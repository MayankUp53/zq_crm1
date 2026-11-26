<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;

use function Cake\Error\debug;
use function Cake\Error\debug as ErrorDebug;

/**
 * Customers Controller
 *
 * @property \App\Model\Table\CustomersTable $Customers
 * @property \App\Model\Table\ContactsTable $Contacts
 * @property \App\Model\Table\UsersTable $Users
 * @property \App\Model\Table\MtStatusesTable $MtStatuses
 */
class CustomersController extends AppController
{
    protected $Contacts;
    protected $Users;
    protected $MtStatuses;
    protected $MtCids;
    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('App.CallbackNotification');

        // Load additional tables
        $this->Contacts = $this->fetchTable('Contacts');
        $this->Users = $this->fetchTable('Users');
        $this->MtStatuses = $this->fetchTable('MtStatuses');
        $this->MtCids = $this->fetchTable('MtCids');
    }

    /**
     * Clear session method
     *
     * @return \Cake\Http\Response|null|void
     */
    public function clearSession()
    {
        $session = $this->request->getSession();
        $session->delete('Customers.search');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */

    public function index()
    {
        $this->setListValue();
        $session = $this->request->getSession();

        // Handle POST actions (search, clear, add)
        if ($response = $this->handleCustomerPost($session)) {
            return $response;
        }

        //  Get search data from session and defaults
        $searchData = $this->getCustomerSearchData($session);

        // Build query
        $query = $this->buildCustomerQuery($searchData);

        //  CSV download
        if ($this->handleCustomerCsv($query)) {
            return;
        }

        //  Pagination
        $this->paginate = ['limit' => 50];
        $customers = $this->paginate($query);

        // Set variables for view
        $this->set(compact('customers', 'searchData'));
    }

    // ====================
    // Handle POST actions
    // ====================
    private function handleCustomerPost($session)
    {
        if (!$this->request->is('post')) return null;

        if ($this->request->getData('searching')) {
            $session->write('Customers.search', $this->request->getData('Search', []));
            return $this->redirect(['action' => 'index']);
        }

        if ($this->request->getData('clear') && method_exists($this, 'clearSession')) {
            return $this->clearSession();
        }

        if ($this->request->getData('add')) {
            return $this->redirect(['action' => 'add']);
        }

        return null;
    }

    // ====================
    //  Get search data with defaults
    // ====================
    private function getCustomerSearchData($session): array
    {
        $searchData = $session->read('Customers.search') ?? [];
        return $searchData + $this->customerSearchDefaults();
    }

    // ====================
    //  Build query with filters
    // ====================
    private function buildCustomerQuery(array $searchData)
    {
        $query = $this->Customers->find()->where(['Customers.deleted IS' => null]);
        foreach ([
            'client_code', 'book_name', 'base_name', 'sales_staff_cd', 'collection_cls', 'direct_cls',
            'reserve1', 'reserve2', 'sub_phone1', 'sub_phone2', 'sub_phone3',
            'sub_phone4', 'sub_phone5',
        ] as $field) {
            $value = trim((string)($searchData[$field] ?? ''));
            if ($value !== '') {
                $query->where(
                    ['Customers.' . $field . ' LIKE' => '%' . $value . '%'],
                    ['Customers.' . $field => 'string']
                );
            }
        }
        $salesPerson = trim((string)($searchData['sales_person'] ?? ''));
        if ($salesPerson !== '') {
            $query->where(['OR' => [
                'Customers.sales_person LIKE' => '%' . $salesPerson . '%',
                'Customers.sales_rep_name LIKE' => '%' . $salesPerson . '%',
            ]]);
        }
        $tel = trim((string)($searchData['tel_only'] ?? ''));
        if ($tel !== '') {
            $tel = str_replace(['-', ' ', '－', '+'], '', $tel);
            $query->where([$this->customerPhoneExpression()])
                ->bind(':phone', '%' . $tel . '%', 'string');
        }
        foreach (['tel2_only', 'tel3_only'] as $field) {
            $value = trim((string)($searchData[$field] ?? ''));
            if ($value !== '') {
                $query->where(['Customers.' . $field . ' LIKE' => '%' . $value . '%']);
            }
        }
        $address = trim((string)($searchData['addr1'] ?? ''));
        if ($address !== '') {
            $query->where(['Customers.addr1 LIKE' => '%' . $address . '%']);
        }
        $address2 = trim((string)($searchData['addr2_search'] ?? ''));
        if ($address2 !== '') {
            $query->where(['Customers.addr2 LIKE' => '%' . $address2 . '%']);
        }
        if (!empty($searchData['created_from'])) {
            $query->where(['Customers.created >=' => $searchData['created_from']]);
        }
        if (!empty($searchData['created_to'])) {
            $end = $searchData['created_to'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                $end .= ' 23:59:59';
            }
            $query->where(['Customers.created <=' => $end]);
        }
        return $query->orderBy(['Customers.created' => 'DESC', 'Customers.id' => 'DESC']);
    }

    // ====================
    //  Handle CSV download
    // ====================
    private function handleCustomerCsv($query): bool
    {
        if (!$this->request->is('post') || !$this->request->getData('download')) {
            return false;
        }

        $operators = $this->viewBuilder()->getVar('opeOpt') ?? [];
        $fields = array_keys($this->customerExportColumns());
        $exportRows = [];
        foreach ($query->all() as $customer) {
            $row = $customer->extract($fields);
            // The legacy query selected notes3/notes4, while its export read remarks3/remarks4.
            $row['remarks3'] = $row['remarks4'] = '';
            $row['client_code'] = $customer->client_code ?? $customer->client_cd;
            $row['created_user'] = $operators[$customer->created_user_id] ?? '';
            $row['updated_user'] = $operators[$customer->updated_user_id] ?? '';
            $row['created'] = $customer->created?->format('Y-m-d H:i:s');
            $row['updated'] = $customer->updated?->format('Y-m-d H:i:s');
            $exportRows[] = $row;
        }

        $this->csv($exportRows);

        return true;
    }

    public function csv($results = null)
    {
        $filename = rawurlencode('顧客情報_' . date('Y-m-d H:i:s') . '.csv');
        $columns = $this->customerExportColumns();
        $results ??= [];

        $this->set(compact('columns', 'results'));
        $this->setResponse($this->getResponse()->withType('application/vnd.ms-excel')->withDownload($filename));
        $this->viewBuilder()->disableAutoLayout();
        $this->render('download');
    }

    /**
     * Jump method - search by phone number
     *
     * @param string|null $phone Phone number
     * @return \Cake\Http\Response|null|void
     */
    public function jump(?string $phone = null)
    {
        if (empty($phone)) {
            return $this->redirect(['action' => 'index']);
        }

        $activeCustomers = $this->Customers->find()->where(['Customers.deleted IS' => null]);
        $matches = (clone $activeCustomers)
            ->select(['Customers.id'])
            ->where(['OR' => ['Customers.tel' => $phone, 'Customers.tel2' => $phone]])
            ->limit(2)
            ->toArray();

        if (count($matches) < 2) {
            $id = $matches[0]->id ?? $this->autoAdd($phone);
            if ($id > 0) {
                return $this->redirect(['controller' => 'Contacts', 'action' => 'add', $id]);
            }
            $this->Flash->error(__('顧客情報が見つかりませんでした。電話番号：{0}', $phone));
            return $this->redirect(['action' => 'index']);
        }

        $this->setListValue();
        $pattern = '%' . $phone . '%';
        $query = $activeCustomers->where(['OR' => ['Customers.tel LIKE' => $pattern, 'Customers.tel2 LIKE' => $pattern]]);
        $customers = $this->paginate($query, ['limit' => 100]);
        $this->set(compact('customers', 'phone'));
        $this->viewBuilder()->setTemplate('index');
    }

    private function customerPhoneExpression(): string
    {
        $phoneFields = [
            'tel', 'tel_only', 'phone', 'phone_only', 'tel2', 'tel2_only', 'tel3', 'tel3_only',
            'sub_phone1', 'sub_phone2', 'sub_phone3', 'sub_phone4', 'sub_phone5',
            'incharge1_phone', 'incharge2_phone', 'incharge3_phone', 'incharge4_phone',
        ];
        $phoneFields = array_intersect($phoneFields, $this->Customers->getSchema()->columns());

        return implode(' OR ', array_map(
            static fn(string $field): string => "REPLACE(REPLACE(REPLACE(REPLACE(Customers.{$field}, '-', ''), ' ', ''), '－', ''), '+', '') LIKE :phone",
            $phoneFields
        ));
    }

    /**
     * Auto-add customer by phone number
     *
     * @param string|null $phone Phone number
     * @return int Customer ID
     */
    protected function autoAdd(?string $phone = null): int
    {
        $latestId = 0;
        if (empty($phone)) {
            return $latestId;
        }

        $customer = $this->Customers->newEmptyEntity();
        $now = date('Y-m-d H:i:s');
        $currentUser = $this->getCurrentUser();

        $dispNo = (new \DateTimeImmutable())->format('ymdHisv');

        $customer->created = $now;
        $customer->created_user_id = $currentUser ? $currentUser->id : 0;
        $customer->updated = $now;
        $customer->updated_user_id = $currentUser ? $currentUser->id : 0;
        $customer->disp_no = $dispNo;
        $customer->client_code = $dispNo;
        $customer->client_cd = 'ameyo_' . bin2hex(random_bytes(8));
        $customer->tel = $phone;
        $customer->tel_only = preg_replace('/[- －+]+/u', '', $phone);
        $customer->zip_cd = '';
        $customer->addr1 = '';

        if ($this->Customers->save($customer)) {
            $latestId = $customer->id;
        }

        return $latestId;
    }

    /**
     * Ameyo method
     *
     * @param string|null $campaign Campaign ID
     * @param string|null $sessionId Session ID
     * @param string|null $queueId Queue ID
     * @param string|null $queueName Queue name
     * @param string|null $phone Phone number
     * @param string|null $dstPhone Destination phone
     * @param string|null $customerId Customer ID
     * @param string|null $crtObjectId Call recording ID
     * @return \Cake\Http\Response|null|void
     */
    public function ameyo(
        ?string $campaign = null,
        ?string $sessionId = null,
        ?string $queueId = null,
        ?string $queueName = null,
        ?string $phone = null,
        ?string $dstPhone = null,
        ?string $customerId = null,
        ?string $crtObjectId = null
    ) {
        $session = $this->request->getSession();

        // Clear session
        $session->delete('campaign');
        $session->delete('sessionId');
        $session->delete('queueId');
        $session->delete('queueName');
        $session->delete('phone');
        $session->delete('dstPhone');
        $session->delete('customerId');
        $session->delete('crtObjectId');

        // Set session values
        $session->write([
            'campaign' => $campaign,
            'sessionId' => $sessionId,
            'queueId' => $queueId,
            'queueName' => $queueName,
            'phone' => $phone,
            'dstPhone' => $dstPhone,
            'customerId' => $customerId,
            'crtObjectId' => $crtObjectId,
        ]);

        if ($phone === null || trim($phone) === '') {
            return $this->redirect(['action' => 'index']);
        }

        $phoneFields = ['tel', 'sub_phone1', 'sub_phone2', 'sub_phone3', 'sub_phone4', 'sub_phone5'];
        $activeCustomers = $this->Customers->find()->where(['Customers.deleted IS' => null]);
        $isAnonymous = $phone === 'unknown' || $phone === 'anonymous';
        $matches = $isAnonymous ? [] : (clone $activeCustomers)
            ->select(['Customers.id'])
            ->where(['OR' => array_fill_keys($phoneFields, $phone)])
            ->limit(2)
            ->toArray();

        if (!$matches) {
            $id = $this->autoAdd($phone);
            if ($id <= 0) {
                $this->Flash->error(__('顧客の自動登録に失敗しました。もう一度操作してください。'));
                return $this->redirect(['action' => 'index']);
            }
            $message = $isAnonymous
                ? __('新規顧客（非通知の電話番号）の自動登録を行いました。:{0}', $id)
                : __('新規顧客（未登録の電話番号）の自動登録を行いました。:{0}', $id);
            $this->Flash->success($message);
            return $this->redirect(['action' => 'edit', $id]);
        }

        if (count($matches) === 1) {
            return $this->redirect(['action' => 'edit', $matches[0]->id]);
        }

        $this->setListValue();
        $likeFields = array_map(static fn(string $field): string => $field . ' LIKE', $phoneFields);
        $query = $activeCustomers->where(['OR' => array_fill_keys($likeFields, '%' . $phone . '%')]);
        $this->paginate = ['limit' => 50];
        $customers = $this->paginate($query);
        $this->set(compact('customers', 'phone'));
        $this->viewBuilder()->setTemplate('index');
    }

    public function view(?string $id = null)
    {
        $customer = $this->Customers->find()
            ->where(['Customers.id' => $id, 'Customers.deleted IS' => null])
            ->firstOrFail();
        $contacts = $this->Contacts->find()
            ->where(['Contacts.customer_id' => $customer->id, 'Contacts.deleted IS' => null])
            ->orderBy(['Contacts.updated' => 'DESC'])
            ->all();

        $this->set(compact('customer', 'contacts'));
        $this->setListValue();
    }

    public function add()
    {
        $customer = $this->Customers->newEmptyEntity();

        if ($this->request->is('post')) {

            // 🔙 Back / Cancel button
            if ($this->request->getData('back')) {
                return $this->redirect(['action' => 'index']);
            }

            $data = $this->customerFormData();
            $now = date('Y-m-d H:i:s');
            $currentUser = $this->getCurrentUser();

            // Audit fields
            $data['created'] = $now;
            $data['created_user_id'] = $currentUser ? $currentUser->id : 0;
            $data['updated'] = $now;
            $data['updated_user_id'] = $currentUser ? $currentUser->id : 0;


            $customer = $this->Customers->patchEntity($customer, $data, ['validate' => 'customerForm']);

            if ($this->Customers->save($customer)) {
                $this->Flash->success(__('顧客情報の登録が完了しました。'));
                return $this->redirect(['action' => 'index']);
            }

            $errorMsg = $this->formatEntityErrors($customer);
            $this->Flash->error(__('顧客情報の登録に失敗しました。 {0}', $errorMsg));
        }

        $this->set(compact('customer'));
        $this->setListValue();
    }


    /**
     * Edit method
     *
     * @param string|null $id Customer id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        $customer = $this->Customers->find()
            ->where(['Customers.id' => $id, 'Customers.deleted IS' => null])
            ->firstOrFail();

        if ($this->request->is(['post', 'patch', 'put'])) {
            if ($this->request->getData('back')) {
                return $this->redirect(['controller' => 'Contacts', 'action' => 'add', $customer->id]);
            }
            if ($this->request->getData('delete')) {
                return $this->delete((string)$customer->id);
            }

            $data = $this->customerFormData();
            $data['updated'] = date('Y-m-d H:i:s');
            $data['updated_user_id'] = $this->getCurrentUser()?->id ?? 0;
            $customer = $this->Customers->patchEntity($customer, $data, ['validate' => 'customerForm']);
            if ($this->Customers->save($customer)) {
                $this->Flash->success(__('顧客情報の更新が完了しました。'));
                return $this->redirect(['controller' => 'Contacts', 'action' => 'add', $customer->id]);
            }
            $this->Flash->error(__('顧客情報の更新に失敗しました。 {0}', $this->formatEntityErrors($customer)));
        }

        $this->set(compact('customer'));
        $this->setListValue();
    }

    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'patch', 'put', 'delete']);
        $currentUser = $this->getCurrentUser();
        if (!$currentUser || (string)$currentUser->auth === '0') {
            throw new \Cake\Http\Exception\ForbiddenException('顧客情報を削除する権限がありません。');
        }
        $customer = $this->Customers->find()
            ->where(['Customers.id' => $id, 'Customers.deleted IS' => null])
            ->firstOrFail();

        $now = date('Y-m-d H:i:s');
        $affected = 0;
        $saved = $this->Customers->getConnection()->transactional(function () use ($customer, $currentUser, $now, &$affected) {
            $customer->deleted = $now;
            $customer->deleted_user_id = $currentUser->id;
            if (!$this->Customers->save($customer)) {
                return false;
            }
            $affected = $this->Contacts->updateAll(
                ['deleted' => $now, 'deleted_user_id' => $currentUser->id],
                ['customer_id' => $customer->id]
            );
            return true;
        });

        if ($saved) {
            $this->Flash->success(__('顧客情報と{0}件の対応履歴の削除が完了しました。', $affected));
        } else {
            $this->Flash->error(__('顧客情報の削除に失敗しました。もう一度操作してください。'));
        }
        return $this->redirect(['action' => 'index']);
    }

    private function customerFormData(): array
    {
        $fields = [
            'client_code', 'client_branch_code', 'client_name', 'client_branch_name',
            'addr1', 'addr2', 'addr3', 'tel', 'fax', 'tel2', 'section_name', 'sales_person', 'deadline',
            'incharge1_name', 'incharge1_phone', 'incharge2_name', 'incharge2_phone',
            'incharge3_name', 'incharge3_phone', 'incharge4_name', 'incharge4_phone',
            'notes1', 'notes2', 'notes3', 'notes4',
            'zip_cd', 'book_name', 'base_name', 'tel3', 'tel2_only', 'tel3_only',
            'sub_phone1', 'sub_phone2', 'sub_phone3', 'sub_phone4', 'sub_phone5',
            'collection_cls', 'direct_cls', 'summary', 'factory_desc',
            'remarks1', 'remarks2', 'remarks5', 'remarks6',
            'reserve1', 'reserve2', 'reserve5', 'sales_rep_name', 'sales_staff_cd', 'closing_date',
        ];
        $data = array_intersect_key($this->request->getData(), array_flip($fields));
        if (array_key_exists('client_code', $data)) {
            $data['client_cd'] = (string)$data['client_code'];
        }
        if (array_key_exists('tel', $data)) {
            $data['tel_only'] = preg_replace('/[- －]+/u', '', (string)$data['tel']);
        }
        foreach (['tel2', 'tel3'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field . '_only'] = preg_replace('/[- －]+/u', '', (string)$data[$field]);
            }
        }
        return $data;
    }

    protected function setListValue(): void
    {
        $warning = $this->CallbackNotification->getNumOfWarning();
        $activeOpt = ['0' => '無効', '1' => '有効'];
        $sexOpt = $this->sexOptions();

        // Operators (users)
        $opeOpt = $this->Users->find('list', keyField: 'id', valueField: 'user_name')
            ->where(['Users.deleted IS' => null])
            ->orderBy(['Users.disp_cd' => 'ASC'])
            ->toArray();

        // All users (including deleted)
        $allUsersOpt = $this->Users->find('list', keyField: 'id', valueField: 'user_name')
            ->orderBy(['Users.deleted' => 'ASC', 'Users.disp_cd' => 'ASC'])
            ->toArray();

        // Status options (removed active_flg filter)
        $statusOpt = $this->MtStatuses->find('list', keyField: 'id', valueField: 'item_name')
            ->orderBy(['MtStatuses.disp_cd' => 'ASC'])
            ->toArray();

 $session = $this->request->getSession();

        $cidOpt = $this->MtCids
            ->find('list', keyField: 'cid', valueField: 'item_name')
            ->where([
                'deleted IS' => null,
               'campaign_id IS' => $session->read('campaign'),
                     // 'campaign_id IS' => 1,
            ])
            ->orderBy(['disp_cd' => 'ASC'])
            ->toArray();

      //  $cidOpt =[1=>'着信専用','2'=>'発信専用','3'=>'着信・発信両用'];

        $this->set(compact('warning', 'activeOpt', 'sexOpt', 'opeOpt', 'allUsersOpt', 'statusOpt', 'cidOpt'));
    }

    protected function customerSearchDefaults(): array
    {
        return [
            'client_cd' => '',
            'company_name' => '',
            'name'      => '',
            'type_of_number' => '',
            'tel'       => '',
            'tel_only' => '',
            'tel2_only' => '',
            'tel3_only' => '',
            'book_name' => '',
            'base_name' => '',
            'addr1' => '',
            'addr2' => '',
            'addr2_search' => '',
            'client_code' => '',
            'sales_person' => '',
            'sales_staff_cd' => '',
            'closing_date' => '',
            'collection_cls' => '',
            'direct_cls' => '',
            'reserve1' => '',
            'reserve2' => '',
            'sub_phone1' => '',
            'sub_phone2' => '',
            'sub_phone3' => '',
            'sub_phone4' => '',
            'sub_phone5' => '',
            'email'     => '',
            'fax'       => '',
            'created'   => '',
        ];
    }

    protected function customerExportColumns(): array
    {
        return [
            'client_code' => 'キー',
            'base_name' => '拠点',
            'book_name' => '得意先名(帳合先名)',
            'client_branch_code' => '複数口座CD',
            'reserve1' => '得意先CD',
            'reserve2' => '帳合先・納品先CD',
            'addr1' => '住所1',
            'addr2' => '住所2',
            'fax' => 'FAX',
            'tel' => 'TEL',
            'sub_phone1' => 'サブ電話番号1',
            'sub_phone2' => 'サブ電話番号2',
            'sub_phone3' => 'サブ電話番号3',
            'sub_phone4' => 'サブ電話番号4',
            'sub_phone5' => 'サブ電話番号5',
            'sales_staff_cd' => '営業員CD',
            'sales_rep_name' => '営業担当者名',
            'closing_date' => '締日',
            'collection_cls' => '回収区分',
            'direct_cls' => 'ベッツダイレクト区分',
            'remarks1' => '備考1(受注時摘要)',
            'remarks2' => '備考2(出荷時摘要)',
            'remarks3' => '備考3',
            'remarks4' => '備考4',
            'remarks5' => '備考5',
            'remarks6' => '備考6',
            'created_user' => '登録者',
            'created' => '登録日時',
            'updated_user' => '更新者',
            'updated' => '更新日時',
        ];
    }

    protected function normalizeCustomerPayload(array $data, bool $isCreate = true): array
    {
        if (empty($data['disp_no'])) {
            $data['disp_no'] = $this->generateDisplayNumber();
        }

        $data['zip_cd'] = $data['zip_cd'] ?? '';
        $data['addr1'] = $data['addr1'] ?? '';

        foreach (['addr2', 'addr3', 'name', 'name_kj', 'name_kn', 'email', 'tel_only', 'tel', 'phone_only', 'phone', 'fax', 'remark', 'ex1', 'ex2', 'ex3'] as $field) {
            if (!array_key_exists($field, $data)) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    protected function generateDisplayNumber(): string
    {
        $a = explode('.', (string)microtime(true));
        return date('ymdHis') . str_pad(substr($a[1] ?? '000', 0, 3), 3, '0');
    }

    protected function sexOptions(): array
    {
        return ['1' => '男性', '2' => '女性'];
    }
}
