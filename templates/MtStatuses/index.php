<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || !jQuery.fn.sortable) {
        return;
    }
    jQuery('#jquery-ui-sortable').sortable();
    jQuery('#jquery-ui-sortable').disableSelection();
    jQuery('#submitSortable').on('click', function () {
        var itemIDs = '';
        jQuery('#jquery-ui-sortable li').map(function () {
            itemIDs += jQuery(this).children('span.status-id').text() + '-';
        });
        if (window.confirm('この順番で更新します。よろしいですか？')) {
            window.location.href = <?= json_encode((string)$this->Url->build(['action' => 'sortupdate', ''])) ?> + itemIDs;
        }
    });
});
</script>
<style>
#jquery-ui-sortable {
    list-style-type: none;
    margin: 0;
    padding: 0;
    width: 50%;
}
#jquery-ui-sortable li {
    margin: 0 3px 3px 3px;
    padding: 0.3em 0.7em;
    padding-left: 1em;
    font-size: 15px;
    font-weight: bold;
    cursor: move;
    border-radius: 4px;
}
</style>

<div class="panel panel-primary filterable">
    <div class="panel-heading">
        <h3 class="panel-title">ステータス一覧</h3>
    </div>
    <br>
    <ul id="jquery-ui-sortable">
        <?php foreach ($mtStatuses as $row): ?>
            <?php $isActive = (int)($row['active_value'] ?? 0) === 1; ?>
            <li class="<?= $isActive ? 'ui-state-default' : '' ?>"<?= $isActive ? '' : ' style="background-color:#dcdcdc;"' ?>>
                <span class="status-id"><?= h($row['id']) ?></span>&nbsp;&nbsp;&nbsp;
                <?= h($row['item_name']) ?>&nbsp;&nbsp;&nbsp;(<?= h($activeOpt[$row['active_value']] ?? '') ?>)
                <button type="button"
                    onclick="location.href='<?= $this->Url->build(['action' => 'edit', $row['id']]) ?>';"
                    class="btn-success btn-xs"
                    style="float:right;margin-top:-2px;">編集</button>
            </li>
        <?php endforeach; ?>
    </ul>
    <div style="width:50%;" align="center" class="btn_margin">
        <button type="button" style="height:30px;width:150px;cursor:pointer;" onclick="location.href='<?= $this->Url->build('/settings') ?>';" class="btn-default btn-xs">戻る</button>
        <button type="button" onclick="location.href='<?= $this->Url->build(['action' => 'add']) ?>';" class="btn-info btn-xs" style="width:150px;height:30px;cursor:pointer">新規登録</button>
        <input type="button" id="submitSortable" value="表示順序を変更" class="btn-default btn-xs" style="width:150px;height:30px;">
    </div>
</div>
