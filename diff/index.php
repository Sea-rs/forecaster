<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/storage.php';
require_once dirname(__DIR__) . '/src/layout.php';

$registersByYear = [];
$files = array_reverse(load_forecast_index());

foreach ($files as $fileName) {
  if (!preg_match('/^(\d{4})_forecast\.json$/', (string)$fileName, $matches)) {
    continue;
  }

  $year = (int)$matches[1];
  $forecast = load_forecast($year);

  foreach (array_reverse($forecast, true) as $registerName => $jobs) {
    if (!is_array($jobs) || $registerName === '') {
      continue;
    }
    if (!isset($registersByYear[$year])) {
      $registersByYear[$year] = [];
    }
    $registersByYear[$year][(string)$registerName] = true;
  }
}

$compareRegistersByYear = [];
foreach ($registersByYear as $year => $names) {
  $yearKey = (string)$year;
  $registerNames = array_values(array_keys($names));
  $compareRegistersByYear[$yearKey] = $registerNames;
}

$defaultYear = (string)(array_key_first($compareRegistersByYear) ?? '');
$defaultRegisters = $defaultYear !== '' ? ($compareRegistersByYear[$defaultYear] ?? []) : [];
$defaultNew = (string)($defaultRegisters[0] ?? '');
$defaultOld = (string)($defaultRegisters[1] ?? ($defaultRegisters[0] ?? ''));

render_page_start('FORECASTER | Diff Index', '/assets/css/diff.css', 'diff', 'diff-index-page');
?>
<div class="wrap">
  <div class="panel">
    <h1>差分比較</h1>

    <?php if (count($compareRegistersByYear) === 0): ?>
    <p>比較対象データがありません。CSV取り込みページから登録してください。</p>
    <?php else: ?>
    <p class="muted">new と old の登録名を選択して比較します。</p>

    <form
      id="diff-select-form"
      class="diff-select-form"
      method="get"
      action="<?= htmlspecialchars(app_url('/diff/compare.php'), ENT_QUOTES, 'UTF-8') ?>"
      data-register-map="<?= htmlspecialchars((string)json_encode($compareRegistersByYear, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    >
      <label class="diff-field">
        <span>年度</span>
        <select name="year" id="diff-year" required>
          <?php foreach (array_keys($compareRegistersByYear) as $year): ?>
          <option value="<?= htmlspecialchars((string)$year, ENT_QUOTES, 'UTF-8') ?>" <?= (string)$year === $defaultYear ? 'selected' : '' ?>><?= htmlspecialchars((string)$year, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="diff-field">
        <span>new</span>
        <select name="new" id="diff-new" required>
          <?php foreach ($defaultRegisters as $name): ?>
          <option value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" <?= $name === $defaultNew ? 'selected' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="diff-field">
        <span>old</span>
        <select name="old" id="diff-old" required>
          <?php foreach ($defaultRegisters as $name): ?>
          <option value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" <?= $name === $defaultOld ? 'selected' : '' ?>><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="diff-go-btn">比較ページへ</button>
    </form>
    <?php endif; ?>

    <div class="nav">
      <a href="<?= htmlspecialchars(app_url('/view/index.php'), ENT_QUOTES, 'UTF-8') ?>">登録名一覧へ</a>
      <a href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">TOPへ</a>
    </div>
  </div>
</div>
<script src="<?= htmlspecialchars(app_url('/assets/js/common.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(app_url('/assets/js/diff-index.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php render_page_end(); ?>
