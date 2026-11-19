<div class="panel panel-primary filterable">
    <div class="panel-heading">
        <h3 class="panel-title">ステータス詳細</h3>
    </div>
    <table class="table table-hover">
        <tbody>
            <tr>
                <td width="20%" style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">ID</td>
                <td><?= h($mtStatus->id) ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">名称</td>
                <td><?= h($mtStatus->item_name) ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">状態</td>
                <td><?= h($activeOpt[((int)($mtStatus->{$activeField ?? 'active'} ?? 0))] ?? '') ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">登録日時</td>
                <td><?= h($mtStatus->created) ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">登録者</td>
                <td><?= h($opeOpt[$mtStatus->created_user_id] ?? '-') ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">更新日時</td>
                <td><?= h($mtStatus->updated) ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#149c82">更新者</td>
                <td><?= h($opeOpt[$mtStatus->updated_user_id] ?? '-') ?></td>
            </tr>
        </tbody>
    </table>
    <div align="center" class="btn_margin">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn-default btn-xs" style="width:150px;height:30px;">一覧</a>
        <a href="<?= $this->Url->build(['action' => 'edit', $mtStatus->id]) ?>" class="btn-success btn-xs" style="width:150px;height:30px;">編集</a>
        <?= $this->Form->postLink('削　除', ['action' => 'delete', $mtStatus->id], [
            'confirm' => 'ステータスを削除します。よろしいですか？',
            'class' => 'btn-danger btn-xs',
            'style' => 'width:150px;height:30px;',
        ]) ?>
    </div>
</div>
