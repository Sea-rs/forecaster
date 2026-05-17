<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/storage.php';
require_once dirname(__DIR__, 2) . '/src/layout.php';

$year = (int)($_GET['year'] ?? 0);
$registerName = trim((string)($_GET['register_name'] ?? ''));

$isSaveRequest = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save';
$isApiRequest = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
  || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

if ($isSaveRequest) {
  $saveYear = (int)($_POST['save_year'] ?? 0);
  $saveSourceRegister = trim((string)($_POST['save_source_register'] ?? ''));
  $saveNewRegister = trim((string)($_POST['save_new_register'] ?? ''));
  $cellEditsJson = (string)($_POST['cell_edits'] ?? '');
  $response = [
    'ok' => false,
    'message' => '',
    'viewUrl' => '',
  ];

  if ($saveNewRegister === '') {
    $response['message'] = '登録名を入力してください。';
  } elseif ($saveNewRegister === $saveSourceRegister) {
    $response['message'] = '登録名が元と同じです。別の登録名を入力してください。';
  } else {
    $existingForecast = load_forecast($saveYear);
    if (array_key_exists($saveNewRegister, $existingForecast)) {
      $response['message'] = '登録名「' . $saveNewRegister . '」は既に存在します。別の登録名を入力してください。';
    } else {
      $cellEdits = [];
      $decoded = json_decode($cellEditsJson, true);
      if (is_array($decoded)) {
        $cellEdits = $decoded;
      }

      if (save_forecast_with_edits($saveYear, $saveSourceRegister, $saveNewRegister, $cellEdits)) {
        $response['ok'] = true;
        $response['message'] = '登録名「' . $saveNewRegister . '」として保存しました。';
        $response['viewUrl'] = '/view/list.php?year=' . $saveYear . '&register_name=' . rawurlencode($saveNewRegister);
      } else {
        $response['message'] = '保存に失敗しました。';
      }
    }
  }

  if ($isApiRequest) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
  }
}

$monthPattern = '/（(\d{4})年(\d{1,2})月分）$/u';
$fiscalMonths = ['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'];

$formatMoney = static function (string $value): string {
  $value = trim($value);
  if ($value === '') {
    return '';
  }

  $normalized = str_replace(',', '', $value);
  if (preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1) {
    return $value;
  }

  return number_format((float)$normalized, 0, '.', ',');
};

$toNumber = static function (string $value): float {
  $normalized = str_replace(',', '', trim($value));
  if (preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1) {
    return 0.0;
  }

  return (float)$normalized;
};

$statusOrder = ['固定', '按分', '変動', 'その他'];
$normalizeStatus = static function (string $status, string $jobKey): string {
  $status = trim($status);
  if (in_array($status, ['固定', '按分', '変動'], true)) {
    return $status;
  }

  if (preg_match('/-(固定|按分|変動)(?:-\d+)?$/u', $jobKey, $m) === 1) {
    return (string)$m[1];
  }

  return 'その他';
};

$rowsByStatus = [];
$monthLabels = $fiscalMonths;

if ($year > 0 && $registerName !== '') {
  $forecast = load_forecast($year);
  if (!isset($forecast[$registerName]) || !is_array($forecast[$registerName])) {
    $forecast = [];
  }

  foreach ($forecast[$registerName] ?? [] as $jobKey => $job) {
    if (!is_array($job)) {
      continue;
    }

    $jobStatus = $normalizeStatus((string)($job['job_jotai'] ?? ''), (string)$jobKey);

    $jobName = trim((string)($job['job_name'] ?? ''));
    $baseJobName = $jobName;
    $monthLabel = null;

    if ($jobName !== '' && preg_match($monthPattern, $jobName, $monthMatches) === 1) {
      $monthLabel = (int)$monthMatches[2] . '月';
      $baseJobName = trim((string)(preg_replace($monthPattern, '', $jobName) ?? $jobName));
    }

    if ($baseJobName === '') {
      $baseJobName = $jobName !== '' ? $jobName : (string)$jobKey;
    }

    if (!isset($rowsByStatus[$jobStatus])) {
      $rowsByStatus[$jobStatus] = [];
    }
    if (!isset($rowsByStatus[$jobStatus][$baseJobName])) {
      $rowsByStatus[$jobStatus][$baseJobName] = [];
    }

    if ($monthLabel === null || !in_array($monthLabel, $fiscalMonths, true)) {
      continue;
    }

    if (!array_key_exists($monthLabel, $rowsByStatus[$jobStatus][$baseJobName])) {
      $rowsByStatus[$jobStatus][$baseJobName][$monthLabel] = 0.0;
    }

    $rowsByStatus[$jobStatus][$baseJobName][$monthLabel] += $toNumber((string)($job['job_seikyu'] ?? ''));
  }

  foreach ($rowsByStatus as &$jobsByName) {
    ksort($jobsByName, SORT_NATURAL);
  }
  unset($jobsByName);
}

