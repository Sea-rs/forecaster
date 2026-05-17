<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/storage.php';
require_once dirname(__DIR__) . '/src/layout.php';

$message = '';
$error = '';
$tz = new DateTimeZone('Asia/Tokyo');
$defaultRegisterYear = (int)(new DateTimeImmutable('now', $tz))->format('Y');
$registerName = '';
$showSuccessModal = false;
$successViewUrl = '/view/index.php';

$now = new DateTimeImmutable('now', $tz);
$fiscalStart = new DateTimeImmutable($now->format('Y') . '-04-01 00:00:00', $tz);
if ($now < $fiscalStart) {
  $defaultRegisterYear -= 1;
}

$registerYear = $defaultRegisterYear;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $registerYear = (int)($_POST['register_year'] ?? 0);
  $registerName = trim((string)($_POST['register_name'] ?? ''));

  if (!preg_match('/^\d{4}$/', (string)$registerYear)) {
    $error = '登録年度は4桁の西暦で入力してください。';
  } elseif ($registerName === '') {
    $error = '登録名を入力してください。';
  } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $error = 'CSVファイルのアップロードに失敗しました。';
  } else {
    $tmpPath = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($tmpPath, 'rb');

    if ($handle === false) {
      $error = 'CSVファイルを開けませんでした。';
    } else {
      $headers = null;
      $rows = [];

      while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if ($headers === null) {
          $headers = $line;
          continue;
        }

        if (count(array_filter($line, fn($v) => trim((string)$v) !== '')) === 0) {
          continue;
        }

        $record = [];
        foreach ($headers as $i => $key) {
          $column = trim((string)$key);
          if ($column === '') {
            $column = 'column_' . $i;
          }
          $record[$column] = $line[$i] ?? '';
        }

        $rows[] = $record;
      }

      fclose($handle);

      if ($headers === null) {
        $error = 'CSVにヘッダー行が見つかりませんでした。';
      } else {
        $saved = save_named_forecast_records($registerYear, $registerName, $rows);

        if ($saved) {
          $message = count($rows) . '件のデータを取り込みました。（年度: ' . $registerYear . ' / 登録名: ' . $registerName . '）';
          $showSuccessModal = true;
          $successViewUrl = '/view/list.php?year=' . (int)$registerYear . '&register_name=' . rawurlencode($registerName);
          $registerYear = $defaultRegisterYear;
          $registerName = '';
        } else {
          $error = 'JSON保存に失敗しました。';
        }
      }
    }
  }
}

render_page_start('FORECASTER | CSV取り込み', '/assets/css/import.css', 'import');
?>
  <div class="wrap">
    <div class="panel">
      <h1>CSV取り込み</h1>

      <?php if ($error !== ''): ?>
      <div class="msg ng"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <label for="register_year">登録年度</label><br>
        <input id="register_year" name="register_year" type="number" min="1900" max="9999" step="1" value="<?= htmlspecialchars((string)$registerYear, ENT_QUOTES, 'UTF-8') ?>" required>
        <br>

        <label for="register_name">登録名</label><br>
        <input id="register_name" name="register_name" type="text" maxlength="100" value="<?= htmlspecialchars($registerName, ENT_QUOTES, 'UTF-8') ?>" required>
        <br>

        <label for="csv_file">CSVファイルを選択</label><br>
        <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required>
        <br>
        <button type="submit">取り込む</button>
      </form>


    </div>
  </div>

  <?php if ($showSuccessModal): ?>
  <div id="import-success-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-card">
      <div class="modal-header">
        <h2 id="modal-title">取り込み完了</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="閉じる">×</button>
      </div>
      <p class="msg ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <div class="modal-nav">
        <a href="<?= htmlspecialchars($successViewUrl, ENT_QUOTES, 'UTF-8') ?>">今回の登録データを見る</a>
        <a href="/view/index.php">登録名一覧へ</a>
        <a href="/">TOPへ</a>
      </div>
    </div>
  </div>
  <script src="/assets/js/common.js"></script>
  <script>
    (() => {
      const modal = document.getElementById('import-success-modal');
      if (!modal || !window.ForecasterUI) {
        return;
      }

      window.ForecasterUI.bindModal(modal);
    })();
  </script>
  <?php endif; ?>
<?php render_page_end(); ?>
