<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\CustomersController;
use App\Model\Entity\User;
use App\Model\Table\CustomersTable;
use App\View\AppView;
use Authentication\Identity;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\ORM\Table;
use Cake\Routing\Router;
use PHPUnit\Framework\TestCase;

/** Exercises the migrated customer flow without touching the application database. */
class CustomersFlowTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        ConnectionManager::setConfig('customers_flow', ['className' => Connection::class, 'driver' => Sqlite::class, 'database' => ':memory:']);
        $this->connection = ConnectionManager::get('customers_flow');
        $fields = [
            'client_cd', 'client_branch_code', 'client_name', 'client_branch_name', 'book_name', 'base_name',
            'addr1', 'addr2', 'addr3', 'tel', 'tel_only', 'tel2', 'tel3', 'tel2_only', 'tel3_only', 'phone', 'phone_only', 'fax',
            'section_name', 'sales_person', 'sales_staff_cd', 'sales_rep_name', 'closing_date',
            'collection_cls', 'direct_cls', 'reserve1', 'reserve2', 'reserve5',
            'sub_phone1', 'sub_phone2', 'sub_phone3', 'sub_phone4', 'sub_phone5',
            'summary', 'factory_desc', 'remarks1', 'remarks2', 'remarks5', 'remarks6',
            'incharge1_name', 'incharge1_phone', 'incharge2_name', 'incharge2_phone',
            'incharge3_name', 'incharge3_phone', 'incharge4_name', 'incharge4_phone',
            'notes1', 'notes2', 'notes3', 'notes4',
        ];
        $columns = implode(', ', array_map(static fn($field) => $field . ' TEXT', $fields));
        $this->connection->execute('CREATE TABLE customers (id INTEGER PRIMARY KEY AUTOINCREMENT, client_code INTEGER, ' . $columns . ', created DATETIME, updated DATETIME, deleted DATETIME, created_user_id INTEGER, updated_user_id INTEGER, deleted_user_id INTEGER, UNIQUE(client_code, client_branch_code))');
        $this->connection->execute('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER, summary TEXT, status TEXT, updated DATETIME, deleted DATETIME, deleted_user_id INTEGER)');
        $this->connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, user_name TEXT)');
        $this->connection->insert('users', ['id' => 7, 'user_name' => 'Operator']);
        Router::reload();
        (require CONFIG . 'routes.php')(Router::createRouteBuilder('/'));
    }

    protected function tearDown(): void
    {
        $this->connection->getDriver()->disconnect();
        ConnectionManager::drop('customers_flow');
        parent::tearDown();
    }

    private function controller(string $action, string $method = 'GET', array $data = [], string $auth = '9'): CustomerFlowHarness
    {
        $request = (new ServerRequest(['environment' => ['REQUEST_METHOD' => $method], 'session' => new Session()]))
            ->withParsedBody($data)
            ->withAttribute('params', ['controller' => 'Customers', 'action' => $action, 'plugin' => null, '_ext' => null, 'pass' => []])
            ->withAttribute('identity', new Identity(new User(['id' => 7, 'auth' => $auth])));
        Router::setRequest($request);
        $controller = new CustomerFlowHarness($request, 'Customers');
        $controller->auth = $auth;
        return $controller;
    }

    private function addCustomer(array $data = []): int
    {
        $controller = $this->controller('add', 'POST', $data + ['client_code' => '123456', 'client_branch_code' => '001', 'client_name' => 'Customer', 'tel' => '03-1234-5678', 'tel2' => '090-1234-5678', 'add' => '1']);
        $response = $controller->add();
        self::assertNotNull($response, json_encode($controller->viewBuilder()->getVar('customer')?->getErrors()));
        self::assertStringContainsString('/customers', $response->getHeaderLine('Location'));
        return (int)$this->connection->execute('SELECT MAX(id) AS id FROM customers')->fetch('assoc')['id'];
    }

    public function testAddEditAndCancelNavigation(): void
    {
        $id = $this->addCustomer([
            'notes1' => 'Note',
            'incharge4_phone' => '040',
            'book_name' => 'Legacy book',
            'tel2' => '090-1234-5678',
            'sub_phone5' => '050-1111-2222',
            'remarks1' => 'Order note',
            'deleted' => '2020-01-01',
        ]);
        $customer = $this->connection->execute('SELECT * FROM customers WHERE id = ?', [$id])->fetch('assoc');
        self::assertSame('123456', $customer['client_cd']);
        self::assertSame('0312345678', $customer['tel_only']);
        self::assertSame('040', $customer['incharge4_phone']);
        self::assertSame('Note', $customer['notes1']);
        self::assertSame('Legacy book', $customer['book_name']);
        self::assertSame('09012345678', $customer['tel2_only']);
        self::assertSame('050-1111-2222', $customer['sub_phone5']);
        self::assertSame('Order note', $customer['remarks1']);
        self::assertNull($customer['deleted']);
        self::assertEquals(7, $customer['created_user_id']);
        $phoneQuery = new \ReflectionMethod(CustomersController::class, 'buildCustomerQuery');
        self::assertSame(1, $phoneQuery->invoke($this->controller('index'), ['tel_only' => '05011112222'])->count());
        // Separate branches may share the same customer code.
        self::assertGreaterThan($id, $this->addCustomer(['client_branch_code' => '002']));

        $controller = $this->controller('edit', 'PATCH', ['client_code' => '123456', 'client_branch_code' => '001', 'tel' => '050-0000-0000', 'notes4' => 'Updated', 'add' => '1']);
        self::assertStringContainsString('/contacts/add/' . $id, $controller->edit((string)$id)->getHeaderLine('Location'));
        $customer = $this->connection->execute('SELECT * FROM customers WHERE id = ?', [$id])->fetch('assoc');
        self::assertSame('05000000000', $customer['tel_only']);
        self::assertSame('Updated', $customer['notes4']);
        self::assertSame('Customer', $customer['client_name']);
        $cancel = $this->controller('edit', 'POST', ['back' => '1', 'tel' => '']);
        self::assertStringContainsString('/contacts/add/' . $id, $cancel->edit((string)$id)->getHeaderLine('Location'));
        $cancel = $this->controller('add', 'POST', ['back' => '1']);
        self::assertStringContainsString('/customers', $cancel->add()->getHeaderLine('Location'));
        self::assertSame(2, $this->controller('index')->Customers->find()->count());
    }

    public function testInvalidInputKeepsSubmittedValues(): void
    {
        $controller = $this->controller('add', 'POST', ['client_code' => '', 'tel' => '', 'notes2' => 'Keep me', 'add' => '1']);
        self::assertNull($controller->add());
        $entity = $controller->viewBuilder()->getVar('customer');
        self::assertNotEmpty($entity->getError('client_code'));
        self::assertNotEmpty($entity->getError('tel'));
        self::assertSame('Keep me', $entity->notes2);
        self::assertSame(0, $controller->Customers->find()->count());
    }

    public function testAddAcceptsLegacyTenCharacterClientCode(): void
    {
        $id = $this->addCustomer(['client_code' => '1234567890', 'client_branch_code' => '010']);
        $customer = $this->connection->execute('SELECT client_code, client_cd FROM customers WHERE id = ?', [$id])->fetch('assoc');

        self::assertSame('1234567890', (string)$customer['client_code']);
        self::assertSame('1234567890', $customer['client_cd']);
    }

    public function testSearchAndIndexLayout(): void
    {
        $address = '1288-2 Mori, Chikuma City, Nagano Prefecture';
        $id = $this->addCustomer([
            'addr1' => $address,
            'addr2' => 'Tokyo',
            'sales_person' => 'Sales',
            'sales_rep_name' => 'Legacy Sales',
            'book_name' => 'Legacy Customer Book',
            'base_name' => 'East Base',
            'sub_phone3' => '03-9999-0000',
        ]);
        $controller = $this->controller('index');
        $method = new \ReflectionMethod(CustomersController::class, 'buildCustomerQuery');
        self::assertSame(1, $method->invoke($controller, ['tel_only' => '090-1234-5678'])->count());
        self::assertSame(1, $method->invoke($controller, ['tel_only' => '090'])->count());
        self::assertSame(1, $method->invoke($controller, ['tel_only' => '99990000'])->count());
        self::assertSame(1, $method->invoke($controller, ['book_name' => 'Legacy'])->count());
        self::assertSame(1, $method->invoke($controller, ['sub_phone3' => '9999'])->count());
        self::assertSame(1, $method->invoke($controller, ['addr2_search' => 'Tokyo'])->count());
        self::assertSame(1, $method->invoke($controller, ['addr1' => 'Chikuma'])->count());
        self::assertSame(0, $method->invoke($controller, ['addr1' => 'Tokyo'])->count());
        self::assertSame(1, $method->invoke($controller, ['addr2_search' => 'Tokyo', 'sales_person' => 'Sales', 'client_code' => '234'])->count());
        self::assertSame(1, $method->invoke($controller, ['sales_person' => 'Legacy Sales'])->count());
        $controller->getRequest()->getSession()->write('Customers.search', ['client_branch_name' => 'missing']);
        $controller->index();
        $controller->getRequest()->getSession()->delete('Customers.search');
        $view = new AppView($controller->getRequest());
        $view->set($controller->viewBuilder()->getVars());
        $html = $view->render('Customers/index', false);
        self::assertStringContainsString('/contacts/add/' . $id, $html);
        self::assertStringContainsString('action="/customers/manualDialAPI/03-1234-5678"', $html);
        self::assertStringNotContainsString('/customers/view/' . $id, $html);
        self::assertStringNotContainsString('>操作<', $html);
        self::assertStringNotContainsString('callModal', $html);
        self::assertStringNotContainsString('追加検索条件', $html);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        self::assertSame(15, $xpath->query('//table[@id="dt"]/thead/tr/td')->length);
        self::assertSame(15, $xpath->query('//table[@id="dt"]/tbody/tr[1]/td')->length);
        $addressLink = $xpath->query('//td[@class="customer-address"]/a')->item(0);
        self::assertSame("1288-2 Mori, Chikuma City,\nNagano Prefecture", $addressLink->textContent);
        self::assertSame('https://www.google.co.jp/maps?q=' . rawurlencode($address), $addressLink->getAttribute('href'));
    }

    public function testIndexFallsBackToCustomerCodeWhenNamesAreMissing(): void
    {
        $this->addCustomer(['book_name' => null, 'client_name' => null]);
        $controller = $this->controller('index');
        $controller->index();

        $view = new AppView($controller->getRequest());
        $view->set($controller->viewBuilder()->getVars());
        $html = $view->render('Customers/index', false);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        self::assertSame(
            '123456',
            trim($xpath->query('//table[@id="dt"]/tbody/tr[1]/td[3]')->item(0)->textContent)
        );
    }

    public function testEditDeleteSoftDeletesOnlyLinkedHistory(): void
    {
        $id = $this->addCustomer();
        $otherId = $this->addCustomer(['client_branch_code' => '002']);
        foreach ([$id, $id, $otherId] as $customerId) {
            $this->connection->insert('contacts', ['customer_id' => $customerId]);
        }
        $controller = $this->controller('edit', 'PATCH', ['delete' => '1']);
        self::assertStringContainsString('/customers', $controller->edit((string)$id)->getHeaderLine('Location'));
        $customer = $controller->Customers->get($id);
        self::assertNotNull($customer->deleted);
        self::assertSame(7, $customer->deleted_user_id);
        $contacts = $this->connection->execute('SELECT * FROM contacts ORDER BY id')->fetchAll('assoc');
        self::assertSame($contacts[0]['deleted'], $contacts[1]['deleted']);
        self::assertNotNull($contacts[0]['deleted']);
        self::assertEquals(7, $contacts[0]['deleted_user_id']);
        self::assertNull($contacts[2]['deleted']);
        self::assertNull($controller->Customers->get($otherId)->deleted);
        $method = new \ReflectionMethod(CustomersController::class, 'buildCustomerQuery');
        self::assertSame(1, $method->invoke($controller, [])->count());
    }

    public function testSearchPersistenceClearAndExport(): void
    {
        $this->addCustomer(['book_name' => 'Export book', 'sub_phone4' => '555-1234', 'remarks1' => 'Order note']);
        $controller = $this->controller('index', 'POST', ['searching' => '1', 'Search' => ['book_name' => 'Export']]);
        $controller->index();
        self::assertSame(['book_name' => 'Export'], $controller->getRequest()->getSession()->read('Customers.search'));
        $controller->clearSession();
        self::assertNull($controller->getRequest()->getSession()->read('Customers.search'));

        $controller = $this->controller('index', 'POST', ['download' => '1']);
        $controller->index();
        self::assertCount(1, $controller->exportRows);
        self::assertEquals('123456', $controller->exportRows[0]['client_code']);
        self::assertSame('Export book', $controller->exportRows[0]['book_name']);
        self::assertSame('555-1234', $controller->exportRows[0]['sub_phone4']);
        self::assertSame('Order note', $controller->exportRows[0]['remarks1']);
        self::assertSame('Operator', $controller->exportRows[0]['created_user']);
        self::assertNull($controller->viewBuilder()->getVar('customers'));
        $csv = mb_convert_encoding((string)$controller->getResponse()->getBody(), 'UTF-8', 'SJIS-win');
        [$header, $row] = explode("\n", $csv);
        self::assertSame('キー,拠点,得意先名(帳合先名),複数口座CD,得意先CD,帳合先・納品先CD,住所1,住所2,FAX,TEL,サブ電話番号1,サブ電話番号2,サブ電話番号3,サブ電話番号4,サブ電話番号5,営業員CD,営業担当者名,締日,回収区分,ベッツダイレクト区分,備考1(受注時摘要),備考2(出荷時摘要),備考3,備考4,備考5,備考6,登録者,登録日時,更新者,更新日時', $header);
        self::assertStringStartsWith('"123456","","Export book","001"', $row);
        self::assertSame(30, count(str_getcsv($row)));
    }

    public function testDeleteRollsBackWhenLinkedHistoryUpdateFails(): void
    {
        $id = $this->addCustomer();
        $this->connection->insert('contacts', ['customer_id' => $id]);
        $this->connection->execute("CREATE TRIGGER reject_delete BEFORE UPDATE ON contacts BEGIN SELECT RAISE(ABORT, 'Test failure'); END");
        try {
            $this->controller('delete', 'POST')->delete((string)$id);
            self::fail('Expected the update failure');
        } catch (\Cake\Database\Exception\QueryException $exception) {
            self::assertStringContainsString('Test failure', $exception->getMessage());
        }
        self::assertNull($this->controller('index')->Customers->get($id)->deleted);
    }

    public function testDeleteRejectsReadOnlyUser(): void
    {
        $id = $this->addCustomer();
        $this->expectException(ForbiddenException::class);
        $this->controller('delete', 'POST', [], '0')->delete((string)$id);
    }

    public function testDeleteRejectsGetRequest(): void
    {
        $id = $this->addCustomer();
        $this->expectException(MethodNotAllowedException::class);
        $this->controller('delete')->delete((string)$id);
    }

    public function testIncomingExactPhoneMatchOpensCustomerEdit(): void
    {
        $phone = '+81-90-1234-5678';
        $this->connection->insert('customers', ['id' => 1, 'sub_phone5' => $phone]);
        $this->connection->insert('customers', ['id' => 2, 'tel' => '819012345678']);
        $this->connection->insert('customers', ['id' => 3, 'tel' => $phone, 'deleted' => '2026-01-01']);
        $controller = $this->controller('ameyo');

        $response = $controller->ameyo('1', 'session', '2', 'Queue', $phone, '03', 'legacy-id', 'recording-id');

        self::assertSame('/customers/edit/1', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        $session = $controller->getRequest()->getSession();
        self::assertSame($phone, $session->read('phone'));
        self::assertSame('recording-id', $session->read('crtObjectId'));
    }

    public function testCallHistoryJumpUsesOnlyExactTelAndTel2BeforeCreating(): void
    {
        $this->connection->insert('customers', ['id' => 1, 'tel2' => '090-1234-5678']);
        $this->connection->insert('customers', ['id' => 2, 'tel' => '09012345678', 'sub_phone1' => '03-1234-5678']);

        $response = $this->controller('jump')->jump('090-1234-5678');
        self::assertSame('/contacts/add/1', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));

        $controller = $this->controller('jump');
        $response = $controller->jump('03-1234-5678');
        self::assertSame('/contacts/add/3', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertSame('03-1234-5678', $controller->Customers->get(3)->tel);
    }

    public function testProductionCustomerSchemaSupportsAmeyoAutoCreateAndPhoneSearch(): void
    {
        $this->connection->execute('DROP TABLE customers');
        $this->connection->execute('CREATE TABLE customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT, client_code TEXT NOT NULL UNIQUE, disp_no TEXT NOT NULL,
            tel TEXT, tel_only TEXT, tel2 TEXT, sub_phone1 TEXT, sub_phone2 TEXT, sub_phone3 TEXT, sub_phone4 TEXT, sub_phone5 TEXT,
            zip_cd TEXT, addr1 TEXT, created DATETIME, created_user_id INTEGER NOT NULL, updated DATETIME, updated_user_id INTEGER, deleted DATETIME
        )');
        $controller = $this->controller('ameyo');
        $response = $controller->ameyo('1', 'session', null, null, '03-1234-5678');
        self::assertSame('/customers/edit/1', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        $customer = $controller->Customers->get(1);
        self::assertMatchesRegularExpression('/^\d{15}$/', $customer->client_code);
        self::assertSame('0312345678', $customer->tel_only);

        $search = new \ReflectionMethod(CustomersController::class, 'buildCustomerQuery');
        self::assertSame(1, $search->invoke($controller, ['tel_only' => '03-1234'])->count());
    }

    public function testCallHistoryJumpPaginatesLegacyContainsMatchesAtOneHundred(): void
    {
        $this->connection->insert('customers', ['id' => 1, 'tel' => '031234']);
        $this->connection->insert('customers', ['id' => 2, 'tel2' => '031234']);
        $this->connection->insert('customers', ['id' => 3, 'sub_phone1' => '031234']);
        $this->connection->insert('customers', ['id' => 4, 'tel' => '031234', 'deleted' => '2026-01-01']);
        for ($id = 5; $id <= 104; $id++) {
            $this->connection->insert('customers', ['id' => $id, 'tel' => '00312349']);
        }
        $controller = $this->controller('jump');

        self::assertNull($controller->jump('031234'));
        $customers = $controller->viewBuilder()->getVar('customers');
        self::assertCount(100, $customers);
        self::assertSame(102, $customers->totalCount());
        self::assertSame('index', $controller->viewBuilder()->getTemplate());
    }

    public function testCustomerDetailBookmarkShowsOnlyActiveContactHistory(): void
    {
        $id = $this->addCustomer(['book_name' => 'Legacy customer']);
        $this->connection->insert('contacts', ['customer_id' => $id, 'summary' => 'Active history', 'updated' => '2026-01-01']);
        $this->connection->insert('contacts', ['customer_id' => $id, 'summary' => 'Deleted history', 'deleted' => '2026-01-02']);
        $controller = $this->controller('view');
        $controller->view((string)$id);

        $view = new AppView($controller->getRequest());
        $view->set($controller->viewBuilder()->getVars());
        $html = $view->render('Customers/view', false);
        self::assertStringContainsString('Legacy customer', $html);
        self::assertStringContainsString('Active history', $html);
        self::assertStringNotContainsString('Deleted history', $html);
    }

    public function testIncomingUnknownOrUnregisteredPhoneCreatesCustomerAndOpensEdit(): void
    {
        $this->connection->insert('customers', ['id' => 1, 'tel' => 'unknown']);
        $phones = ['unknown', 'anonymous', '03-1234-5678'];
        foreach ($phones as $index => $phone) {
            $controller = $this->controller('ameyo');
            $response = $controller->ameyo('1', 'session', null, null, $phone);

            $id = $index + 2;
            self::assertSame('/customers/edit/' . $id, parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            self::assertSame($phone, $controller->Customers->get($id)->tel);
        }
    }

    public function testIncomingMultipleExactMatchesShowActiveSubstringMatches(): void
    {
        $this->connection->insert('customers', ['id' => 1, 'tel' => '031234']);
        $this->connection->insert('customers', ['id' => 2, 'sub_phone1' => '031234']);
        $this->connection->insert('customers', ['id' => 3, 'sub_phone4' => '00312349']);
        $this->connection->insert('customers', ['id' => 4, 'tel' => '031234', 'deleted' => '2026-01-01']);
        $controller = $this->controller('ameyo');

        self::assertNull($controller->ameyo('1', 'session', null, null, '031234'));

        $view = $controller->viewBuilder();
        self::assertSame('index', $view->getTemplate());
        $customers = $view->getVar('customers');
        self::assertEqualsCanonicalizing([1, 2, 3], $customers->items()->extract('id')->toList());
    }

    public function testIncomingAutoCreateFailureShowsErrorInsteadOfRedirectingToCustomerZero(): void
    {
        foreach (['unknown', 'anonymous', '0312345678'] as $phone) {
            $controller = $this->controller('ameyo');
            $controller->getRequest()->getSession()->delete('Flash.flash');
            $customers = $this->getMockBuilder(CustomersTable::class)
                ->setConstructorArgs([['connection' => $this->connection, 'alias' => 'Customers']])
                ->onlyMethods(['save'])
                ->getMock();
            $customers->expects(self::once())->method('save')->willReturn(false);
            $controller->Customers = $customers;

            $response = $controller->ameyo('1', 'session', null, null, $phone);

            self::assertSame('/customers', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            $messages = $controller->getRequest()->getSession()->read('Flash.flash');
            self::assertCount(1, $messages);
            self::assertSame('flash/error', $messages[0]['element']);
            self::assertSame(0, (int)$this->connection->execute('SELECT COUNT(*) AS total FROM customers')->fetch('assoc')['total']);
        }
    }

    public function testAddAndEditFormsMatchOriginalFields(): void
    {
        $id = $this->addCustomer();
        $legacyFields = [
            'client_code', 'base_name', 'book_name', 'client_branch_code', 'reserve1', 'reserve2',
            'addr1', 'addr2', 'fax', 'tel', 'sales_staff_cd', 'sub_phone1', 'sales_rep_name',
            'sub_phone2', 'closing_date', 'sub_phone3', 'collection_cls', 'sub_phone4',
            'direct_cls', 'sub_phone5', 'remarks1', 'remarks2', 'notes3', 'notes4', 'remarks5', 'remarks6',
        ];
        $fieldsByAction = [
            'add' => array_merge($legacyFields, ['back', 'add']),
            'edit' => array_merge($legacyFields, ['back', 'add']),
        ];
        foreach (['add', 'edit'] as $action) {
            foreach (['0', '9'] as $auth) {
                $controller = $this->controller($action, 'GET', [], $auth);
                $action === 'edit' ? $controller->edit((string)$id) : $controller->add();
                $view = new AppView($controller->getRequest());
                $view->set($controller->viewBuilder()->getVars());
                $html = $view->render('Customers/' . $action, false);
                foreach ($fieldsByAction[$action] as $field) {
                    self::assertStringContainsString('name="' . $field . '"', $html);
                }
                self::assertSame($action === 'edit' && $auth === '9', str_contains($html, 'name="delete"'));
                self::assertStringContainsString($action === 'edit' ? '#ff8c00' : '#4169E1', $html);
                self::assertStringNotContainsString('name="email"', $html);
                self::assertStringNotContainsString('name="client_cd"', $html);
                self::assertStringContainsString('novalidate', $html);
                self::assertStringContainsString('キー＊', $html);

                $dom = new \DOMDocument();
                @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
                $xpath = new \DOMXPath($dom);
                $controls = $xpath->query('//form[@id="customerForm"]//table//input[not(@type="hidden")] | //form[@id="customerForm"]//table//textarea');
                $actualFields = [];
                foreach ($controls as $control) {
                    $actualFields[] = $control->getAttribute('name');
                }
                self::assertSame(array_slice($fieldsByAction[$action], 0, 26), $actualFields);
                foreach (['client_name', 'client_branch_name', 'incharge4_name', 'addr3', 'deadline', 'tel2'] as $nonLegacyField) {
                    self::assertStringNotContainsString('name="' . $nonLegacyField . '"', $html);
                }
            }
        }
    }
}

class CustomerFlowHarness extends CustomersController
{
    public CustomersTable $Customers;
    public string $auth = '9';
    public array $exportRows = [];

    public function initialize(): void
    {
        $connection = ConnectionManager::get('customers_flow');
        $this->Customers = new CustomersTable(['connection' => $connection, 'alias' => 'Customers']);
        $this->Contacts = new Table(['connection' => $connection, 'table' => 'contacts']);
        $this->Users = new Table(['connection' => $connection, 'table' => 'users']);
        $this->loadComponent('Flash');
    }

    protected function getCurrentUser(): ?User
    {
        return new User(['id' => 7, 'auth' => $this->auth]);
    }

    protected function setListValue(): void
    {
        $this->set('opeOpt', $this->Users->find('list', keyField: 'id', valueField: 'user_name')->toArray());
    }

    public function csv($results = null)
    {
        $this->exportRows = $results;
        parent::csv($results);
    }
}