$jobCount = 0;
foreach ($rowsByStatus as $jobsByName) {
  $jobCount += count($jobsByName);
}

$hasData = $jobCount > 0;

render_page_start('FORECASTER | View List', '/assets/css/view.css', 'view', 'list-page');
?>
  <div class="wrap">
  <div class="panel">
    <h1>登録データ表示</h1>

    <?php if ($year <= 0 || $registerName === ''): ?>
    <p>年度と登録名を指定してください。</p>
    <?php elseif (!$hasData): ?>
    <p>データが存在しません。</p>
    <?php else: ?>
    <p class="muted">ジョブ件数: <?= $jobCount ?> 件</p>

    <div class="table-wrap">
      <table>
      <thead>
      <tr>
        <th>ジョブ名</th>
        <?php foreach ($monthLabels as $monthLabel): ?>
        <th><?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></th>
        <?php endforeach; ?>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($statusOrder as $status): ?>
        <?php $jobsByName = $rowsByStatus[$status] ?? []; ?>
        <?php if (count($jobsByName) === 0): ?>
          <?php continue; ?>
        <?php endif; ?>
        <tr class="status-separator">
          <td colspan="<?= 1 + count($monthLabels) ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php foreach ($jobsByName as $jobName => $months): ?>
        <tr>
        <td><?= htmlspecialchars((string)$jobName, ENT_QUOTES, 'UTF-8') ?></td>
        <?php foreach ($monthLabels as $monthLabel): ?>
          <?php $rawValue = (string)($months[$monthLabel] ?? '0'); ?>
          <td class="editable-amount"
            data-original-value="<?= htmlspecialchars($rawValue, ENT_QUOTES, 'UTF-8') ?>"
            data-raw-value="<?= htmlspecialchars($rawValue, ENT_QUOTES, 'UTF-8') ?>"
            data-job-name="<?= htmlspecialchars((string)$jobName, ENT_QUOTES, 'UTF-8') ?>"
            data-job-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
            data-month="<?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?>"
          ><?= htmlspecialchars($formatMoney($rawValue), ENT_QUOTES, 'UTF-8') ?></td>
        <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
      </table>
    </div>

    <div class="save-panel">
      <form id="save-form" method="post" class="save-form">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="save_year" value="<?= (int)$year ?>">
        <input type="hidden" name="save_source_register" value="<?= htmlspecialchars($registerName, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="cell_edits" id="cell-edits-input">
        <div class="save-form-row">
          <label for="save-register-name">登録名</label>
          <input type="text" id="save-register-name" name="save_new_register"
            value="<?= htmlspecialchars($registerName, ENT_QUOTES, 'UTF-8') ?>"
            maxlength="100" required>
          <button type="submit" class="primary-btn">保存</button>
        </div>
      </form>
    </div>

    <div id="save-result-modal" class="modal-backdrop is-hidden" role="dialog" aria-modal="true" aria-labelledby="save-result-title">
      <div class="modal-card">
        <div class="modal-header">
          <h2 id="save-result-title">保存結果</h2>
          <button type="button" class="modal-close" data-modal-close aria-label="閉じる">×</button>
        </div>
        <p id="save-result-message" class="msg"></p>
        <div id="save-result-links" class="modal-nav"></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="nav">
    <a href="/view/index.php">一覧へ戻る</a>
    </div>
  </div>
  </div>
    <script src="/assets/js/common.js"></script>
  <script src="/assets/js/list.js"></script>
  <?php render_page_end(); ?>