<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/storage.php';
require_once dirname(__DIR__, 2) . '/src/layout.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $deleteYear = (int)($_POST['delete_year'] ?? 0);
  $deleteRegisterName = trim((string)($_POST['delete_register_name'] ?? ''));

  if ($deleteYear <= 0 || $deleteRegisterName === '') {
    $error = '削除対象の年度または登録名が不正です。';
  } elseif (delete_named_forecast_register($deleteYear, $deleteRegisterName)) {
    $message = '登録データを削除しました。（年度: ' . $deleteYear . ' / 登録名: ' . $deleteRegisterName . '）';
  } else {
    $error = '削除に失敗しました。対象データが存在しないか、保存処理でエラーが発生しました。';
  }
}

$registersByYear = [];
$files = array_reverse(load_forecast_index());

foreach ($files as $fileName) {
  if (!preg_match('/^(\d{4})_forecast\.json$/', (string)$fileName, $matches)) {
    continue;
  }

  $year = (int)$matches[1];
  $forecast = load_forecast($year);

  foreach (array_reverse($forecast, true) as $registerName => $jobs) {
    if (is_array($jobs) && $registerName !== '') {
      if (!isset($registersByYear[$year])) {
        $registersByYear[$year] = [];
      }
      $registersByYear[$year][(string)$registerName] = true;
    }
  }
}

render_page_start('FORECASTER | View Index', '/assets/css/view.css', 'view');

$registerCount = 0;
foreach ($registersByYear as $names) {
  $registerCount += count($names);
}
?>
<div class="wrap">
  <div class="panel">
    <h1>登録名一覧</h1>
    <p class="muted">登録名件数: <?= $registerCount ?> 件</p>
    <p class="muted"><a href="/diff/index.php">差分比較ページへ移動</a></p>

    <?php if ($message !== ''): ?>
    <div class="msg ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
    <div class="msg ng"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (count($registersByYear) === 0): ?>
    <p>まだデータがありません。CSV取り込みページから登録してください。</p>
    <?php else: ?>
    <?php foreach ($registersByYear as $year => $names): ?>
    <div class="year-section">
      <h3><?= (int)$year ?> 年度</h3>
      <div class="register-list">
        <?php foreach (array_keys($names) as $name): ?>
        <div class="register-item-row">
          <a href="/view/list.php?year=<?= (int)$year ?>&register_name=<?= urlencode($name) ?>" class="register-item">
            <span class="register-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="register-arrow">→</span>
          </a>
          <form method="post" class="delete-form" onsubmit="return confirm('<?= htmlspecialchars((int)$year . '年度 / ' . $name . ' を削除します。よろしいですか？', ENT_QUOTES, 'UTF-8') ?>');">
            <input type="hidden" name="delete_year" value="<?= (int)$year ?>">
            <input type="hidden" name="delete_register_name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="danger-btn">削除</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>
<?php render_page_end(); ?>