<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\HomesController;
use App\Model\Table\CoreSettingsTable;
use App\View\AppView;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\ORM\Table;
use Cake\Routing\Router;
use PHPUnit\Framework\TestCase;

class HomesIndexTest extends TestCase
{
    public function testCallHistoryUsesConfiguredCampaignWhileDialUsesSessionCampaign(): void
    {
        $connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $url = 'http://127.0.0.1:1/history';
        $connection->execute('CREATE TABLE core_settings (id INTEGER PRIMARY KEY, ch_api_url TEXT, ccid TEXT, call_history_campaign_id INTEGER, limit_value INTEGER, deleted DATETIME)');
        $connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, user_name TEXT, deleted DATETIME)');
        $connection->execute('CREATE TABLE mt_cids (id INTEGER PRIMARY KEY, cid TEXT, item_name TEXT, campaign_id INTEGER, disp_cd INTEGER, deleted DATETIME)');
        $connection->insert('core_settings', ['ch_api_url' => $url, 'ccid' => 'test-ccid', 'call_history_campaign_id' => 37, 'limit_value' => 80]);
        $connection->insert('mt_cids', ['cid' => '05000000001', 'item_name' => 'Session caller ID', 'campaign_id' => 52]);
        $connection->insert('mt_cids', ['cid' => '05000000002', 'item_name' => 'History caller ID', 'campaign_id' => 37]);
        $call = ['call_id' => 'call-123', 'crt_object_id' => 'recording-456', 'phone' => '0312345678', 'srcphone' => '0312345678'];
        $query = http_build_query([[
            'ccid' => 'test-ccid', 'campaign_id' => 37, 'limit' => 80, 'userid' => '',
            'date_from' => date('Y-m-d', strtotime('-1 month')), 'date_to' => date('Y-m-d'),
        ]], '', '&', PHP_QUERY_RFC3986);
        Client::addMockResponse('GET', $url . '?' . $query, new Response([], json_encode([$call])));

        Router::reload();
        (require CONFIG . 'routes.php')(Router::createRouteBuilder('/'));
        $session = new Session();
        $session->write('campaign', '52');
        try {
            $request = (new ServerRequest(['url' => '/homes', 'session' => $session]))->withMethod('GET');
            $request = $request->withAttribute('params', Router::parseRequest($request));
            Router::setRequest($request);
            $controller = new HomesIndexHarness($request, $connection);
            $controller->index();

            $vars = $controller->viewBuilder()->getVars();
            self::assertSame([$call], $vars['response']);
            self::assertSame(80, $vars['limit']);
            self::assertSame(['05000000001' => 'Session caller ID'], $vars['cidOpt']);

            $view = new AppView($request);
            $view->set($vars);
            $html = $view->render('Homes/index', false);
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            self::assertSame(1, $xpath->query('//a[@href="/homes/play/call-123/recording-456"][@onclick]')->length);
            self::assertStringContainsString('pageLength: 20', $view->fetch('script'));
            self::assertStringContainsString("extend: 'excelHtml5'", $view->fetch('script'));
        } finally {
            $session->delete('campaign');
            Client::clearMockResponses();
            Router::reload();
            $connection->getDriver()->disconnect();
        }
    }
}

class HomesIndexHarness extends HomesController
{
    public function __construct(ServerRequest $request, private Connection $connection)
    {
        parent::__construct($request, 'Homes');
    }

    public function initialize(): void
    {
        $this->CoreSettings = new CoreSettingsTable(['connection' => $this->connection]);
        $this->Users = new Table(['connection' => $this->connection, 'table' => 'users']);
        $this->MtCids = new Table(['connection' => $this->connection, 'table' => 'mt_cids']);
    }
}
