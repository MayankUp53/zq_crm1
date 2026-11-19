<?php
$this->assign('title', '顧客詳細情報');
$fields = [
    'client_code' => 'キー', 'base_name' => '拠点',
    'book_name' => '得意先名(帳合先名)', 'client_branch_code' => '複数口座CD',
    'reserve1' => '得意先CD', 'reserve2' => '帳合先・納品先CD',
    'addr1' => '住所1', 'addr2' => '住所2', 'fax' => 'FAX', 'tel' => 'TEL',
    'sales_staff_cd' => '営業員CD', 'sub_phone1' => 'サブ電話番号1',
    'sales_rep_name' => '営業担当者名', 'sub_phone2' => 'サブ電話番号2',
    'closing_date' => '締日', 'sub_phone3' => 'サブ電話番号3',
    'collection_cls' => '回収区分', 'sub_phone4' => 'サブ電話番号4',
    'direct_cls' => 'ベッツダイレクト区分', 'sub_phone5' => 'サブ電話番号5',
    'remarks1' => '備考1(受注時摘要)', 'remarks2' => '備考2(出荷時摘要)',
    'notes3' => '備考3', 'notes4' => '備考4', 'remarks5' => '備考5', 'remarks6' => '備考6',
];
?>
<div class="panel panel-primary">
    <div class="panel-heading"><h3 class="panel-title">顧客詳細情報</h3></div>
    <table class="table">
        <tbody>
            <?php foreach (array_chunk($fields, 2, true) as $row): ?>
                <tr>
                    <?php foreach ($row as $field => $label): ?>
                        <th scope="row"><?= h($label) ?></th>
                        <td><?= nl2br(h($customer->{$field} ?? '')) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="btn_margin">
    <?= $this->Html->link('顧客一覧', ['action' => 'index'], ['class' => 'btn btn-default btn-xs']) ?>
    <?= $this->Html->link('修正', ['action' => 'edit', $customer->id], ['class' => 'btn btn-warning btn-xs']) ?>
    <?= $this->Html->link('対応履歴登録', ['controller' => 'Contacts', 'action' => 'add', $customer->id], ['class' => 'btn btn-info btn-xs']) ?>
</div>
<div class="panel panel-primary">
    <div class="panel-heading"><h3 class="panel-title">対応履歴</h3></div>
    <table class="table table-striped table-hover">
        <thead>
            <tr><th>概要</th><th>ステータス</th><th>更新日時</th><th>操作</th></tr>
        </thead>
        <tbody>
            <?php foreach ($contacts as $contact): ?>
                <tr>
                    <td><?= h($contact->summary) ?></td>
                    <td><?= h($statusOpt[$contact->status] ?? '') ?></td>
                    <td><?= h($contact->updated?->format('Y-m-d H:i:s')) ?></td>
                    <td><?= $this->Html->link('詳細', ['controller' => 'Contacts', 'action' => 'view', $contact->id]) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($contacts->isEmpty()): ?>
                <tr><td colspan="4">対応履歴はありません。</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
