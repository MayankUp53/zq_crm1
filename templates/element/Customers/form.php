<?php
$isEdit = $isEdit ?? false;
$heading = $isEdit ? '顧客詳細情報 更新' : '顧客詳細情報新規登録';
$currentUser = $this->request->getAttribute('identity');
$userAuth = $currentUser ? $currentUser->getOriginalData()->auth : null;
$editRows = [
    [['client_code', 'キー＊', 100], ['base_name', '拠点', 30]],
    [['book_name', '得意先名(帳合先名)', 60], ['client_branch_code', '複数口座CD', 50]],
    [['reserve1', '得意先CD', 50], ['reserve2', '帳合先・納品先CD', 50]],
    [['addr1', '住所1', 50], ['addr2', '住所2', 75]],
    [['fax', 'FAX', 10], ['tel', 'TEL＊', 12]],
    [['sales_staff_cd', '営業員CD', 10], ['sub_phone1', '顧客の担当者1電話', 12]],
    [['sales_rep_name', '営業担当者名', 20], ['sub_phone2', 'サブ電話番号2', 20]],
    [['closing_date', '締日', null], ['sub_phone3', 'サブ電話番号3', null]],
    [['collection_cls', '回収区分', null], ['sub_phone4', 'サブ電話番号4', null]],
    [['direct_cls', 'ベッツダイレクト区分', 20], ['sub_phone5', 'サブ電話番号5', 20]],
    [['remarks1', '備考1（受注時摘要）', 50], ['remarks2', '備考２（出荷時摘要）', 50]],
    [['notes3', '備考3', 50], ['notes4', '備考4', 50]],
    [['remarks5', '備考5', 50], ['remarks6', '備考6', 50]],
];
$rows = $editRows;
$buttonStyle = 'width:150px;' . ($isEdit ? '' : 'height:30px;') . 'cursor:pointer';
$this->Form->setTemplates(['inputContainer' => '{{content}}']);
?>
<div class="panel <?= $isEdit ? 'panel-warning' : 'panel-info' ?> customer-form">
    <div class="panel-heading">
        <h3 class="panel-title"><?= h($heading) ?></h3>
    </div>
    <?= $this->Form->create($customer, ['url' => $isEdit ? ['action' => 'edit', $customer->id] : ['action' => 'add'], 'id' => 'customerForm', 'novalidate' => true]) ?>
    <?php if ($isEdit): ?>
        <?= $this->Form->control('id', ['type' => 'hidden']) ?>
    <?php endif; ?>
    <table class="table">
        <colgroup><col style="width:12%"><col style="width:20%"><col style="width:12%"><col style="width:20%"></colgroup>
        <tbody>
            <?php foreach ($rows as $fields): ?>
                <tr>
                    <?php foreach ($fields as [$field, $label, $maxLength]): ?>
                        <?php
                        $isTextarea = str_starts_with($field, 'notes')
                            || str_starts_with($field, 'remarks')
                            || in_array($field, ['reserve1', 'reserve2', 'reserve5', 'summary', 'factory_desc'], true);
                        $options = [
                            'label' => false,
                            'type' => $isTextarea ? 'textarea' : 'text',
                            'class' => 'form-control',
                            'style' => 'width:100%;',
                            'required' => in_array($field, ['client_code', 'tel'], true),
                        ];
                        if ($maxLength !== null) {
                            $options['maxlength'] = $maxLength;
                        }
                        if ($isTextarea) {
                            $options += ['rows' => 3, 'cols' => 5];
                        }
                        ?>
                        <td class="customer-form-label" style="vertical-align:middle;color:#ffffff;background-color:<?= $isEdit ? '#ff8c00' : '#4169E1' ?>;"><?= h($label) ?></td>
                        <td><?= $this->Form->control($field, $options) ?></td>
                    <?php endforeach; ?>
                    <?php if (count($fields) === 1): ?><td colspan="2"></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="width:95%;margin-bottom:5px;margin-top:5px;" align="center" class="btn_margin">
        <?php if ($isEdit && $userAuth !== null && (string)$userAuth !== '0'): ?>
            <?= $this->Form->button('削　除', ['name' => 'delete', 'value' => '1', 'type' => 'submit', 'formnovalidate' => true, 'style' => $buttonStyle, 'class' => 'btn btn-danger btn-xs', 'onclick' => 'return confirm("顧客情報を削除します。よろしいですか？")']) ?>
        <?php endif; ?>
        <?= $this->Form->button('キャンセル', ['name' => 'back', 'value' => '1', 'type' => 'submit', 'formnovalidate' => true, 'style' => $buttonStyle, 'class' => $isEdit ? 'btn btn-default btn-xs' : 'btn-default btn-xs']) ?>
        <?= $this->Form->button($isEdit ? '更　新' : '登　録', ['name' => 'add', 'value' => '1', 'type' => 'submit', 'style' => $buttonStyle, 'class' => $isEdit ? 'btn btn-warning btn-xs' : 'btn-info btn-xs']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && jQuery.fn.datepicker) {
            jQuery('.customer-form .datepicker2').datepicker({
                changeMonth: true,
                changeYear: true,
                yearRange: '-100:+0',
                dateFormat: 'yymmdd'
            });
        }
        document.querySelectorAll('#customerForm input:not([type="submit"])').forEach(function (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
        });
    });
</script>
