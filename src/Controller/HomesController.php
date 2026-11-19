<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Client;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Psr\Http\Message\ResponseInterface;

use function Cake\Error\debug;

/**
 * Homes Controller (landing dashboard)
 *
 * Migrated from CakePHP 2.x keeping functional parity.
 */
class HomesController extends AppController
{
    private const CALL_HISTORY_CAMPAIGN_ID = '29';

    protected $CoreSettings;
    protected $MtCids;
    protected $MtPhonelists;
    protected $MtQueues;
    protected $Users;
    protected $Customers;
    public function initialize(): void
    {
        parent::initialize();

        // Tables referenced across actions.
        $this->CoreSettings = $this->fetchTable('CoreSettings');
        $this->MtCids = $this->fetchTable('MtCids');
        $this->MtPhonelists = $this->fetchTable('MtPhonelists');
        $this->MtQueues = $this->fetchTable('MtQueues');
        $this->MtCids = $this->fetchTable('MtCids');
        $this->Users = $this->fetchTable('Users');
        $this->Customers = $this->fetchTable('Customers');
    }

    public function beforeFilter(EventInterface $event): ?ResponseInterface
    {
        parent::beforeFilter($event);
        // Landing page is public after login; rely on Authentication component to guard.
        return null;
    }

    public function index()
    {
        $dialPhone = (string)$this->request->getQuery('tel', $this->request->getParam('phone', ''));

        $core = $this->CoreSettings->find()
            ->where(['deleted IS' => null])
            ->first();

        $limitField = $this->CoreSettings->getCallHistoryLimitField();
        $limit = (int)($core?->{$limitField} ?? 50);
        $response = $this->callCallHistoryApi(
            $core?->ch_api_url,
            (string)$core?->ccid,
            $limit,
            date('Y-m-d', strtotime('-1 month')),
            date('Y-m-d')
        );

        $this->set(compact('response', 'limit', 'dialPhone'));
        $this->setListValue();
    }

    public function play(?string $id = null, ?string $crtObjectId = null)
    {
        $core = $this->CoreSettings->find()
            ->where(['deleted IS' => null])
            ->first();

        $url = $core?->voicelog_url;
        if (!$url) {
            throw new NotFoundException('録音再生URLが設定されていません。');
        }

        $this->set(compact('url', 'id', 'crtObjectId'));
        $this->viewBuilder()->disableAutoLayout();
    }

    public function phoneList()
    {
        $mtPhonelists = $this->MtPhonelists
            ->find()
            ->where(['deleted IS' => null])
            ->orderBy(['disp_cd' => 'ASC'])
            ->all();

        $this->set(compact('mtPhonelists'));
        $this->viewBuilder()->disableAutoLayout();
    }

    public function storeList()
    {
        $mtPhonelists = $this->MtPhonelists
            ->find()
            ->select(['item_name'])
            ->where(['deleted IS' => null])
            ->orderBy(['disp_cd' => 'ASC'])
            ->all();

        $this->set(compact('mtPhonelists'));
        $this->viewBuilder()->disableAutoLayout();
    }

