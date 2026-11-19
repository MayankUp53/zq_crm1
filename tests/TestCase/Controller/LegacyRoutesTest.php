<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\CallbacksController;
use App\Controller\MtPhonelistsController;
use Cake\Controller\Component;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\ORM\Table;
use Cake\Routing\Router;
use PHPUnit\Framework\TestCase;

class LegacyRoutesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        Router::reload();
        (require CONFIG . 'routes.php')(Router::createRouteBuilder('/'));
    }

    protected function tearDown(): void
    {
        Router::reload();
        $this->connection->getDriver()->disconnect();
        parent::tearDown();
    }

    private function request(string $path, string $method = 'GET', array $data = []): ServerRequest
    {
        $request = (new ServerRequest(['url' => $path, 'session' => new Session()]))
            ->withMethod($method)->withParsedBody($data);
        $request = $request->withAttribute('params', Router::parseRequest($request));
        Router::setRequest($request);

        return $request;
    }

    private function legacyNavigation(string $path, string $site): ServerRequest
    {
        return $this->request($path)
            ->withHeader('Sec-Fetch-Site', $site)
            ->withHeader('Sec-Fetch-Mode', 'navigate')
            ->withHeader('Sec-Fetch-Dest', 'document')
            ->withHeader('Sec-Fetch-User', '?1')
            ->withAttribute('csrfToken', 'test-token');
    }

    public function testRootAndCustomerBookmarkUseCustomerController(): void
    {
        $root = $this->request('/');
        self::assertSame('Customers', $root->getParam('controller'));
        self::assertSame('index', $root->getParam('action'));
        $bookmark = $this->request('/customers/view/7');
        self::assertSame('view', $bookmark->getParam('action'));
        self::assertSame(['7'], $bookmark->getParam('pass'));
    }

    public function testLegacyCallbackEditAndDeleteUseExistingReservation(): void
    {
        $this->connection->execute('CREATE TABLE callbacks (id INTEGER PRIMARY KEY, customer_id INTEGER, phone TEXT, callback_datetime DATETIME, is_done INTEGER, remark TEXT, updated DATETIME, updated_user_id INTEGER, deleted DATETIME, deleted_user_id INTEGER)');
        $this->connection->insert('callbacks', ['id' => 7, 'customer_id' => 1, 'phone' => '0312345678']);
        $this->connection->execute('CREATE TABLE customers (id INTEGER PRIMARY KEY, book_name TEXT, deleted DATETIME)');
        $this->connection->insert('customers', ['id' => 1, 'book_name' => 'Example company']);
        $this->connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, user_name TEXT, deleted DATETIME)');

        $request = $this->request('/contacts/callback_edit/7');
        self::assertSame('Callbacks', $request->getParam('controller'));
        $controller = new LegacyCallbacksHarness($request, $this->connection);
        $controller->edit(...$request->getParam('pass'));
        $html = (string)$controller->render('edit')->getBody();
        self::assertStringContainsString('action="/contacts/callback_edit/7"', $html);
        self::assertStringContainsString('Example company', $html);

        $request = $this->request('/contacts/callback_edit', 'POST', ['data' => ['Contact' => ['id' => '7', 'memo' => 'Legacy memo']]]);
        $controller = new LegacyCallbacksHarness($request, $this->connection);
        $response = $controller->edit();
        self::assertSame('/homes', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertSame('Legacy memo', $this->connection->execute('SELECT remark FROM callbacks WHERE id = 7')->fetchColumn(0));

        $path = '/contacts/callback_delete/7';
        $request = $this->legacyNavigation($path, 'none');
        $controller = new LegacyCallbacksHarness($request, $this->connection);
        self::assertNull($controller->delete(...$request->getParam('pass')));
        self::assertNull($this->connection->execute('SELECT deleted FROM callbacks WHERE id = 7')->fetch('assoc')['deleted']);
        $html = (string)$controller->render()->getBody();
        self::assertStringContainsString('action="' . $path . '"', $html);
        self::assertStringContainsString('name="_csrfToken" value="test-token"', $html);
        self::assertStringContainsString("getElementById('legacy-action').submit()", $html);

        $request = $this->request($path, 'POST');
        $controller = new LegacyCallbacksHarness($request, $this->connection);
        $response = $controller->delete(...$request->getParam('pass'));
        self::assertSame('/homes', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertNotNull($this->connection->execute('SELECT deleted FROM callbacks WHERE id = 7')->fetch('assoc')['deleted']);
    }

    public function testLegacyPhoneListAutoSubmitsAndAppliesOrderAtomically(): void
    {
        $this->connection->execute('CREATE TABLE mt_phonelists (id INTEGER PRIMARY KEY, disp_cd INTEGER UNIQUE, deleted DATETIME)');
        foreach ([1, 2, 3] as $id) {
            $this->connection->insert('mt_phonelists', ['id' => $id, 'disp_cd' => $id]);
        }

        $path = '/mt_phonelists/sortupdate/3-1-2-';
        $request = $this->legacyNavigation($path, 'same-origin');
        self::assertSame('MtPhonelists', $request->getParam('controller'));
        $controller = new LegacyPhoneListHarness($request, $this->connection);
        self::assertNull($controller->sortupdate(...$request->getParam('pass')));
        self::assertSame([1, 2, 3], $this->phoneOrder());
        $html = (string)$controller->render()->getBody();
        self::assertStringContainsString('action="' . $path . '"', $html);
        self::assertStringContainsString("getElementById('legacy-action').submit()", $html);

        $request = $this->request($path, 'POST');
        $controller = new LegacyPhoneListHarness($request, $this->connection);
        $response = $controller->sortupdate(...$request->getParam('pass'));
        self::assertSame('/mt-phonelists', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertSame([3, 1, 2], $this->phoneOrder());

        $request = $this->request('/mt-phonelists/update-order', 'POST', ['orderData' => [['id' => 2]]]);
        $controller = new LegacyPhoneListHarness($request, $this->connection);
        self::assertSame(500, $controller->updateOrder()->getStatusCode());
        self::assertSame([3, 1, 2], $this->phoneOrder());
    }

    public function testCrossSiteLegacyDeleteDoesNotRenderAnAutoSubmittingForm(): void
    {
        $request = $this->legacyNavigation('/contacts/callback_delete/7', 'cross-site');
        $controller = new LegacyCallbacksHarness($request, $this->connection);
        $this->expectException(ForbiddenException::class);
        $controller->delete(...$request->getParam('pass'));
    }

    private function phoneOrder(): array
    {
        return array_column($this->connection->execute('SELECT id FROM mt_phonelists ORDER BY disp_cd')->fetchAll('assoc'), 'id');
    }
}

class LegacyCallbacksHarness extends CallbacksController
{
    public function __construct(ServerRequest $request, private Connection $connection)
    {
        parent::__construct($request, 'Callbacks');
    }

    public function initialize(): void
    {
        $this->loadComponent('Flash');
        $this->loadComponent('CallbackNotification', ['className' => LegacyCallbackNotificationHarness::class]);
        $this->Callbacks = new Table(['connection' => $this->connection, 'table' => 'callbacks']);
        $this->Customers = new Table(['connection' => $this->connection, 'table' => 'customers']);
        $this->Users = new Table(['connection' => $this->connection, 'table' => 'users']);
        $this->viewBuilder()->disableAutoLayout();
    }

    protected function getCurrentUser(): ?\App\Model\Entity\User
    {
        return null;
    }
}

class LegacyCallbackNotificationHarness extends Component
{
    public function getNumOfWarning(): int
    {
        return 0;
    }
}

class LegacyPhoneListHarness extends MtPhonelistsController
{
    public function __construct(ServerRequest $request, private Connection $connection)
    {
        parent::__construct($request, 'MtPhonelists');
    }

    public function initialize(): void
    {
        $this->loadComponent('Flash');
        $this->MtPhonelists = new Table(['connection' => $this->connection, 'table' => 'mt_phonelists']);
        $this->viewBuilder()->disableAutoLayout();
    }
}
