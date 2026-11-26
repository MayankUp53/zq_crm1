<?php
/**
 * Homes/index
 * AmeyoJ Dashboard (Manual Dial + Call History)
 */
$this->Html->css('https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap.min.css', ['block' => true]);
?>

<div class="bs-docs-section clearfix">

<!-- ================= DASHBOARD ================= -->
<div class="panel panel-primary filterable">
    <div class="panel-heading">
        <h3 class="panel-title">AmeyoJ ダッシュボード</h3>
    </div>

<!-- ===================================================== -->
<!-- JAVASCRIPT VALIDATION + PHONE BOOK HANDLER -->
<!-- ===================================================== -->
<script>
function validateManualDial() {
    const cid = document.getElementById('cid')?.value;
    const tel = document.getElementById('tel')?.value;

    if (!cid) {
        alert('通知番号を選択してください');
        return false;
    }
    if (!tel) {
        alert('発信先電話番号を入力してください');
        return false;
    }
    return true;
}

</script>

<!-- ===================================================== -->
<!-- MANUAL DIAL FORM -->
<!-- ===================================================== -->
<?= $this->Form->create(null, [
    'url' => ['controller' => 'Homes', 'action' => 'manualDialApi'],
    'class' => 'form-inline',
    'onsubmit' => 'return validateManualDial();'
]) ?>

<table class="table" style="width:auto; margin-bottom:10px;">
    <tr>
        <td style="width:180px;">
            <?= $this->Form->control('cid', [
                'label' => false,
                'id' => 'cid',
                'type' => 'select',
                'options' => $cidOpt,
                'empty' => '--- 通知番号 ---',
                'class' => 'form-control',
            ]) ?>
        </td>

        <td style="width:180px;">
            <?= $this->Form->control('tel', [
                'label' => false,
                'id' => 'tel',
                'type' => 'text',
                'placeholder' => '発信先TEL',
                'value' => $dialPhone ?? '',
                'class' => 'form-control',
            ]) ?>
        </td>

        <td style="padding-left:6px;">
            <button type="button"
                class="btn btn-success btn-xs"
                onclick="window.open('<?= $this->Url->build(['action' => 'phoneList', '?' => ['target' => 'tel']]) ?>','','width=500,height=700');">
                電話帳
            </button>
        </td>

        <td style="padding-left:6px;">
            <?= $this->Form->submit('発信', [
                'class' => 'btn btn-info btn-xs',
                'disabled' => empty($cidOpt),
            ]) ?>
        </td>

    </tr>
</table>

<?= $this->Form->end() ?>

</div>


<!-- ===================================================== -->
<!-- CALL HISTORY -->
<!-- ===================================================== -->
<div class="panel-heading" style="background-color:midnightblue;color:#fff">
    コール履歴一覧（直近<?= h($limit) ?>件まで表示）
</div>

<table id="callsTable" class="table table-hover table-bordered" style="font-size:12px; width:100%;">

<thead>
<tr style="color:#fff;background:#46b8da;">
    <th>No.</th>
    <th style="display:none">campaign_id</th>
    <th style="display:none">call_id</th>
    <th>キャンペーン</th>
    <th style="display:none">キューID</th>
    <th>種別</th>
    <th>キュー名</th>
    <th style="display:none">電話番号</th>
    <th>発信元</th>
    <th>発信先</th>
    <th style="display:none">lead_id</th>
    <th style="display:none">lead_name</th>
    <th style="display:none">システム結果</th>
    <th>コール結果</th>
    <th>転送先</th>
    <th style="display:none">ユーザID</th>
    <th>ユーザ名</th>
    <th>開始日時</th>
    <th style="display:none">終了日時</th>
    <th style="display:none">呼出時間</th>
    <th style="display:none">IVR時間</th>
    <th>通話時間</th>
    <th style="display:none">保留時間</th>
    <th style="display:none">待機時間</th>
    <th style="display:none">後処理時間</th>
    <th>通録</th>
