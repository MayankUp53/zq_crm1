<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\CoreSettingsController;
use App\Controller\HomesController;
use App\Model\Entity\User;
use App\Model\Table\CoreSettingsTable;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\ORM\Table;
use Cake\Routing\Router;
use PHPUnit\Framework\TestCase;

class ManualDialTest extends TestCase
{
    private const DIAL_URL = 'http://127.0.0.1:1/command?command=manual-dial&data=';
    private const PHONE = '+81-90-1234-5678';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $this->connection->execute('CREATE TABLE core_settings (id INTEGER PRIMARY KEY, dial_api_url TEXT, customer_contact_dial_caller_id TEXT, updated DATETIME, updated_user_id INTEGER, deleted DATETIME)');
        $this->connection->insert('core_settings', ['dial_api_url' => self::DIAL_URL, 'customer_contact_dial_caller_id' => '05033106255']);
        Router::reload();
        (require CONFIG . 'routes.php')(Router::createRouteBuilder('/'));
    }

    protected function tearDown(): void
    {
        Client::clearMockResponses();
        Router::reload();
        $this->connection->getDriver()->disconnect();
        parent::tearDown();
    }

    private function controller(string $path, array $data = [], bool $hasSession = true, string $method = 'POST'): ManualDialHarness
    {
        $session = new Session();
        $session->delete('Flash.flash');
        $session->write([
            'campaign' => $hasSession ? '29' : null,
            'sessionId' => $hasSession ? 'test-session' : null,
        ]);
        $request = (new ServerRequest(['url' => $path, 'session' => $session]))
            ->withMethod($method)
            ->withParsedBody($data + ['tel' => self::PHONE]);
        $request = $request->withAttribute('params', Router::parseRequest($request));
        Router::setRequest($request);

        return new ManualDialHarness($request, $this->connection);
    }

    private function expectDial(string $callerId): void
    {
        $payload = [
            'campaignId' => '29',
            'sessionId' => 'test-session',
            'command-request-id' => '0',
            'phone' => self::PHONE,
            'additionalParams' => ['caller_id' => $callerId],
        ];
        Client::addMockResponse('GET', self::DIAL_URL . urlencode(json_encode($payload)), new Response([], '{"reason":"success"}'));
    }

    public function testOneClickDialUsesCallerIdSavedInAdvancedSettings(): void
    {
        $settingsRequest = (new ServerRequest(['url' => '/core-settings/edit/1', 'session' => new Session()]))
            ->withMethod('POST')
            ->withParsedBody(['customer_contact_dial_caller_id' => '05000000003']);
        $settingsRequest = $settingsRequest->withAttribute('params', Router::parseRequest($settingsRequest));
        Router::setRequest($settingsRequest);
        $settings = new DialCoreSettingsHarness($settingsRequest, $this->connection);
        $response = $settings->edit('1');
        self::assertSame('/core-settings', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));

        $this->expectDial('05000000003');
        foreach (['customers', 'contacts'] as $source) {
            $controller = $this->controller('/' . $source . '/manualDialAPI/' . rawurlencode(self::PHONE), [
                'cid' => 'ignored', 'tel' => 'ignored',
            ]);
            $response = $controller->manualDialApi();

            self::assertSame('/' . $source, parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            self::assertNull($controller->getRequest()->getSession()->read('Flash.flash'));
        }
    }

    public function testLegacyDialBookmarksAutoSubmitWithoutConfirmation(): void
    {
        foreach (['customers', 'contacts'] as $source) {
            $path = '/' . $source . '/manualDialAPI/' . rawurlencode(self::PHONE);
            $controller = $this->controller($path, method: 'GET');
            $controller->setRequest($controller->getRequest()
                ->withHeader('Sec-Fetch-Site', 'none')
                ->withHeader('Sec-Fetch-Mode', 'navigate')
                ->withHeader('Sec-Fetch-Dest', 'document')
                ->withHeader('Sec-Fetch-User', '?1')
                ->withAttribute('csrfToken', 'test-token'));

            self::assertNull($controller->manualDialApi());
            $html = (string)$controller->render()->getBody();
            self::assertStringContainsString('action="' . $path . '"', $html);
            self::assertStringContainsString('name="_csrfToken" value="test-token"', $html);
            self::assertStringContainsString("getElementById('legacy-action').submit()", $html);

            $this->expectDial('05033106255');
            $controller = $this->controller($path);
            $response = $controller->manualDialApi();
            self::assertSame('/' . $source, parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            self::assertNull($controller->getRequest()->getSession()->read('Flash.flash'));
        }
    }

    public function testCrossSiteDialUrlDoesNotAutomaticallySubmit(): void
    {
        $controller = $this->controller('/contacts/manualDialAPI/' . rawurlencode(self::PHONE), method: 'GET');
        $controller->setRequest($controller->getRequest()
            ->withHeader('Sec-Fetch-Site', 'cross-site')
            ->withHeader('Sec-Fetch-Mode', 'navigate')
            ->withHeader('Sec-Fetch-Dest', 'document')
            ->withHeader('Sec-Fetch-User', '?1'));

        $this->expectException(ForbiddenException::class);
        $controller->manualDialApi();
    }

    public function testPreviousPostRouteStillAcceptsTheNumberInTheBody(): void
    {
        $this->expectDial('05033106255');
        $controller = $this->controller('/contacts/manualDialAPI');
        $response = $controller->manualDialApi();

        self::assertSame('/contacts', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertNull($controller->getRequest()->getSession()->read('Flash.flash'));
    }

    public function testDashboardUsesCallerIdFromSessionCampaign(): void
    {
        $this->connection->update('core_settings', ['customer_contact_dial_caller_id' => null], ['id' => 1]);
        $this->connection->execute('CREATE TABLE mt_cids (id INTEGER PRIMARY KEY, cid TEXT, campaign_id INTEGER, deleted DATETIME)');
        $this->connection->insert('mt_cids', ['cid' => '05000000001', 'campaign_id' => 52]);
        $this->connection->insert('mt_cids', ['cid' => '05000000001', 'campaign_id' => 29]);
        $this->connection->insert('mt_cids', ['cid' => '05000000002', 'campaign_id' => 52]);
        $this->expectDial('05000000001');
        $controller = $this->controller('/homes/manualDialAPI', ['cid' => '05000000001']);
        $response = $controller->manualDialApi();

        self::assertSame('/homes', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertNull($controller->getRequest()->getSession()->read('Flash.flash'));

        $controller = $this->controller('/homes/manualDialAPI', ['cid' => '05000000002']);
        $controller->manualDialApi();
        self::assertSame('このキャンペーンの通知番号を選択してください。', $controller->getRequest()->getSession()->read('Flash.flash.0.message'));
    }

    public function testOneClickDialRequiresAmeyoSession(): void
    {
        $controller = $this->controller('/contacts/manualDialAPI', hasSession: false);
        $response = $controller->manualDialApi();

        self::assertSame('/contacts', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertSame('AmeyoJのセッションが正常に取得できませんでした。操作をやり直してください。', $controller->getRequest()->getSession()->read('Flash.flash.0.message'));
    }

    public function testOneClickDialRequiresCallerIdInCoreSettings(): void
    {
        $this->connection->update('core_settings', ['customer_contact_dial_caller_id' => null], ['id' => 1]);
        $controller = $this->controller('/customers/manualDialAPI/' . rawurlencode(self::PHONE));
        $response = $controller->manualDialApi();

        self::assertSame('/customers', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
        self::assertSame('顧客・対応履歴から発信するための通知番号が設定されていません。', $controller->getRequest()->getSession()->read('Flash.flash.0.message'));
    }
}

class DialCoreSettingsHarness extends CoreSettingsController
{
    public function __construct(ServerRequest $request, private Connection $connection)
    {
        parent::__construct($request, 'CoreSettings');
    }

    public function initialize(): void
    {
        $this->loadComponent('Flash');
        $this->CoreSettings = new CoreSettingsTable(['connection' => $this->connection]);
    }

    protected function getCurrentUser(): ?User
    {
        return new User(['id' => 7]);
    }
}

class ManualDialHarness extends HomesController
{
    public function __construct(ServerRequest $request, private Connection $connection)
    {
        parent::__construct($request, 'Homes');
    }

    public function initialize(): void
    {
        $this->loadComponent('Flash');
        $this->CoreSettings = new Table(['connection' => $this->connection, 'table' => 'core_settings']);
        $this->MtCids = new Table(['connection' => $this->connection, 'table' => 'mt_cids']);
    }
}
