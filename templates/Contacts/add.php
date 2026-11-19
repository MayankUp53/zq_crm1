<?php
$this->assign('title', '対応履歴登録');
$customerRows = [
    ['キー＊', 'client_code', '拠点', 'base_name'],
    ['得意先名(帳合先名)', 'book_name', '複数口座CD', 'client_branch_code'],
    ['得意先CD', 'reserve1', '帳合先・納品先CD', 'reserve2'],
    ['住所1', 'addr1', '住所2', 'addr2'],
    ['FAX', 'fax', 'TEL＊', 'tel'],
    ['営業員CD', 'sales_staff_cd', 'サブ電話番号1', 'sub_phone1'],
    ['営業担当者名', 'sales_rep_name', 'サブ電話番号2', 'sub_phone2'],
    ['締日', 'closing_date', 'サブ電話番号3', 'sub_phone3'],
    ['回収区分', 'collection_cls', 'サブ電話番号4', 'sub_phone4'],
    ['ベッツダイレクト区分', 'direct_cls', 'サブ電話番号5', 'sub_phone5'],
];
$this->Form->setTemplates(['inputContainer' => '{{content}}']);
?>
<div class="contact-add">
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">顧客詳細情報</h3>
            <span style="float:right;margin-top:-20px">
                <button type="button" style="width:150px;cursor:pointer;" class="btn btn-warning btn-xs"
                    onclick="location.href='<?= $this->Url->build(['controller' => 'Customers', 'action' => 'edit', $customer->id]) ?>';">修　正</button>
            </span>
        </div>
        <table class="table customer-details">
            <colgroup><col style="width:15%"><col style="width:30%"><col style="width:15%"><col></colgroup>
            <tbody>
                <?php foreach ($customerRows as [$leftLabel, $leftField, $rightLabel, $rightField]): ?>
                    <tr>
                        <td class="customer-label"><?= h($leftLabel) ?></td>
                        <td><?= h($customer->{$leftField} ?? '') ?></td>
                        <td class="customer-label"><?= h($rightLabel) ?></td>
                        <td>
                            <?php if ($rightField === 'tel' && !empty($customer->tel)): ?>
                                <?= $this->Form->postLink(
                                    (string)$customer->tel,
                                    ['_name' => 'contactDial', 'phone' => rawurlencode((string)$customer->tel)],
                                    ['style' => 'color:blue']
                                ) ?>
                            <?php else: ?>
                                <?= h($customer->{$rightField} ?? '') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td class="customer-label">登録日</td>
                    <td><?= h($customer->created?->format('Y-m-d H:i:s') ?? '') ?></td>
                    <td class="customer-label">登録者</td>
                    <td><?= h($allUsersOpt[$customer->created_user_id] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">更新日</td>
                    <td><?= h($customer->updated?->format('Y-m-d H:i:s') ?? '') ?></td>
                    <td class="customer-label">更新者</td>
                    <td><?= h($allUsersOpt[$customer->updated_user_id] ?? '') ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <?= $this->Form->create($contact, ['url' => ['action' => 'add', $customer->id], 'class' => 'form contact-add-form', 'novalidate' => true]) ?>
    <?= $this->Form->control('customer_id', ['type' => 'hidden', 'value' => $customer->id]) ?>
    <div class="panel panel-info">
        <div class="panel-heading">
            <h3 class="panel-title">対応履歴新規登録</h3>
        </div>
        <table class="table contact-entry">
            <tbody>
                <tr>
                    <td class="add-label">概要</td>
                    <td><?= $this->Form->control('summary', ['label' => false, 'type' => 'text', 'required' => false, 'class' => 'form-control if100']) ?></td>
                    <td class="add-label">種別＊</td>
                    <td><?= $this->Form->control('queue_name', ['label' => false, 'type' => 'select', 'required' => true, 'options' => $queueOpt ?? [], 'empty' => '-----', 'class' => 'form-control if100']) ?></td>
                </tr>
                <tr>
                    <td class="add-label">ステータス</td>
                    <td><?= $this->Form->control('status', ['label' => false, 'type' => 'select', 'options' => $statusOpt ?? [], 'empty' => '-----', 'class' => 'form-control if100', 'required' => false]) ?></td>
                    <td class="add-label"></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="add-label">対応内容</td>
                    <td><?= $this->Form->control('detail', ['label' => false, 'type' => 'textarea', 'cols' => 20, 'rows' => 5, 'class' => 'form-control if100', 'style' => 'width:100%']) ?></td>
                    <td class="add-label">メモ</td>
                    <td><?= $this->Form->control('remark', ['label' => false, 'type' => 'textarea', 'cols' => 20, 'rows' => 5, 'class' => 'form-control if100', 'style' => 'width:100%']) ?></td>
                </tr>
            </tbody>
        </table>
        <div style="width:95%;margin-bottom:5px;margin-top:5px;" align="center" class="btn_margin">
            <?= $this->Form->button('登　録', ['name' => 'add', 'value' => '1', 'type' => 'submit', 'style' => 'width:150px;height:30px;cursor:pointer', 'class' => 'btn-info btn-xs AddButton']) ?>
        </div>
    </div>
    <?= $this->Form->end() ?>

    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">履歴一覧</h3>
            <span style="float:right;margin-top:-20px">検索結果<?= count($contacts) ?>件</span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover contact-history">
                <thead>
                    <tr>
                        <th width="7%">履歴番号</th>
                        <th width="12%">登録日時</th>
                        <th width="25%">ステータス</th>
                        <th width="12%">担当OP</th>
                        <th>概要</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contacts as $row): ?>
                        <tr ondblclick="location.href='<?= $this->Url->build(['action' => 'view', $row->id]) ?>';">
                            <td><?= h($row->id) ?></td>
                            <td><?= h($row->created?->format('Y-m-d H:i') ?? '') ?></td>
                            <td><?= h($statusOpt[$row->status] ?? '') ?></td>
                            <td><?= h($allUsersOpt[$row->created_user_id] ?? '') ?></td>
                            <td><?= h(mb_strlen((string)$row->summary) > 30 ? mb_substr((string)$row->summary, 0, 30) . '...' : (string)$row->summary) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<br><br>
<style>
    .contact-add .customer-label,
    .contact-add .contact-history th {
        vertical-align: middle;
        color: #ffffff;
        background-color: #149c82;
        font-weight: normal;
    }
    .contact-add .add-label {
        vertical-align: middle;
        color: #ffffff;
        background-color: #4169E1;
    }
    .contact-add .contact-history td {
        vertical-align: middle;
    }
</style>
<script>
    document.querySelector('.contact-add-form').addEventListener('submit', function (event) {
        if (!event.submitter || event.submitter.name !== 'add') {
            return;
        }
        if (!this.querySelector('[name="queue_name"]').value) {
            event.preventDefault();
            alert('種別は必須項目です');
            return;
        }
        if (!window.confirm('対応履歴を登録します。よろしいですか？')) {
            event.preventDefault();
        }
    });
</script>