</tr>
</thead>

<tbody>
<?php $calls = $response ?? []; ?>

<?php if (!empty($calls)): ?>
    <?php foreach ($calls as $row): ?>
        <tr>
            <td><?= h($row['row_number'] ?? '') ?></td>
            <td style="display:none"><?= h($row['campaign_id'] ?? '') ?></td>
            <td style="display:none"><?= h($row['call_id'] ?? '') ?></td>
            <td><?= h($row['campaign_name'] ?? '') ?></td>
            <td style="display:none"><?= h($row['agent_queue_id'] ?? '') ?></td>
            <td><?= h($row['call_type'] ?? '') ?></td>
            <td><?= h($row['agent_queue_name'] ?? '') ?></td>
            <td style="display:none"><?= h($row['phone'] ?? '') ?></td>
            <td>
                <?php if (!empty($row['phone'])): ?>
                    <?= $this->Html->link($row['srcphone'] ?? '', ['controller' => 'Customers', 'action' => 'jump', $row['phone']]) ?>
                <?php else: ?>
                    <?= h($row['srcphone'] ?? '') ?>
                <?php endif; ?>
            </td>
            <td><?= h($row['dstphone'] ?? '') ?></td>
            <td style="display:none"><?= h($row['lead_id'] ?? '') ?></td>
            <td style="display:none"><?= h($row['lead_name'] ?? '') ?></td>
            <td style="display:none"><?= h($row['system_disposition'] ?? '') ?></td>
            <td><?= h($row['disposition_code'] ?? '') ?></td>
            <td><?= h($row['transfer_to'] ?? '') ?></td>
            <td style="display:none"><?= h($row['user_id'] ?? '') ?></td>
            <td><?= h($row['user_name'] ?? '') ?></td>
            <td><?= h(isset($row['call_originate_time']) ? substr($row['call_originate_time'],0,16) : '') ?></td>
            <td style="display:none"><?= h(isset($row['call_end_time']) ? substr($row['call_end_time'],0,16) : '') ?></td>
            <td style="display:none"><?= h($row['ringing_time'] ?? '') ?></td>
            <td style="display:none"><?= h($row['ivr_time'] ?? '') ?></td>
            <td><?= h($row['talk_time'] ?? '') ?></td>
            <td style="display:none"><?= h($row['hold_time'] ?? '') ?></td>
            <td style="display:none"><?= h($row['wait_time'] ?? '') ?></td>
            <td style="display:none"><?= h($row['wrap_time'] ?? '') ?></td>
            <td>
                <?php if (!empty($row['call_id']) && !empty($row['crt_object_id'])): ?>
                    <?= $this->Html->link('再生', ['action' => 'play', (string)$row['call_id'], (string)$row['crt_object_id']], [
                        'class' => 'btn btn-xs btn-default',
                        'target' => '_blank',
                        'rel' => 'noopener',
                        'onclick' => "window.open(this.href, 'play', 'width=310,height=80,noopener'); return false;",
                    ]) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
</tbody>

</table>

</div>


<!-- ===================================================== -->
<!-- DATATABLE -->
<!-- ===================================================== -->
<?= $this->Html->script([
    'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
    'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
], ['block' => true]) ?>
<?php $this->append('script'); ?>
<script>
jQuery(function($){
    $('#callsTable').DataTable({
        autoWidth: false,
        pageLength: 20,
        lengthChange: false,
        order: [[0, 'asc']],
        language: {url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Japanese.json'},
        dom: '<"row"<"col-sm-6"f><"col-sm-6 text-right"B>>t<"row"<"col-sm-6"i><"col-sm-6"p>>',
        buttons: [{
            extend: 'excelHtml5',
            text: 'Excel DL',
            title: <?= json_encode('コール履歴_' . date('YmdHi')) ?>,
            exportOptions: {columns: [0, 3, 5, 6, 8, 9, 13, 14, 16, 17, 21]}
        }]
    });
});
</script>
<?php $this->end(); ?>
