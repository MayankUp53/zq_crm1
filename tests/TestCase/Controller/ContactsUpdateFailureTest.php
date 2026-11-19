<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\ContactsController;
use App\Model\Entity\Contact;
use App\Model\Entity\Customer;
use App\Model\Entity\User;
use App\Model\Table\ContactsTable;
use App\View\AppView;
use Authentication\Identity;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\ORM\Table;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ContactsUpdateFailureTest extends TestCase
{
    public function testAddFormMatchesLegacyFieldsAndRequiredType(): void
    {
        Router::reload();
        (require CONFIG . 'routes.php')(Router::createRouteBuilder('/'));
        $request = (new ServerRequest(['environment' => ['REQUEST_METHOD' => 'GET'], 'session' => new Session()]))
            ->withAttribute('params', ['controller' => 'Contacts', 'action' => 'add', 'plugin' => null, '_ext' => null, 'pass' => ['1']]);
        Router::setRequest($request);
        $view = new AppView($request);
        $view->set([
            'customer' => new Customer(['id' => 1, 'client_code' => '123456', 'book_name' => 'Legacy customer', 'tel' => '0312345678']),
            'contact' => new Contact(['customer_id' => 1]),
            'contacts' => [],
            'statusOpt' => [1 => 'Open'],
            'queueOpt' => [2 => 'Retail'],
            'queueSelectedValue' => null,
            'allUsersOpt' => [],
        ]);
        $html = $view->render('Contacts/add', false);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $controls = $xpath->query('//form[contains(@class, "contact-add-form")]//table[contains(@class, "contact-entry")]//input | //form[contains(@class, "contact-add-form")]//table[contains(@class, "contact-entry")]//select | //form[contains(@class, "contact-add-form")]//table[contains(@class, "contact-entry")]//textarea');
        $fieldNames = [];
        foreach ($controls as $control) {
            $fieldNames[] = $control->getAttribute('name');
        }

        self::assertSame(['summary', 'queue_name', 'status', 'detail', 'remark'], $fieldNames);
        self::assertSame('種別＊', trim($xpath->query('//table[contains(@class, "contact-entry")]/tbody/tr[1]/td[3]')->item(0)->textContent));
        self::assertSame('ステータス', trim($xpath->query('//table[contains(@class, "contact-entry")]/tbody/tr[2]/td[1]')->item(0)->textContent));
        self::assertNotEmpty($xpath->query('//select[@name="queue_name"][@required]')->length);
        self::assertSame(0, $xpath->query('//select[@name="status"][@required]')->length);
        self::assertStringContainsString('Legacy customer', $html);
        self::assertSame(1, $xpath->query('//form[@action="/contacts/manualDialAPI/0312345678"][@method="post"]')->length);

        $validator = (new ContactsTable())->validationDefault(new Validator());
        self::assertSame([], $validator->validate(['customer_id' => 1, 'queue_name' => '2']));
        self::assertArrayHasKey('queue_name', $validator->validate(['customer_id' => 1]));
    }

    public function testInvalidUpdateKeepsDetailPageHistoryAndSubmittedValues(): void
    {
        ConnectionManager::setConfig('contact_update_failure', [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => ':memory:',
        ]);
        $connection = ConnectionManager::get('contact_update_failure');
        try {
            $connection->execute('CREATE TABLE customers (id INTEGER PRIMARY KEY, client_name TEXT, book_name TEXT)');
            $connection->execute('CREATE TABLE contacts (id INTEGER PRIMARY KEY, customer_id INTEGER, summary TEXT, detail TEXT, remark TEXT, status TEXT, queue_name TEXT, updated_user_id INTEGER, updated DATETIME, deleted DATETIME)');
            $connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, user_name TEXT)');
            $connection->insert('customers', ['id' => 1, 'client_name' => 'Existing customer', 'book_name' => 'Existing customer']);
            $connection->insert('users', ['id' => 7, 'user_name' => 'Operator']);
            $connection->insert('contacts', ['id' => 10, 'customer_id' => 1, 'summary' => 'Saved history', 'status' => '1', 'updated_user_id' => 7]);

            Router::reload();
            Router::createRouteBuilder('/')->scope('/', function (RouteBuilder $routes): void {
                $routes->setRouteClass(DashedRoute::class);
                $routes->fallbacks();
            });
            $request = (new ServerRequest(['environment' => ['REQUEST_METHOD' => 'POST'], 'session' => new Session()]))
                ->withParsedBody(['update' => '1', 'summary' => 'Unsaved summary', 'detail' => 'Unsaved detail', 'remark' => 'Unsaved memo', 'status' => ''])
                ->withAttribute('params', ['controller' => 'Contacts', 'action' => 'edit', 'plugin' => null, '_ext' => null, 'pass' => ['10']])
                ->withAttribute('identity', new Identity(new User(['id' => 7, 'auth' => '9'])));
            Router::setRequest($request);
            $controller = new ContactUpdateFailureHarness($request, 'Contacts');
            $response = $controller->edit('10');

            self::assertSame(200, $response->getStatusCode());
            $body = (string)$response->getBody();
            foreach (['Existing customer', 'Saved history', 'Unsaved summary', 'Unsaved detail', 'Unsaved memo', '履歴一覧', 'customer-details'] as $text) {
                self::assertStringContainsString($text, $body);
            }
            self::assertStringContainsString('/contacts/edit/10', $body);
            self::assertNotEmpty($controller->viewBuilder()->getVar('contact')->getErrors());
            self::assertNotEmpty($request->getSession()->read('Flash.flash'));
            self::assertSame('Saved history', $connection->execute('SELECT summary FROM contacts WHERE id = 10')->fetch('assoc')['summary']);
        } finally {
            $connection->getDriver()->disconnect();
            ConnectionManager::drop('contact_update_failure');
            Router::reload();
        }
    }

    public function testLegacySearchFiltersCoverCustomerPhonesAndQueue(): void
    {
        ConnectionManager::setConfig('contact_legacy_search', [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => ':memory:',
        ]);
        $connection = ConnectionManager::get('contact_legacy_search');
        try {
            $customerFields = [
                'client_name', 'client_branch_name', 'book_name', 'base_name', 'zip_cd',
                'addr1', 'addr2', 'addr3', 'tel', 'tel_only', 'tel2', 'tel3',
                'tel2_only', 'tel3_only', 'sub_phone1', 'sub_phone2', 'sub_phone3', 'sub_phone4',
                'sub_phone5', 'incharge1_phone', 'incharge2_phone', 'incharge3_phone', 'incharge4_phone',
            ];
            $connection->execute('CREATE TABLE customers (id INTEGER PRIMARY KEY, ' . implode(', ', array_map(static fn($field) => $field . ' TEXT', $customerFields)) . ')');
            $connection->execute('CREATE TABLE contacts (id INTEGER PRIMARY KEY, customer_id INTEGER, summary TEXT, detail TEXT, remark TEXT, status TEXT, queue_name TEXT, created_user_id INTEGER, updated_user_id INTEGER, created DATETIME, updated DATETIME, deleted DATETIME)');
            $connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, user_name TEXT)');
            $connection->execute('CREATE TABLE mt_queues (id INTEGER PRIMARY KEY, item_name TEXT, active_flg INTEGER)');
            $connection->execute('CREATE TABLE mt_statuses (id INTEGER PRIMARY KEY, item_name TEXT, active INTEGER)');
            $connection->insert('mt_queues', ['id' => 2, 'item_name' => 'Retail', 'active_flg' => 1]);
            $connection->insert('mt_statuses', ['id' => 1, 'item_name' => 'Open', 'active' => 1]);
            $connection->insert('customers', [
                'id' => 1,
                'client_name' => 'Current name',
                'book_name' => 'Legacy book',
                'base_name' => 'East base',
                'addr2' => 'Tokyo',
                'tel' => '090-1234-5678',
                'tel_only' => '09012345678',
            ]);
            $connection->insert('contacts', [
                'id' => 10,
                'customer_id' => 1,
                'summary' => 'Saved history',
                'detail' => 'Shipping detail',
                'remark' => 'Call memo',
                'status' => '1',
                'queue_name' => '2',
                'created_user_id' => 7,
                'created' => '2024-01-15 12:00:00',
                'updated' => '2024-02-10 12:00:00',
            ]);

            $request = (new ServerRequest(['environment' => ['REQUEST_METHOD' => 'GET'], 'session' => new Session()]))
                ->withAttribute('params', ['controller' => 'Contacts', 'action' => 'index', 'plugin' => null, '_ext' => null, 'pass' => []])
                ->withAttribute('identity', new Identity(new User(['id' => 7, 'auth' => '9'])));
            $controller = new ContactUpdateFailureHarness($request, 'Contacts', $connection);
            $baseQuery = new \ReflectionMethod(ContactsController::class, 'buildBaseQuery');
            $applyFilters = new \ReflectionMethod(ContactsController::class, 'applySearchFilters');
            $queryFor = static function (array $filters) use ($controller, $baseQuery, $applyFilters) {
                $query = $baseQuery->invoke($controller);
                $applyFilters->invoke($controller, $query, $filters);
                return $query;
            };

            self::assertSame(1, $queryFor([
                'base_name' => 'East',
                'book_name' => 'Legacy',
                'tel' => '0901234',
                'addr' => 'Tokyo',
                'status' => '1',
                'created_user_id' => [7],
                'queue_name' => ['2'],
                'summary' => 'Saved,history',
                'detail' => 'Shipping',
                'remark' => 'Call',
                'created_from' => '2024-01-01',
                'created_to' => '2024-01-15',
                'updated_from' => '2024-02-01',
                'updated_to' => '2024-02-10',
            ])->count());
            self::assertSame(0, $queryFor(['queue_name' => ['Other']])->count());
            self::assertSame(0, $queryFor(['status' => '2'])->count());
            self::assertSame(0, $queryFor(['summary' => 'Saved,missing'])->count());

            $download = $request->withMethod('POST')->withParsedBody(['download' => '1']);
            $controller = new ContactUpdateFailureHarness($download, 'Contacts', $connection);
            $controller->index();
            $csv = mb_convert_encoding((string)$controller->getResponse()->getBody(), 'UTF-8', 'SJIS-win');
            [$header, $row] = explode("\n", $csv);
            self::assertSame('ステータス,種別,TEL,拠点,得意先名(帳合先名),概要,対応内容,メモ,住所,担当OP,登録日', $header);
            self::assertSame(['Retail', 'Open', '090-1234-5678', 'Legacy book', 'East base', 'Saved history', 'Shipping detail', 'Call memo', 'Tokyo', 'Operator', '2024-01-15 12:00:00'], str_getcsv($row));
        } finally {
            $connection->getDriver()->disconnect();
            ConnectionManager::drop('contact_legacy_search');
            Router::reload();
        }
    }
}

