<?php

/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface<\App\Model\Entity\Customer> $customers
 */
$currentUser = $this->request->getAttribute('identity');
$search = $searchData ?? [];
$this->Form->setTemplates(['inputContainer' => '{{content}}']);
?>
<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title">顧客情報検索条件</h3>
    </div>
    <?= $this->Form->create(null, ['url' => ['action' => 'index'], 'type' => 'post']) ?>
    <table class="table customer-search">
    <colgroup><col style="width:10%"><col style="width:25%"><col style="width:10%"><col style="width:25%"><col style="width:10%"><col style="width:25%"></colgroup>
    <tbody>
    <tr>
        <td class="search-label">キー＊</td>
        <td>
            <?= $this->Form->control('Search.client_code', [
                'label' => false, 'type' => 'text',
                'value' => $search['client_code'] ?? '',
                'class' => 'form-control'
            ]) ?>
        </td>
        <td class="search-label">得意先名（帳合先名）</td>
        <td><?= $this->Form->control('Search.book_name', ['label' => false, 'value' => $search['book_name'] ?? '', 'class' => 'form-control']) ?></td>
        <td class="search-label">拠点</td>
        <td><?= $this->Form->control('Search.base_name', ['label' => false, 'value' => $search['base_name'] ?? '', 'class' => 'form-control']) ?></td>
    </tr>
    <tr>
        <td class="search-label">得意先CD</td>
        <td><?= $this->Form->control('Search.reserve1', ['label' => false, 'value' => $search['reserve1'] ?? '', 'class' => 'form-control']) ?></td>
        <td class="search-label">帳合先・納品先CD</td>
        <td><?= $this->Form->control('Search.reserve2', ['label' => false, 'value' => $search['reserve2'] ?? '', 'class' => 'form-control']) ?></td>
        <td class="search-label">営業員CD</td>
        <td><?= $this->Form->control('Search.sales_staff_cd', ['label' => false, 'value' => $search['sales_staff_cd'] ?? '', 'class' => 'form-control']) ?></td>
    </tr>
    <tr>
        <td class="search-label">住所1</td>
        <td><?= $this->Form->control('Search.addr1', ['label' => false, 'value' => $search['addr1'] ?? '', 'class' => 'form-control']) ?></td>
        <td class="search-label">住所2</td>
        <td><?= $this->Form->control('Search.addr2_search', ['label' => false, 'value' => $search['addr2_search'] ?? '', 'class' => 'form-control']) ?></td>
        <td class="search-label">営業担当者名</td>
        <td><?= $this->Form->control('Search.sales_person', ['label' => false, 'value' => $search['sales_person'] ?? '', 'class' => 'form-control']) ?></td>
    </tr>
    <tr>
        <td class="search-label">TEL</td>
        <td><?= $this->Form->control('Search.tel_only', ['label' => false, 'value' => $search['tel_only'] ?? '', 'class' => 'form-control']) ?></td>
        <td class="search-label">サブ電話番号1</td>
        <td><?= $this->Form->control('Search.sub_phone1', ['label' => false, 'value' => $search['sub_phone1'] ?? '', 'class' => 'form-control']) ?></td>
        <td class="search-label">サブ電話番号2</td>
        <td><?= $this->Form->control('Search.sub_phone2', ['label' => false, 'value' => $search['sub_phone2'] ?? '', 'class' => 'form-control']) ?></td>
    </tr>
    <tr>
        <?php for ($phoneNumber = 3; $phoneNumber <= 5; $phoneNumber++): ?>
            <td class="search-label">サブ電話番号<?= $phoneNumber ?></td>
            <td><?= $this->Form->control('Search.sub_phone' . $phoneNumber, ['label' => false, 'value' => $search['sub_phone' . $phoneNumber] ?? '', 'class' => 'form-control']) ?></td>
        <?php endfor; ?>
    </tr>
    <tr>
        <td class="search-label">登録日（From）</td>
        <td><?= $this->Form->control('Search.created_from', ['label' => false, 'type' => 'text', 'size' => 10, 'value' => $search['created_from'] ?? '', 'class' => 'form-control datepicker']) ?></td>
        <td class="search-label">登録日（To）</td>
        <td><?= $this->Form->control('Search.created_to', ['label' => false, 'type' => 'text', 'size' => 10, 'value' => $search['created_to'] ?? '', 'class' => 'form-control datepicker']) ?></td>
        <td></td><td></td>
    </tr>
</tbody>


    </table>
    <div class="text-center btn_margin" style="width:95%;margin-bottom:5px;margin-top:5px;">
        <?= $this->Form->button('検　索', ['name' => 'searching', 'value' => '1', 'type' => 'submit', 'class' => 'btn-success btn-xs', 'style' => 'width:150px;height:30px;cursor:pointer']) ?>
        <?= $this->Form->button('クリア', ['name' => 'clear', 'value' => '1', 'type' => 'submit', 'class' => 'btn-success btn-xs', 'style' => 'width:150px;height:30px;cursor:pointer']) ?>
        <?= $this->Form->button('新規登録', ['name' => 'add', 'value' => '1', 'type' => 'submit', 'class' => 'btn-info btn-xs', 'style' => 'width:150px;height:30px;cursor:pointer']) ?>
        <?php if ($currentUser && $currentUser->auth != 0): ?>
            <?= $this->Form->button('検索結果を出力', ['name' => 'download', 'value' => '1', 'type' => 'submit', 'class' => 'btn-success btn-xs', 'style' => 'width:150px;height:30px;cursor:pointer']) ?>
        <?php endif; ?>
    </div>
    <?= $this->Form->end() ?>
