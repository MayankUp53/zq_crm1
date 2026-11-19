<div class="panel panel-info filterable">
    <div class="panel-heading">
        <h3 class="panel-title">ステータス登録</h3>
    </div>

    <?= $this->Form->create($entry, ['url' => ['action' => 'add'], 'id' => 'statusForm']) ?>

    <table class="table table-hover">
        <tbody>
            <tr>
                <td width="25%" style="vertical-align:middle;color:#ffffff;" bgcolor="#4169E1">
                    *名称
                </td>
                <td>
                    <?= $this->Form->control('item_name', [
                        'label' => false,
                        'div'   => false,
                        'class' => 'form-control',
                        'id'    => 'item_name',
                        'required' => true
                    ]) ?>
                    <?= $this->Form->error('item_name') ?>
                </td>
            </tr>

            <!-- 状態 -->
        <tr>
    <td width="25%" style="vertical-align:middle;color:#ffffff;" bgcolor="#4169E1">
        状態
    </td>
     <td>
        <?= $this->Form->control(($activeField ?? 'active'), [
            'label' => false,
            'div'   => false,
            'class' => 'form-control',
            'id'    => 'active',
            'type'  => 'select',
            'options' => ['1' => '有効', '0' => '無効'],
            'empty' => '-- 選択してください --', // empty placeholder
            'required' => true,
            'value' => $entry->id ? $entry->active : '' // force empty for new record
        ]) ?>
        <?= $this->Form->error('active') ?>
    </td>
</tr>

        </tbody>
    </table>

    <div align="center" class="btn_margin">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn-default btn-xs" style="width:150px;height:30px;">
            キャンセル
        </a>

        <?= $this->Form->button('登　録', [
            'class' => 'btn-info btn-xs', 
            'style' => 'width:150px;height:30px;cursor:pointer', 
            'id' => 'submitBtn'
        ]) ?>
    </div>

    <?= $this->Form->end() ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("statusForm");
    const submitBtn = document.getElementById("submitBtn");
    const activeControl = form.querySelector('[name="active"], [name="active_flg"]');

    submitBtn.addEventListener("click", function (e) {
        const itemName = document.getElementById("item_name").value.trim();
        const isActive = activeControl ? activeControl.value : '';

        if (itemName === "") {
            alert("名称は必須です。");
            e.preventDefault(); // stop submit
            return false;
        }

        if (isActive === "") {
            alert("状態を選択してください。");
            e.preventDefault(); // stop submit
            return false;
        }
    });
});
</script>
