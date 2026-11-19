<?php
/**
 * Audio playback view for recorded calls.
 */
// Expected variables: $url (base API endpoint), $crtObjectId
$audioUrl = $url . urlencode(json_encode(['crtObjectId' => $crtObjectId, 'targetFormat' => 'mp3']));
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>録音再生</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { margin:0; padding:8px; font-family:sans-serif; }
    audio { width:100%; }
  </style>
</head>
<body>
  <audio id="<?= h($id) ?>" preload="auto" controls>
    <source src="<?= h($audioUrl) ?>" type="audio/mpeg">
    お使いのブラウザは音声再生に対応していません。
  </audio>

</body>
</html>
