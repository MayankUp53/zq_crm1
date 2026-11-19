<div class="panel panel-warning filterable">
    <div class="panel-heading">
        <h3 class="panel-title">ステータス更新</h3>
    </div>

    <?= $this->Form->create($entry, ['url' => ['action' => 'edit', $entry->id]]) ?>
    <?= $this->Form->control('id', ['type' => 'hidden']) ?>

    <table class="table table-hover">
        <tbody>
            <tr>
                <td width="20%" style="vertical-align:middle;color:#ffffff;" bgcolor="#ff8c00">*名称</td>
                <td>
                    <?= $this->Form->control('item_name', [
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true
                    ]) ?>
                </td>
                <td style="vertical-align:middle;color:#ffffff;" bgcolor="#ff8c00">状態</td>
                        <td>
                            <?= $this->Form->control(($activeField ?? 'active'), [
                        'label' => false,
                        'class' => 'form-control',
                        'type'  => 'select',
                        'options' => ['1' => '有効', '0' => '無効'],
                        'empty' => '-- 選択してください --',
                                'value' => $entry->{$activeField ?? 'active'} ?? 0
                    ]) ?>
                </td>
            </tr>
            <tr>
                <td bgcolor="#ff8c00">登録日時</td>
                <td><?= h($entry->created) ?></td>
                <td bgcolor="#ff8c00">登録者</td>
                <td><?= h($opeOpt[$entry->created_user_id] ?? '-') ?></td>
            </tr>
            <tr>
                <td bgcolor="#ff8c00">更新日時</td>
                <td><?= h($entry->updated) ?></td>
                <td bgcolor="#ff8c00">更新者</td>
                <td><?= h($opeOpt[$entry->updated_user_id] ?? '-') ?></td>
            </tr>
        </tbody>
    </table>

    <div align="center" class="btn_margin">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn-default btn-xs" style="width:150px;height:30px;">キャンセル</a>
        <?= $this->Form->button('更　新', [
            'class' => 'btn-warning btn-xs', 
            'style' => 'width:150px;height:30px;cursor:pointer'
        ]) ?>
    </div>

    <?= $this->Form->end() ?>
</div>