    public function manualDialApi()
    {
        $dialFrom = $this->request->getParam('dialFrom');
        $encodedPhone = $this->request->getParam('phone');
        $phone = $encodedPhone === null ? null : rawurldecode($encodedPhone);
        $returnTo = ['controller' => $dialFrom ?? 'Homes', 'action' => 'index'];
        if ($dialFrom !== null && $phone !== null && $this->request->is('get')) {
            $dialUrl = ['_name' => $dialFrom === 'Customers' ? 'customerDial' : 'contactDial', 'phone' => rawurlencode($phone)];
            $this->autoSubmitLegacyAction($dialUrl, __('発信'));
            return;
        }

        if (!$this->request->is('post')) {
            return $this->redirect($returnTo);
        }

        $session = $this->request->getSession();
        $campaignId = $session->read('campaign');
        $sessionId = $session->read('sessionId');
        if (empty($campaignId) || empty($sessionId)) {
            $this->Flash->error(__('AmeyoJのセッションが正常に取得できませんでした。操作をやり直してください。'));
            return $this->redirect($returnTo);
        }

        $tel = $phone ?? $this->request->getData('tel');
        if (!is_string($tel) || trim($tel) === '') {
            $this->Flash->error(__('発信先TELが未入力です。'));
            return $this->redirect($returnTo);
        }

        $core = $this->CoreSettings->find()
            ->where(['deleted IS' => null])
            ->orderBy(['id' => 'ASC'])
            ->first();
        $callerId = trim((string)$core?->customer_contact_dial_caller_id);

        if ($dialFrom === null) {
            $callerId = $this->request->getData('cid');
            if (!is_string($callerId) || $callerId === '' || !$this->MtCids->exists([
                'cid' => $callerId,
                'campaign_id' => $campaignId,
                'deleted IS' => null,
            ])) {
                $this->Flash->error(__('このキャンペーンの通知番号を選択してください。'));
                return $this->redirect($returnTo);
            }
        } elseif ($callerId === '') {
            $this->Flash->error(__('顧客・対応履歴から発信するための通知番号が設定されていません。'));
            return $this->redirect($returnTo);
        }

        $url = $core?->dial_api_url;
        if (!$url) {
            $this->Flash->error(__('ダイヤルAPIのURLが設定されていません。'));
            return $this->redirect($returnTo);
        }

        $payload = [
            'campaignId' => (string)$campaignId,
            'sessionId' => (string)$sessionId,
            'command-request-id' => '0',
            'phone' => $tel,
            'additionalParams' => [
                'caller_id' => $callerId,
            ],
        ];

        $client = new Client();
        try {
            // The legacy API expected JSON as a URL-encoded data param appended to the URL.
            $response = $client->get(
                $url . urlencode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                [],
                ['headers' => ['Content-Type' => 'application/json']]
            );

            $body = json_decode((string)$response->getBody(), true);
        } catch (\Throwable $e) {
            Log::error('手動発信APIの呼び出しに失敗しました。', ['exceptionClass' => $e::class]);
            $this->Flash->error(__('外部APIの呼び出しに失敗しました。'));
            return $this->redirect($returnTo);
        }

        if (!is_array($body) || ($body['reason'] ?? '') !== 'success') {
            $reason = $body['reason'] ?? null;
            $message = is_string($reason) ? $reason : __('不明なエラーが発生しました。');
            if (strpos($message, '業務を行っていません') !== false) {
                $message = 'ステータスを利用可能に変更してください。';
            } elseif (strpos($message, '他のコールのために予約されています') !== false) {
                $message = '内線セッションエラー：電話機を確認し、再ログインしてください。';
            }
            $this->Flash->error($message);
        }

        return $this->redirect($returnTo);
    }

    /**
     * Call call history API (legacy HttpSocket equivalent).
     */
    private function callCallHistoryApi(?string $url, string $ccid, int $limit, string $dateFrom, string $dateTo): array
    {
        if (!$url) {
            return [];
        }

        $params = [
            'ccid' => $ccid,
            'campaign_id' => self::CALL_HISTORY_CAMPAIGN_ID,
            'limit' => $limit,
            'userid' => '',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $client = new Client();
        try {
            // The legacy endpoint receives its parameters under query index 0.
            $response = $client->get($url, [$params]);
            $body = json_decode((string)$response->getBody(), true);
            return is_array($body) && array_is_list($body) ? $body : [];
        } catch (\Throwable $e) {
            Log::error('通話履歴APIの呼び出しに失敗しました: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Prepare shared option lists for the views.
     */
    protected function setListValue(): void
    {
        $activeOpt = ['0' => '無効', '1' => '有効'];
        $callbackStatusOpt = [
            '0' => '未',
            '1' => '完了',
            '2' => '中止',
            '3' => '破棄',
            '4' => '登録誤り',
            '99' => 'その他',
        ];

        $operators = $this->Users
            ->find('list', keyField: 'id', valueField: 'user_name')
            ->where(['deleted IS' => null])
            ->orderBy(['id' => 'ASC'])
            ->toArray();

        $campaignId = $this->request->getSession()->read('campaign');
        $cidOpt = [];
        if (!empty($campaignId)) {
            $cidOpt = $this->MtCids
                ->find('list', keyField: 'cid', valueField: 'item_name')
                ->where(['deleted IS' => null, 'campaign_id' => $campaignId])
                ->orderBy(['disp_cd' => 'ASC'])
                ->toArray();
        }

        $this->set([
            'activeOpt' => $activeOpt,
            'callbackStatusOpt' => $callbackStatusOpt,
            'opeOpt' => $operators,
            'cidOpt' => $cidOpt,
        ]);
    }
}
