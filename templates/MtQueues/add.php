<div class="panel panel-info filterable">
    <div class="panel-heading">
        <h3 class="panel-title">種別リスト新規登録</h3>
    </div>
    <?= $this->Form->create($entry, ['url' => ['action' => 'add']]) ?>
    <table class="table table-hover">
        <tbody>
            <tr>
                <td width="20%" style="vertical-align:middle;color:#ffffff;" bgcolor="#4169E1">*名称</td>
                <td><?= $this->Form->control('item_name', ['label' => false, 'div' => false, 'class' => 'form-control']) ?></td>
                <td width="20%" style="vertical-align:middle;color:#ffffff;" bgcolor="#4169E1">備考</td>
                <td><?= $this->Form->control('item_memo', ['label' => false, 'div' => false, 'class' => 'form-control']) ?></td>
            </tr>
            <tr>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#4169E1">*有効無効</td>
                <td><?= $this->Form->control('active_flg', ['label' => false, 'div' => false, 'type' => 'select', 'options' => $activeOpt ?? [], 'empty' => '-----', 'class' => 'form-control']) ?></td>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#4169E1">表示順</td>
                <td><?= $this->Form->control('disp_cd', ['label' => false, 'div' => false, 'class' => 'form-control']) ?></td>
            </tr>
        </tbody>
    </table>
    <div align="center" class="btn_margin">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn-default btn-xs" style="width:150px;height:30px;">キャンセル</a>
        <?= $this->Form->button('登　録', ['class' => 'btn-info btn-xs', 'style' => 'width:150px;height:30px;cursor:pointer']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
