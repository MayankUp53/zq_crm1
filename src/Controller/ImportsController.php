<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Exception\QueryException;
use Cake\Database\Schema\TableSchema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Imports Controller
 *
 * Customer data import.
 */
class ImportsController extends AppController
{
    protected $Customers;
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('App.CallbackNotification');
        $this->Customers = $this->fetchTable('Customers');
    }

    public function index()
    {
        $warning = $this->CallbackNotification->getNumOfWarning();
        $this->set('importNote', __('CSV / Excel / ODS形式でアップロードできます。1行目はヘッダーとして読み取られます。'));
        $this->set(compact('warning'));
    }

    /**
     * Download import template
     */
    public function template()
    {
        return $this->downloadTemplate($this->getCsvTemplate());
    }

    public function legacyTemplate()
    {
        return $this->downloadTemplate($this->getLegacyCsvTemplate(), 'temp.xlsx');
    }

    private function downloadTemplate(array $headers, string $filename = '顧客取込雛形.xlsx')
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        $sheet->freezePane('A2');
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
        $this->response = $this->response
            ->withType('xlsx')
            ->withDownload($filename);
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        $this->response = $this->response->withStringBody($content);
        return $this->response;
    }

    public function import()
    {
        if (!$this->request->is('post')) {
            return $this->redirect(['action' => 'index']);
        }

        $file = $this->request->getData('result');

        if (empty($file) || $file->getError() !== UPLOAD_ERR_OK) {
            $this->Flash->error(__('ファイルのアップロードに失敗しました。'));
            return $this->redirect(['action' => 'index']);
        }

        $filePath = $file->getStream()->getMetadata('uri');
        $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'xlsx', 'xls', 'ods'])) {
            $this->Flash->error(__('CSV / Excel / ODS ファイルのみ対応しています。'));
            return $this->redirect(['action' => 'index']);
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            $this->Flash->error(__('取込ファイルを読み込めませんでした。ファイル形式または内容を確認してください。'));
            return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
        }
        $sheet = $spreadsheet->getSheet(0);

        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            $this->Flash->error(__('ファイルが空です。'));
            return $this->redirect(['action' => 'index']);
        }

        $header = array_map(static function ($value): string {
            return trim((string)preg_replace('/^\xEF\xBB\xBF/u', '', $value ?? ''));
        }, $rows[0]);
        $template = array_map('trim', $this->getCsvTemplate());
        $legacyTemplate = array_map('trim', $this->getLegacyCsvTemplate());

        $blankHeader = count(array_filter($header, static fn($value) => trim((string)$value) !== '')) === 0;
        $isLegacyImport = $header === $legacyTemplate
            || ($blankHeader && count($header) === count($legacyTemplate));
        $isCurrentTemplate = $header === $template
            || ($blankHeader && count($header) === count($template));
        if (!$isLegacyImport && !$isCurrentTemplate) {
            $this->Flash->error(__('取込ファイルのヘッダーが正しくありません。列の順番や名称を確認してください。'));
            return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
        }

        array_shift($rows);

        $imported = 0;
        $updated = 0;
        $reactivated = 0;
        $failed = 0;
        $errorMessages = [];

        $now = date('Y-m-d H:i:s');
        $userId = $this->getCurrentUser()?->id ?? 0;

        foreach ($rows as $row) {
            $importedPending = false;
            $updatedPending = false;
            $reactivatedPending = false;

            $row = array_map(static fn($value) => (string)($value ?? ''), $row);
            if (count(array_filter($row, static fn($value) => trim($value) !== '')) === 0) {
                continue;
            }

            if ($isLegacyImport) {
                $data = $this->mapLegacyCustomerRow($row);
                $clientCode = $data['client_code'];
                $clientName = $data['client_name'];
            } else {
                $row = array_pad(array_map('trim', $row), 25, '');
                [
                    $clientCode,
                    $clientBranchCode,
                    $clientName,
                    $clientBranchName,
                    $addr1,
                    $addr2,
                    $addr3,
                    $tel,
                    $fax,
                    $tel2,
                    $incharge1Name,
                    $incharge1Phone,
                    $incharge2Name,
                    $incharge2Phone,
                    $incharge3Name,
                    $incharge3Phone,
                    $incharge4Name,
                    $incharge4Phone,
                    $sectionName,
                    $salesPerson,
                    $deadline,
                    $notes1,
                    $notes2,
                    $notes3,
                    $notes4,
                ] = $row;

                $normalizedTel = $this->cleanPhone($tel) ?? '';
                $data = [
                    'client_code' => $clientCode,
                    'client_cd' => $clientCode,
                    'client_branch_code' => $clientBranchCode,
                    'client_name' => $clientName,
                    'client_branch_name' => $clientBranchName,
                    'company_name' => $clientName,
                    'company_branch_name' => $clientBranchName,
                    'name' => $clientName,
                    'addr1' => $addr1,
                    'addr2' => $addr2,
                    'addr3' => $addr3,
                    'tel' => $normalizedTel,
                    'tel_only' => $normalizedTel,
                    'phone' => $normalizedTel,
                    'phone_only' => $normalizedTel,
                    'fax' => $fax,
                    'tel2' => $this->cleanPhone($tel2),
                    'incharge1_name' => $incharge1Name,
                    'incharge1_phone' => $this->cleanPhone($incharge1Phone),
                    'incharge2_name' => $incharge2Name,
                    'incharge2_phone' => $this->cleanPhone($incharge2Phone),
                    'incharge3_name' => $incharge3Name,
                    'incharge3_phone' => $this->cleanPhone($incharge3Phone),
                    'incharge4_name' => $incharge4Name,
                    'incharge4_phone' => $this->cleanPhone($incharge4Phone),
                    'section_name' => $sectionName,
                    'sales_person' => $salesPerson,
                    'deadline' => $deadline,
                    'notes1' => $notes1,
                    'notes2' => $notes2,
                    'notes3' => $notes3,
                    'notes4' => $notes4,
                ];
            }

            if ($clientCode === '') {
                $failed++;
                continue;
            }

            $matches = $this->findImportMatches($clientCode, $data['client_branch_code']);
            if (count($matches) > 1) {
                $failed++;
                $errorMessages[] = __('同じキーと複数口座CDの顧客が複数存在します。');
                continue;
            }
            $entity = $matches[0] ?? null;

            $data['updated'] = $now;
            $data['updated_user_id'] = $userId;

            if ($entity) {
                if (!empty($entity->deleted)) {
                    $reactivatedPending = true;
                } else {
                    $updatedPending = true;
                }
                $data['deleted'] = null;
                if (!$isLegacyImport) {
                    $data['deleted_user_id'] = null;
                }
            } else {
                $entity = $this->Customers->newEmptyEntity();
                $data['name'] = $clientName;
                $data['company_name'] = $clientName;
                $importedPending = true;
            }

            if ($isLegacyImport || $entity->isNew()) {
                $data['created'] = $now;
                $data['created_user_id'] = $userId;
            }

            $entity = $this->Customers->patchEntity($entity, $data, ['validate' => 'import']);
            try {
                $saved = $this->Customers->save($entity);
            } catch (QueryException $exception) {
                if ((string)$exception->getCode() !== '23000') {
                    throw $exception;
                }
                $failed++;
                $errorMessages[] = __('顧客データがデータベースの制約に違反しています。キーと複数口座CDを確認してください。');
                continue;
            }

            if (!$saved) {
                $failed++;

                foreach ($entity->getErrors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $errorMessages[] = $field . ': ' . $message;
                    }
                }
            } elseif (!empty($reactivatedPending)) {
                $reactivated++;
            } elseif (!empty($updatedPending)) {
                $updated++;
            } elseif (!empty($importedPending)) {
                $imported++;
            }
        }

        if (!empty($errorMessages)) {
            $this->Flash->error("取込エラー:\n" . implode("\n", $errorMessages));
        }

        if ($isLegacyImport && $failed === 0) {
            $this->Flash->success(__('{0}件の登録＆更新が完了しました。', $imported + $updated + $reactivated));
            return $this->redirect(['controller' => 'Settings', 'action' => 'index']);
        }

        $summary = sprintf(
            '取込結果: 新規 %d件 / 更新 %d件 / 再有効化 %d件 / 失敗 %d件',
            $imported,
            $updated,
            $reactivated,
            $failed
        );

        $this->Flash->success($summary);
        return $this->redirect(['controller' => $isLegacyImport ? 'Settings' : 'Customers', 'action' => 'index']);
    }

    private function findImportMatches(string $clientCode, string $branchCode): array
    {
        $schema = $this->Customers->getSchema();
        $uniqueCodes = [];
        foreach ($schema->constraints() as $name) {
            $constraint = $schema->getConstraint($name);
            if ($constraint['type'] === TableSchema::CONSTRAINT_UNIQUE && count($constraint['columns']) === 1) {
                $uniqueCodes[] = $constraint['columns'][0];
            }
        }
        foreach (['client_code', 'client_cd'] as $column) {
            if (in_array($column, $uniqueCodes, true)) {
                return $this->Customers->find()->where([$column => $clientCode])->limit(2)->toArray();
            }
        }

        $branch = $branchCode === ''
            ? ['OR' => [['client_branch_code' => ''], ['client_branch_code IS' => null]]]
            : ['client_branch_code' => $branchCode];
        $code = [$schema->hasColumn('client_code') ? 'client_code' : 'client_cd' => $clientCode];
        if ($schema->hasColumn('client_code') && $schema->hasColumn('client_cd')) {
            $code = ['OR' => [
                ['client_code' => $clientCode],
                ['client_code IS' => null, 'client_cd' => $clientCode],
            ]];
        }

        return $this->Customers->find()
            ->where($branch)
            ->andWhere($code)
            ->limit(2)
            ->toArray();
    }

    /**
     * Remove hyphens/spaces from phone string.
     */
    protected function cleanPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        return preg_replace('/[^0-9]/', '', $phone);
    }

    protected function mapLegacyCustomerRow(array $row): array
    {
        $row = array_pad(array_slice($row, 0, 26), 26, '');
        [
            $clientCode,
            $baseName,
            $bookName,
            $clientBranchCode,
            $reserve1,
            $reserve2,
            $addr1,
            $addr2,
            $fax,
            $tel,
            $subPhone1,
            $subPhone2,
            $subPhone3,
            $subPhone4,
            $subPhone5,
            $salesStaffCode,
            $salesRepName,
            $closingDate,
            $collectionClass,
            $directClass,
            $remarks1,
            $remarks2,
            $notes3,
            $notes4,
            $remarks5,
            $remarks6,
        ] = $row;

        $normalizedTel = $this->cleanPhone($tel) ?? '';
        return [
            'client_code' => $clientCode,
            'client_cd' => $clientCode,
            'client_branch_code' => $clientBranchCode,
            'client_name' => $bookName,
            'book_name' => $bookName,
            'base_name' => $baseName,
            'reserve1' => $reserve1,
            'reserve2' => $reserve2,
            'addr1' => str_replace("'", '', $addr1),
            'addr2' => str_replace("'", '', $addr2),
            'fax' => $fax,
            'tel' => str_replace('-', '', $tel),
            'tel_only' => $normalizedTel,
            'phone' => $normalizedTel,
            'phone_only' => $normalizedTel,
            'sub_phone1' => $subPhone1,
            'sub_phone2' => $subPhone2,
            'sub_phone3' => $subPhone3,
            'sub_phone4' => $subPhone4,
            'sub_phone5' => $subPhone5,
            'sales_staff_cd' => $salesStaffCode,
            'sales_rep_name' => $salesRepName,
            'closing_date' => $closingDate,
            'collection_cls' => $collectionClass,
            'direct_cls' => $directClass,
            'remarks1' => $remarks1,
            'remarks2' => $remarks2,
            'notes3' => $notes3,
            'notes4' => $notes4,
            'remarks5' => $remarks5,
            'remarks6' => $remarks6,
        ];
    }
    function getCsvTemplate()
    {
        return [
            '得意先CD',
            '得意先支店CD',
            '得意先名',
            '得意先支店名',
            '住所1',
            '住所2',
            '住所3',
            'TEL',
            'FAX',
            'サブ電話番号1',
            '顧客の担当者1',
            '顧客の担当者1電話',
            '顧客の担当者2',
            '顧客の担当者2電話',
            '顧客の担当者3',
            '顧客の担当者3電話',
            '顧客の担当者4',
            '顧客の担当者4電話',
            '所属名',
            '営業担当者名',
            '締日',
            '備考1',
            '備考2',
            '備考3',
            '備考4',
        ];
    }

    protected function getLegacyCsvTemplate(): array
    {
        return [
            'キー',
            '拠点',
            '得意先名(帳合先名)',
            '複数口座CD',
            '得意先CD',
            '帳合先・納品先CD',
            '住所1',
            '住所2',
            'FAX',
            'TEL',
            'サブ電話番号1',
            'サブ電話番号2',
            'サブ電話番号3',
            'サブ電話番号4',
            'サブ電話番号5',
            '営業員CD',
            '営業担当者名',
            '締日',
            '回収区分',
            'ベッツダイレクト区分',
            '備考1(受注時摘要)',
            '備考2(出荷時摘要)',
            '備考3',
            '備考4',
            '備考5',
            '備考6',
        ];
    }
}
