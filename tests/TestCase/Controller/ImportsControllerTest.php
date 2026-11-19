<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\ImportsController;
use App\Model\Entity\User;
use App\Model\Table\CustomersTable;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\Routing\Router;
use Laminas\Diactoros\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class ImportsControllerTest extends TestCase
{
    public function testLegacyCustomerTemplateRowsMapIntoCurrentCustomerFields(): void
    {
        $controller = (new \ReflectionClass(ImportsController::class))->newInstanceWithoutConstructor();
        $mapRow = new \ReflectionMethod(ImportsController::class, 'mapLegacyCustomerRow');

        $data = $mapRow->invoke($controller, [
            'K001',
            'East base',
            'Legacy book',
            '002',
            'C001',
            'D001',
            'Tokyo',
            'Chiyoda',
            '03-1234-5678',
            '090-1111-2222',
            'sub-1',
            'sub-2',
            'sub-3',
            'sub-4',
            'sub-5',
            'S001',
            'Sales rep',
            '202501',
            'Monthly',
            'Direct',
            'Order remark',
            'Shipping remark',
            'Note 3',
            'Note 4',
            'Remark 5',
            'Remark 6',
        ]);

        self::assertSame('K001', $data['client_code']);
        self::assertSame('Legacy book', $data['book_name']);
        self::assertSame('East base', $data['base_name']);
        self::assertSame('C001', $data['reserve1']);
        self::assertSame('D001', $data['reserve2']);
        self::assertSame('09011112222', $data['tel']);
        self::assertSame('09011112222', $data['tel_only']);
        self::assertSame('sub-5', $data['sub_phone5']);
        self::assertSame('Order remark', $data['remarks1']);
        self::assertSame('Shipping remark', $data['remarks2']);
        self::assertSame('Note 3', $data['notes3']);
        self::assertSame('Remark 6', $data['remarks6']);
    }

    public function testLegacyWorkbookUpdatesAndReactivatesOnlyTheMatchingBranch(): void
    {
        $connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $connection->execute('CREATE TABLE customers (
            id INTEGER PRIMARY KEY, client_code TEXT, client_cd TEXT, client_branch_code TEXT,
            book_name TEXT, tel TEXT, deleted DATETIME, deleted_user_id INTEGER,
            created DATETIME, created_user_id INTEGER, updated DATETIME, updated_user_id INTEGER,
            UNIQUE(client_cd, client_branch_code)
        )');
        $connection->insert('customers', ['id' => 1, 'client_code' => 'K001', 'client_cd' => 'K001', 'client_branch_code' => 'A', 'book_name' => 'Other branch']);
        $connection->insert('customers', ['id' => 2, 'client_code' => 'K001', 'client_cd' => 'K001', 'client_branch_code' => 'B', 'book_name' => 'Old name', 'deleted' => '2020-01-01', 'deleted_user_id' => 5, 'created' => '2020-01-01', 'created_user_id' => 5]);
        $path = tempnam(TMP, 'legacy-import-');
        $spreadsheet = new Spreadsheet();
        $session = new Session();
        $session->delete('Flash.flash');
        try {
            $headers = (new \ReflectionMethod(ImportsController::class, 'getLegacyCsvTemplate'))
                ->invoke((new \ReflectionClass(ImportsController::class))->newInstanceWithoutConstructor());
            $spreadsheet->getSheet(0)->fromArray([
                $headers,
                ['K001', '', 'Updated branch B', 'B', '', '', '', '', '', '03-1234-5678'],
                ['K001', '', 'New branch C', 'C'],
            ]);
            $spreadsheet->createSheet();
            $spreadsheet->setActiveSheetIndex(1);
            (new Xlsx($spreadsheet))->save($path);

            Router::reload();
            (require CONFIG . 'routes.php')(Router::createRouteBuilder('/'));
            $request = (new ServerRequest(['session' => $session]))
                ->withMethod('POST')
                ->withAttribute('params', ['controller' => 'Imports', 'action' => 'import', 'plugin' => null, 'pass' => []])
                ->withParsedBody(['pattern' => '1', 'result' => new UploadedFile($path, filesize($path), UPLOAD_ERR_OK, 'temp.xlsx')]);
            Router::setRequest($request);

            foreach ([1, 2] as $run) {
                $controller = new LegacyImportHarness($request, $connection);
                $response = $controller->import();
                self::assertSame('/settings', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
                self::assertSame('2件の登録＆更新が完了しました。', $session->read('Flash.flash.' . ($run - 1) . '.message'));
            }

            $rows = $connection->execute('SELECT * FROM customers ORDER BY id')->fetchAll('assoc');
            self::assertSame(['Other branch', 'Updated branch B', 'New branch C'], array_column($rows, 'book_name'));
            self::assertSame('0312345678', $rows[1]['tel']);
            self::assertNull($rows[1]['deleted']);
            self::assertSame(5, (int)$rows[1]['deleted_user_id']);
            self::assertSame(7, (int)$rows[1]['created_user_id']);
        } finally {
            $session->delete('Flash.flash');
            Router::reload();
            $spreadsheet->disconnectWorksheets();
            unlink($path);
            $connection->getDriver()->disconnect();
        }
    }

    public function testProductionImportUsesUniqueClientCodeAndUpdatesBranchOnTheSameRecord(): void
    {
        $connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $connection->execute('CREATE TABLE customers (
            id INTEGER PRIMARY KEY, disp_no TEXT NOT NULL DEFAULT \'9999\', client_code TEXT NOT NULL UNIQUE,
            client_branch_code TEXT, book_name TEXT, tel TEXT, tel_only TEXT, deleted DATETIME,
            created DATETIME, created_user_id INTEGER NOT NULL, updated DATETIME, updated_user_id INTEGER
        )');
        $connection->insert('customers', ['id' => 4, 'client_code' => '00123', 'client_branch_code' => 'OLD', 'created_user_id' => 5, 'deleted' => '2020-01-01']);
        $path = tempnam(TMP, 'production-import-');
        $spreadsheet = new Spreadsheet();
        $session = new Session();
        $session->delete('Flash.flash');
        try {
            $headers = (new \ReflectionMethod(ImportsController::class, 'getLegacyCsvTemplate'))
                ->invoke((new \ReflectionClass(ImportsController::class))->newInstanceWithoutConstructor());
            $spreadsheet->getSheet(0)->fromArray([
                $headers,
                ['00123', '', 'Updated customer', 'NEW', '', '', '', '', '', '03-1234-5678'],
                ['00124', '', 'New customer', 'NEW'],
            ]);
            (new Xlsx($spreadsheet))->save($path);

            Router::reload();
            (require CONFIG . 'routes.php')(Router::createRouteBuilder('/'));
            $request = (new ServerRequest(['session' => $session]))
                ->withMethod('POST')
                ->withAttribute('params', ['controller' => 'Imports', 'action' => 'import', 'plugin' => null, 'pass' => []])
                ->withParsedBody(['result' => new UploadedFile($path, filesize($path), UPLOAD_ERR_OK, 'temp.xlsx')]);
            Router::setRequest($request);
            (new LegacyImportHarness($request, $connection))->import();

            $rows = $connection->execute('SELECT * FROM customers ORDER BY id')->fetchAll('assoc');
            self::assertCount(2, $rows);
            self::assertSame(4, $rows[0]['id']);
            self::assertSame('00123', $rows[0]['client_code']);
            self::assertSame('NEW', $rows[0]['client_branch_code']);
            self::assertSame('Updated customer', $rows[0]['book_name']);
            self::assertNull($rows[0]['deleted']);
            self::assertSame('00124', $rows[1]['client_code']);
            self::assertSame('2件の登録＆更新が完了しました。', $session->read('Flash.flash.0.message'));
        } finally {
            $session->delete('Flash.flash');
            Router::reload();
            $spreadsheet->disconnectWorksheets();
            unlink($path);
            $connection->getDriver()->disconnect();
        }
    }
}

class LegacyImportHarness extends ImportsController
{
    public function __construct(ServerRequest $request, private Connection $connection)
    {
        parent::__construct($request, 'Imports');
    }

    public function initialize(): void
    {
        $this->Customers = new CustomersTable(['connection' => $this->connection]);
        $this->loadComponent('Flash');
    }

    protected function getCurrentUser(): ?User
    {
        return new User(['id' => 7]);
    }
}
