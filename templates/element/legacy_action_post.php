<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title><?= h($submitLabel) ?></title>
</head>
<body>
    <?= $this->Form->create(null, ['url' => $actionUrl, 'id' => 'legacy-action']) ?>
    <noscript><?= $this->Form->button($submitLabel, ['type' => 'submit']) ?></noscript>
    <?= $this->Form->end() ?>
    <script>document.getElementById('legacy-action').submit();</script>
</body>
</html>