</div>

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title">検索結果一覧</h3>
        <span style="float:right;margin-top:-20px">
            <?= $this->Paginator->counter(__('{{page}}/{{pages}}ページ  {{start}}件目から{{end}}件目 検索結果{{count}}件')) ?>
        </span>
    </div>
    <div class="table-responsive">
        <table id="dt" class="table table-striped table-hover customer-results">
            <thead>
                <tr height="35" bgcolor="#20b2aa" style="vertical-align:middle;color:#ffffff;">
                    <td width="4%">キー</td>
                    <td width="6%">複数口座CD</td>
                    <td width="9%">得意先名<br>（帳合先名）</td>
                    <td width="6%">拠点</td>
                    <td width="5%">TEL</td>
                    <td width="7%">サブ電話番号1</td>
                    <td width="11%">住所1</td>
                    <td width="6%">営業員CD</td>
                    <td width="6%">営業担当者名</td>
                    <td width="6%">サブ電話番号2</td>
                    <td width="5%">締日</td>
                    <td width="5%">回収区分</td>
                    <td width="5%">ベッツダイレクト区分</td>
                    <td width="5%">備考1（受注時摘要）</td>
                    <td width="5%">登録日</td>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr onDblClick="location.href='<?= $this->Url->build(['controller' => 'Contacts', 'action' => 'add', $customer->id]) ?>'">
                        <?php
                        $customerTitle = $customer->book_name
                            ?: $customer->client_name
                            ?: $customer->company_name
                            ?: $customer->name
                            ?: $customer->client_code
                            ?: '顧客 #' . $customer->id;
                        $address = (string)$customer->addr1;
                        $addressLabel = $address;
                        $lastComma = strrpos($address, ',');
                        if ($lastComma !== false && !str_contains($address, "\n")) {
                            $addressLabel = substr($address, 0, $lastComma + 1) . "\n" . ltrim(substr($address, $lastComma + 1));
                        }
                        ?>
                        <td><?= h($customer->client_code ?? $customer->client_cd) ?></td>
                        <td><?= h($customer->client_branch_code) ?></td>
                        <td><?= h((string)$customerTitle) ?></td>
                        <td><?= h($customer->base_name ?: $customer->section_name) ?></td>
                        <td><?= $customer->tel ? $this->Form->postLink(
                            (string)$customer->tel,
                            ['_name' => 'customerDial', 'phone' => rawurlencode((string)$customer->tel)]
                        ) : '' ?></td>
                        <td><?= h($customer->sub_phone1 ?: $customer->tel2) ?></td>
                        <td class="customer-address"><a style="color:blue" target="_blank" rel="noopener noreferrer" href="https://www.google.co.jp/maps?q=<?= rawurlencode($address) ?>"><?= h($addressLabel) ?></a></td>
                        <td><?= h($customer->sales_staff_cd) ?></td>
                        <td><?= h($customer->sales_rep_name ?: $customer->sales_person) ?></td>
                        <td><?= h($customer->sub_phone2 ?: $customer->tel3) ?></td>
                        <td><?= h($customer->closing_date ?: $customer->deadline) ?></td>
                        <td><?= h($customer->collection_cls) ?></td>
                        <td><?= h($customer->direct_cls) ?></td>
                        <td><?= h(mb_substr((string)($customer->remarks1 ?: $customer->notes1), 0, 11) . (mb_strlen((string)($customer->remarks1 ?: $customer->notes1)) > 11 ? '...' : '')) ?></td>
                        <td><?= $customer->created ? h($customer->created->format('Y-m-d')) : '' ?></td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
    <div class="paginateLinks btn_margin" style="width:100%" align="center">
        <ul class="pagination pagination-sm">
            <li><?= $this->Paginator->first('<< ', ['class' => 'page-link']) ?></li>
            <li><?= $this->Paginator->prev('前へ ', ['class' => 'page-link']) ?></li>
            <li><?= $this->Paginator->numbers(['separator' => '']) ?></li>
            <li><?= $this->Paginator->next('次へ', ['class' => 'page-link']) ?></li>
            <li><?= $this->Paginator->last('>>', ['class' => 'page-link']) ?></li>
        </ul>
    </div>
    <br><br>
</div>

<style>
    .search-label {
        vertical-align: middle;
        color: #ffffff;
        background-color: #149c82;
    }
    .customer-results {
        width: 100%;
        min-width: 720px;
        table-layout: fixed;
        font-size: 12px;
    }
    .customer-results > thead > tr > td,
    .customer-results > tbody > tr > td {
        padding: 6px 4px;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .customer-results > tbody > tr > .customer-address {
        white-space: pre-line;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && jQuery.fn.datepicker) {
            jQuery('.customer-search .datepicker').datepicker({
                changeMonth: true,
                changeYear: true,
                yearRange: '-100:+0',
                dateFormat: 'yy-mm-dd'
            });
        }
    });
</script>