class ContactUpdateFailureHarness extends ContactsController
{
    private ?Connection $testConnection = null;

    public function __construct($request, string $name, ?Connection $connection = null)
    {
        $this->testConnection = $connection;
        parent::__construct($request, $name);
    }

    public function initialize(): void
    {
        $connection = $this->testConnection ?? ConnectionManager::get('contact_update_failure');
        $this->Customers = new Table(['connection' => $connection, 'table' => 'customers', 'alias' => 'Customers']);
        $this->Contacts = new Table(['connection' => $connection, 'table' => 'contacts', 'alias' => 'Contacts', 'entityClass' => Contact::class]);
        $this->Contacts->belongsTo('Customers', ['targetTable' => $this->Customers, 'foreignKey' => 'customer_id']);
        $this->Contacts->getValidator()->notEmptyString('status', 'Select a status.');
        $this->Users = new Table(['connection' => $connection, 'table' => 'users', 'alias' => 'Users']);
        $this->MtQueues = new Table(['connection' => $connection, 'table' => 'mt_queues']);
        $this->MtStatuses = new Table(['connection' => $connection, 'table' => 'mt_statuses']);
        $this->loadComponent('Flash');
        $this->viewBuilder()->disableAutoLayout();
    }

    protected function getCurrentUser(): ?User
    {
        return new User(['id' => 7, 'auth' => '9']);
    }

    protected function setListValue(?string $selectedQueueName = null): void
    {
        $this->set(['allUsersOpt' => [7 => 'Operator'], 'statusOpt' => [1 => 'Open'], 'queueOpt' => []]);
    }
}
