<div class="panel panel-primary filterable">
    <div class="panel-heading">
        <h3 class="panel-title">種別詳細</h3>
    </div>
    <table class="table table-hover">
        <tbody>
            <tr>
                <td width="20%" style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">ID</td>
                <td><?= h($entry->id) ?></td>
                <td width="20%" style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">表示順</td>
                <td><?= h($entry->disp_cd) ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">名称</td>
                <td><?= h($entry->item_name) ?></td>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">有効無効</td>
                <td><?= h($activeOpt[$entry->active_flg] ?? '') ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">備考</td>
                <td colspan="3"><?= nl2br(h($entry->item_memo ?? '')) ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">登録日時</td>
                <td><?= h($entry->created) ?></td>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">登録者</td>
                <td><?= h($opeOpt[$entry->created_user_id] ?? '-') ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">更新日時</td>
                <td><?= h($entry->updated) ?></td>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">更新者</td>
                <td><?= h($opeOpt[$entry->updated_user_id] ?? '-') ?></td>
            </tr>
        </tbody>
    </table>
    <div align="center" class="btn_margin">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn-default btn-xs" style="width:150px;height:30px;">一覧</a>
        <a href="<?= $this->Url->build(['action' => 'edit', $entry->id]) ?>" class="btn-success btn-xs" style="width:150px;height:30px;">編集</a>
        <?= $this->Form->postLink('削　除', ['action' => 'delete', $entry->id], [
            'confirm' => '種別を削除します。よろしいですか？',
            'class' => 'btn-danger btn-xs',
            'style' => 'width:150px;height:30px;',
        ]) ?>
    </div>
</div>
