<?php
$this->assign('title', '対応履歴詳細');
$currentUser = $this->request->getAttribute('identity');
$userAuth = $currentUser ? $currentUser->getOriginalData()->auth : null;
$this->Form->setTemplates(['inputContainer' => '{{content}}']);
?>
<div class="contact-view">
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">顧客詳細情報</h3>
            <span style="float:right;margin-top:-20px">
                <button type="button" style="width:150px;cursor:pointer;" class="btn-warning btn-xs"
                    onclick="location.href='<?= $this->Url->build(['controller' => 'Customers', 'action' => 'edit', $customer->id]) ?>';">修　正</button>
            </span>
        </div>
        <table class="table customer-details">
            <tbody>
                <tr>
                    <td width="15%" class="customer-label">キー＊</td>
                    <td width="30%"><?= h($customer->client_code ?? $customer->client_cd ?? '') ?></td>
                    <td width="15%" class="customer-label">拠点</td>
                    <td colspan="3"><?= h($customer->base_name ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">得意先名（帳合先名）</td>
                    <td><?= h($customer->book_name ?? '') ?></td>
                    <td class="customer-label">複数口座CD</td>
                    <td><?= h($customer->client_branch_code ?? '') ?></td>
                </tr>
                <tr>
                    <td width="15%" class="customer-label">得意先CD</td>
                    <td width="30%"><?= h($customer->reserve1 ?? '') ?></td>
                    <td width="15%" class="customer-label">帳合先・納品先CD</td>
                    <td colspan="3"><?= h($customer->reserve2 ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">住所1</td>
                    <td><?= h($customer->addr1 ?? '') ?></td>
                    <td class="customer-label">住所2</td>
                    <td colspan="3"><?= h($customer->addr2 ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">FAX</td>
                    <td><?= h($customer->fax ?? '') ?></td>
                    <td class="customer-label">TEL＊</td>
                    <td><?= !empty($customer->tel) ? $this->Form->postLink(
                        (string)$customer->tel,
                        ['_name' => 'contactDial', 'phone' => rawurlencode((string)$customer->tel)]
                    ) : '' ?></td>
                </tr>
                <tr>
                    <td class="customer-label">営業員CD</td>
                    <td><?= h($customer->sales_staff_cd ?? '') ?></td>
                    <td class="customer-label">サブ電話番号1</td>
                    <td><?= h($customer->sub_phone1 ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">営業担当者名</td>
                    <td><?= h($customer->sales_rep_name ?? '') ?></td>
                    <td class="customer-label">サブ電話番号2</td>
                    <td><?= h($customer->sub_phone2 ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">締日</td>
                    <td><?= h($customer->closing_date ?? '') ?></td>
                    <td class="customer-label">サブ電話番号3</td>
                    <td><?= h($customer->sub_phone3 ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">回収区分</td>
                    <td><?= h($customer->collection_cls ?? '') ?></td>
                    <td class="customer-label">サブ電話番号4</td>
                    <td><?= h($customer->sub_phone4 ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">ベッツダイレクト区分</td>
                    <td><?= h($customer->direct_cls ?? '') ?></td>
                    <td class="customer-label">サブ電話番号5</td>
                    <td><?= h($customer->sub_phone5 ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">登録日</td>
                    <td><?= h($customer->created?->format('Y-m-d H:i:s') ?? '') ?></td>
                    <td class="customer-label">登録者</td>
                    <td colspan="3"><?= h($allUsersOpt[$customer->created_user_id] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="customer-label">更新日</td>
                    <td><?= h($customer->updated?->format('Y-m-d H:i:s') ?? '') ?></td>
                    <td class="customer-label">更新者</td>
                    <td colspan="3"><?= h($allUsersOpt[$customer->updated_user_id] ?? '') ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <?= $this->Form->create($contact, ['url' => ['action' => 'edit', $contact->id], 'type' => 'post', 'class' => 'form contact-update-form']) ?>
    <?= $this->Form->control('id', ['type' => 'hidden']) ?>
    <?= $this->Form->control('customer_id', ['type' => 'hidden']) ?>
    <div class="panel panel-warning">
        <div class="panel-heading">
            <h3 class="panel-title">対応履歴更新</h3>
        </div>
        <table class="table contact-update">
            <tbody>
                <tr>
                    <td width="15%" class="update-label">概要</td>
                    <td><?= $this->Form->control('summary', ['label' => false, 'type' => 'text', 'class' => 'form-control if100']) ?></td>
                    <td class="update-label">種別</td>
                    <td><?= $this->Form->control('queue_name', ['label' => false, 'type' => 'select', 'required' => false, 'options' => $queueOpt ?? [], 'value' => $queueSelectedValue ?? $contact->queue_name, 'empty' => '-----', 'class' => 'form-control if100']) ?></td>
                </tr>
                <tr>
                    <td class="update-label">ステータス</td>
                    <td><?= $this->Form->control('status', ['label' => false, 'type' => 'select', 'options' => $statusOpt ?? [], 'empty' => '-----', 'class' => 'form-control if100', 'required' => true]) ?></td>
                    <td class="update-label"></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="update-label">対応内容</td>
                    <td><?= $this->Form->control('detail', ['label' => false, 'type' => 'textarea', 'cols' => 20, 'rows' => 5, 'class' => 'form-control if100', 'style' => 'width:100%']) ?></td>
                    <td class="update-label">メモ</td>
                    <td><?= $this->Form->control('remark', ['label' => false, 'type' => 'textarea', 'cols' => 20, 'rows' => 5, 'class' => 'form-control if100', 'style' => 'width:100%']) ?></td>
                </tr>
            </tbody>
        </table>
        <div style="width:95%;margin-bottom:5px;margin-top:5px;" align="center" class="btn_margin">
            <?php if ($userAuth !== null && (string)$userAuth !== '0'): ?>
                <?= $this->Form->button('削　除', ['name' => 'delete', 'value' => '1', 'type' => 'submit', 'formnovalidate' => true, 'style' => 'width:150px;height:30px;cursor:pointer', 'class' => 'btn-danger btn-xs', 'onclick' => 'return confirm("対応履歴を削除します。よろしいですか？")']) ?>
            <?php endif; ?>
            <?= $this->Form->button('更　新', ['name' => 'update', 'value' => '1', 'type' => 'submit', 'style' => 'width:150px;height:30px;cursor:pointer', 'class' => 'btn-warning btn-xs EditButton']) ?>
            <?= $this->Form->button('新規登録', ['name' => 'add', 'value' => '1', 'type' => 'submit', 'formnovalidate' => true, 'style' => 'width:150px;height:30px;cursor:pointer', 'class' => 'btn-info btn-xs']) ?>
        </div>
    </div>
    <?= $this->Form->end() ?>

    <a id="anc"></a>
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
    .contact-view .customer-label,
    .contact-view .contact-history th {
        vertical-align: middle;
        color: #ffffff;
        background-color: #149c82;
        font-weight: normal;
    }
    .contact-view .update-label {
        vertical-align: middle;
        color: #ffffff;
        background-color: #ff8c00;
    }
    .contact-view .contact-history td {
        vertical-align: middle;
    }
</style>
<script>
    document.querySelector('.contact-update-form').addEventListener('submit', function (event) {
        if (!event.submitter || event.submitter.name !== 'update') {
            return;
        }
        if (!this.querySelector('[name="status"]').value) {
            event.preventDefault();
            alert('ステータスは必須項目です');
            return;
        }
        if (!window.confirm('対応履歴を登録します。よろしいですか？')) {
            event.preventDefault();
        }
    });
</script>
