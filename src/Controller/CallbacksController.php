<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;

/**
 * Callbacks Controller
 *
 * Manages callback (再コール) reservations.
 */
class CallbacksController extends AppController
{
    protected $Callbacks;
    protected $Users;
    protected $Customers;
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('App.CallbackNotification');
        $this->Callbacks = $this->fetchTable('Callbacks');
        $this->Users = $this->fetchTable('Users');
        $this->Customers = $this->fetchTable('Customers');
    }

    public function index()
    {
        $callbacks = $this->Callbacks->find()
            ->where(['deleted IS' => null])
            ->orderBy(['callback_datetime' => 'DESC'])
            ->all();

        $this->set(compact('callbacks'));
        $this->setListValue();
    }

    public function add()
    {
        $callback = $this->Callbacks->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $now = date('Y-m-d H:i:s');
            $userId = $this->getCurrentUser()?->id ?? 0;

            $data['created'] = $now;
            $data['created_user_id'] = $userId;
            $data['updated'] = $now;
            $data['updated_user_id'] = $userId;
            $data['is_done'] = $data['is_done'] ?? 0;

            $callback = $this->Callbacks->patchEntity($callback, $data);
            if ($this->Callbacks->save($callback)) {
                $this->Flash->success(__('再コール予約の登録が完了しました。'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('再コール予約の登録に失敗しました。もう一度操作してください。'));
        }

        $this->set(compact('callback'));
        $this->setListValue();
    }

    public function edit(?string $id = null)
    {
        $legacy = $this->request->getParam('legacyCallback') === true;
        $isWrite = $this->request->is(['patch', 'post', 'put']);
        $data = $isWrite ? $this->request->getData() : [];
        if ($legacy && $isWrite) {
            $data = $this->request->getData('data.Contact') ?? $this->request->getData('Contact') ?? $data;
        }
        if (!is_array($data)) {
            throw new BadRequestException();
        }
        if ($legacy && $isWrite) {
            $postedId = $data['id'] ?? null;
            if ($id === null && (is_string($postedId) || is_int($postedId)) && ctype_digit((string)$postedId)) {
                $id = (string)$postedId;
            }
            if (array_key_exists('memo', $data)) {
                $data['remark'] = $data['memo'];
            }
        }

        $callback = $this->Callbacks->find()->where(['id' => $id, 'deleted IS' => null])->firstOrFail();
        if ($isWrite) {
            $data = array_intersect_key($data, array_fill_keys(['customer_id', 'phone', 'callback_datetime', 'is_done', 'remark'], true));
            $data['updated'] = date('Y-m-d H:i:s');
            $data['updated_user_id'] = $this->getCurrentUser()?->id ?? 0;

            $callback = $this->Callbacks->patchEntity($callback, $data);
            if ($this->Callbacks->save($callback)) {
                $this->Flash->success(__('再コール予約の更新が完了しました。'));
                return $this->redirect($this->callbackReturnUrl());
            }
            $this->Flash->error(__('再コール予約の更新に失敗しました。もう一度操作してください。'));
        }

        $editUrl = $legacy ? ['_name' => 'legacyCallbackEdit', 'id' => $callback->id] : ['action' => 'edit', $callback->id];
        $deleteUrl = $legacy ? ['_name' => 'legacyCallbackDelete', 'id' => $callback->id] : ['action' => 'delete', $callback->id];
        $cancelUrl = $this->callbackReturnUrl();
        $this->set(compact('callback', 'editUrl', 'deleteUrl', 'cancelUrl'));
        $this->setListValue();
    }

    public function delete(?string $id = null)
    {
        if ($this->request->getParam('legacyCallback') === true && $this->request->is('get')) {
            $this->autoSubmitLegacyAction(['_name' => 'legacyCallbackDelete', 'id' => $id], __('削除'));
            return;
        }

        $this->request->allowMethod(['post', 'delete']);
        $callback = $this->Callbacks->get($id);
        if ($callback->deleted !== null) {
            return $this->redirect($this->callbackReturnUrl());
        }

        $callback->deleted = date('Y-m-d H:i:s');
        $callback->deleted_user_id = $this->getCurrentUser()?->id ?? 0;

        if ($this->Callbacks->save($callback, ['fields' => ['deleted', 'deleted_user_id']])) {
            $this->Flash->success(__('再コール予約の削除が完了しました。'));
        } else {
            $this->Flash->error(__('再コール予約の削除に失敗しました。もう一度操作してください。'));
        }

        return $this->redirect($this->callbackReturnUrl());
    }

    private function callbackReturnUrl(): array
    {
        return $this->request->getParam('legacyCallback') === true
            ? ['controller' => 'Homes', 'action' => 'index']
            : ['action' => 'index'];
    }

    protected function setListValue(): void
    {
        $warning = $this->CallbackNotification->getNumOfWarning();
        $callbackStatusOpt = ['0' => '未', '1' => '完了', '2' => '中止', '3' => '破棄', '4' => '登録誤り', '99' => 'その他'];
        $opeOpt = $this->Users->find('list', keyField: 'id', valueField: 'user_name')
            ->where(['deleted IS' => null])
            ->orderBy(['id' => 'ASC'])
            ->toArray();
        $fields = array_values(array_intersect(['id', 'book_name', 'name_kj', 'name'], $this->Customers->getSchema()->columns()));
        $customers = $this->Customers->find('list', keyField: 'id', valueField: static function ($customer): string {
            return $customer->book_name ?: ($customer->name_kj ?: ($customer->name ?: (string)$customer->id));
        })
            ->select($fields)
            ->where(['deleted IS' => null])
            ->orderBy(['id' => 'ASC'])
            ->toArray();

        $this->set(compact('warning', 'callbackStatusOpt', 'opeOpt', 'customers'));
    }
}
