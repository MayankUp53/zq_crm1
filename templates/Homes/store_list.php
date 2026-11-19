<?php
/**
 * Store list pop-up (reuses phone list data but only shows names).
 */
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.16/css/dataTables.bootstrap.min.css">
</head>
<body>
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">店舗一覧</h3>
        </div>
        <table id="storeList" class="table table-striped table-hover">
            <thead>
                <tr style="color:#fff;background:#20b2aa">
                    <th scope="col">名称</th>
                    <th scope="col">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mtPhonelists as $row): ?>
                    <tr>
                        <td><?= h($row->item_name) ?></td>
                        <td><button type="button" class="btn btn-xs btn-info store-select">選択</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.16/js/dataTables.bootstrap.min.js"></script>
    <script>
        var storeTable = document.getElementById('storeList');
        storeTable.addEventListener('click', function(event) {
            var button = event.target.closest('.store-select');
            if (!button) {
                return;
            }

            if (window.opener && !window.opener.closed) {
                var storeName = button.closest('tr').cells[0].textContent.trim();
                var parentDocument = window.opener.document;
                var input = parentDocument.getElementById('summary')
                    || parentDocument.getElementById('ContactSummary')
                    || parentDocument.querySelector('[name="summary"], [name="Contact[summary]"]');

                if (input) {
                    if (input.tagName === 'SELECT' && !Array.prototype.some.call(input.options, function(option) {
                        return option.value === storeName;
                    })) {
                        input.add(new window.opener.Option(storeName, storeName));
                    }
                    input.value = storeName;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
            window.close();
        });

        jQuery(function($) {
            $(storeTable).DataTable({
                dom: '<"pull-left"f><"pull-right"l>ti<"pull-left"p>',
                order: [[0, 'asc']],
                language: {url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Japanese.json'},
                columnDefs: [{ targets: 1, orderable: false, searchable: false }]
            });
        });
    </script>
</body>
</html>
