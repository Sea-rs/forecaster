<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/storage.php';
require_once dirname(__DIR__) . '/src/layout.php';

$year = (int)($_GET['year'] ?? 0);
$registerNew = trim((string)($_GET['new'] ?? ''));
$registerOld = trim((string)($_GET['old'] ?? ''));

$monthLabels = ['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'];

$toNumber = static function (string $value): float {
  $normalized = str_replace(',', '', trim($value));
  if (preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1) {
    return 0.0;
  }
  return (float)$normalized;
};

$buildRegisterJobMonths = static function (array $registerData) use ($monthLabels, $toNumber): array {
  $monthPattern = '/(\d{4})年(\d{1,2})月/u';
  $stripMonthFromJobName = static function (string $jobName) use ($monthPattern): string {
    $result = preg_replace_callback('/([（(])([^）)]*)([）)])/u', static function (array $m) use ($monthPattern): string {
      $inner = (string)$m[2];

      if (preg_match($monthPattern, $inner) !== 1) {
        return (string)$m[0];
      }

      return '';
    }, $jobName);

    $result = is_string($result) ? $result : $jobName;
    if ($result === '') {
      $result = trim((string)(preg_replace($monthPattern, '', $jobName) ?? $jobName));
    }
    $result = trim((string)(preg_replace($monthPattern, '', $result) ?? $result));
    $result = trim((string)(preg_replace('/[（(][\s_-]*[）)]/u', '', $result) ?? $result));
    return trim((string)(preg_replace('/[（）()]+$/u', '', $result) ?? $result));
  };
  $result = [];

  foreach ($registerData as $jobKey => $job) {
    if (!is_array($job)) {
      continue;
    }

    $jobName = trim((string)($job['job_name'] ?? ''));
    $baseName = $jobName;
    $monthLabel = null;

    if ($jobName !== '' && preg_match($monthPattern, $jobName, $m) === 1) {
      $monthLabel = (int)$m[2] . '月';
      $baseName = $stripMonthFromJobName($jobName);
    }

    if ($baseName === '') {
      $baseName = $jobName !== '' ? $jobName : (string)$jobKey;
    }

    if ($monthLabel === null || !in_array($monthLabel, $monthLabels, true)) {
      continue;
    }

    if (!isset($result[$baseName])) {
      $result[$baseName] = [];
    }
    if (!isset($result[$baseName][$monthLabel])) {
      $result[$baseName][$monthLabel] = ['uriage' => 0.0, 'syauri' => 0.0];
    }

    $result[$baseName][$monthLabel]['uriage'] += $toNumber((string)($job['job_uriage'] ?? ''));
    $result[$baseName][$monthLabel]['syauri'] += $toNumber((string)($job['job_syauri'] ?? ''));
  }

  ksort($result, SORT_NATURAL);
  return $result;
};

$fmt = static fn(?int $v): string => $v === null ? '—' : number_format($v, 0, '.', ',');
$fmtSigned = static fn(int $v): string => ($v > 0 ? '+' : '') . number_format($v, 0, '.', ',');

$diffClass = static function (?array $new, ?array $old, int $idx): string {
  $newValue = $new !== null ? $new[$idx] : null;
  $oldValue = $old !== null ? $old[$idx] : null;

  if ($newValue === null && $oldValue === null) return '';
  if ($oldValue === null) return 'diff-added';
  if ($newValue === null) return 'diff-removed';
  if ($newValue === $oldValue) return 'diff-same';
  return $newValue > $oldValue ? 'diff-up' : 'diff-down';
};

$diffText = static function (?array $new, ?array $old, int $idx): string {
  $newValue = $new !== null ? $new[$idx] : null;
  $oldValue = $old !== null ? $old[$idx] : null;

  if ($newValue === null || $oldValue === null) return '';
  $diff = $newValue - $oldValue;
  if ($diff === 0) return '';
  return ($diff > 0 ? '+' : '') . number_format($diff, 0, '.', ',');
};

$error = '';
$jobs = [];

if ($year <= 0 || $registerNew === '' || $registerOld === '') {
  $error = 'year / new / old のURLパラメータを指定してください。';
} else {
  $forecast = load_forecast($year);
  $newData = (array)($forecast[$registerNew] ?? []);
  $oldData = (array)($forecast[$registerOld] ?? []);

  if (count($newData) === 0 && count($oldData) === 0) {
    $error = '指定されたnew/oldの登録データが見つかりません。';
  } else {
    $newJobs = $buildRegisterJobMonths($newData);
    $oldJobs = $buildRegisterJobMonths($oldData);

    $jobNames = [];
    foreach (array_keys($newJobs) as $name) $jobNames[$name] = true;
    foreach (array_keys($oldJobs) as $name) $jobNames[$name] = true;

    if (count($jobNames) === 0) {
      $error = '比較対象のジョブが見つかりません。';
    } else {
      $allJobNames = array_keys($jobNames);
      sort($allJobNames, SORT_NATURAL);

      foreach ($allJobNames as $jobName) {
      $months = [];
      foreach ($monthLabels as $ml) {
          $newMonth = $newJobs[$jobName][$ml] ?? null;
          $oldMonth = $oldJobs[$jobName][$ml] ?? null;

          $months[$ml] = [
            'new' => $newMonth !== null ? [(int)round($newMonth['uriage']), (int)round($newMonth['syauri'])] : null,
            'old' => $oldMonth !== null ? [(int)round($oldMonth['uriage']), (int)round($oldMonth['syauri'])] : null,
          ];
        }

        $jobs[$jobName] = $months;
      }
    }
  }
}

$jobsChanged = [];
$jobsAddedRemoved = [];

foreach ($jobs as $jobName => $months) {
  $hasNew = false;
  $hasOld = false;
  foreach ($months as $m) {
    if ($m['new'] !== null) $hasNew = true;
    if ($m['old'] !== null) $hasOld = true;
  }

  if ($hasNew && $hasOld) {
    $hasDiff = false;
    foreach ($months as $m) {
      for ($idx = 0; $idx <= 1; $idx++) {
        if (($m['new'][$idx] ?? null) !== ($m['old'][$idx] ?? null)) {
          $hasDiff = true;
          break 2;
        }
      }
    }
    if ($hasDiff) $jobsChanged[$jobName] = $months;
  } else {
    $jobsAddedRemoved[$jobName] = $months;
  }
}

$totalDiffUriage = 0;
$totalDiffSyauri = 0;
foreach ($jobs as $months) {
  foreach ($months as $m) {
    $newUriage = $m['new'][0] ?? 0;
    $newSyauri = $m['new'][1] ?? 0;
    $oldUriage = $m['old'][0] ?? 0;
    $oldSyauri = $m['old'][1] ?? 0;

    $totalDiffUriage += ($newUriage - $oldUriage);
    $totalDiffSyauri += ($newSyauri - $oldSyauri);
  }
}

render_page_start('FORECASTER | Diff Compare', '/assets/css/diff_compare.css', 'diff', 'diff-compare-page');
?>
<div class="wrap">
  <div class="panel">
    <h1>登録データ比較 <span class="test-badge">DIFF</span></h1>

    <?php if ($error !== ''): ?>
    <div class="msg ng"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="compare-header-bar">
      <div class="compare-register-label">
        <span class="register-badge badge-new">new</span>
        <span><?= htmlspecialchars($registerNew, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="compare-vs">vs</div>
      <div class="compare-register-label">
        <span class="register-badge badge-old">old</span>
        <span><?= htmlspecialchars($registerOld, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>

    <?php if ($error === ''): ?>
    <div class="total-diff-summary">
      <div class="total-diff-title">増減の累計</div>
      <div class="total-diff-items">
        <div class="total-diff-item">
          <span class="total-diff-label">売上</span>
          <span class="total-diff-value <?= $totalDiffUriage > 0 ? 'diff-up' : ($totalDiffUriage < 0 ? 'diff-down' : 'diff-same') ?>"><?= htmlspecialchars($fmtSigned($totalDiffUriage), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="total-diff-item">
          <span class="total-diff-label">社売</span>
          <span class="total-diff-value <?= $totalDiffSyauri > 0 ? 'diff-up' : ($totalDiffSyauri < 0 ? 'diff-down' : 'diff-same') ?>"><?= htmlspecialchars($fmtSigned($totalDiffSyauri), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
    </div>

    <?php
      static $cardIdx = 0;

      $renderJobCard = static function (
        string $jobName,
        array $months,
        callable $diffClass,
        callable $diffText,
        callable $fmt,
        array $monthLabels
      ) use (&$cardIdx): void {
        $onlyNew = true;
        $onlyOld = true;
        foreach ($months as $m) {
          if ($m['new'] !== null) $onlyOld = false;
          if ($m['old'] !== null) $onlyNew = false;
        }

        $cardClass = $onlyNew ? 'job-card job-card--only-new'
          : ($onlyOld ? 'job-card job-card--only-old' : 'job-card job-card--changed');

        $tableId = 'job-table-' . (++$cardIdx);
        $metrics = ['売上' => 0, '社売' => 1];
        $summary = [];

        foreach ($metrics as $metricLabel => $metricIdx) {
          $totalNew = 0;
          $totalOld = 0;
          $diffMonths = 0;

          foreach ($monthLabels as $ml) {
            $newCell = $months[$ml]['new'];
            $oldCell = $months[$ml]['old'];
            $newValue = $newCell !== null ? $newCell[$metricIdx] : null;
            $oldValue = $oldCell !== null ? $oldCell[$metricIdx] : null;

            if ($newValue !== null) $totalNew += $newValue;
            if ($oldValue !== null) $totalOld += $oldValue;
            if (!$onlyNew && !$onlyOld && $newValue !== $oldValue) $diffMonths++;
          }

          $summary[$metricLabel] = [
            'totalNew' => $totalNew,
            'totalOld' => $totalOld,
            'diff' => $totalNew - $totalOld,
            'diffMonths' => $diffMonths,
          ];
        }

        echo '<div class="' . $cardClass . '">';
        echo '<div class="job-card-header">';
        echo '<span class="job-card-name">' . htmlspecialchars($jobName, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($onlyNew) echo '<span class="job-badge badge-only-new">追加</span>';
        if ($onlyOld) echo '<span class="job-badge badge-only-old">削除</span>';
        if (!$onlyNew && !$onlyOld) echo '<span class="job-badge badge-changed">差分あり</span>';
        echo '</div>';

        echo '<div class="job-card-summary">';
        if ($onlyNew || $onlyOld) {
          foreach ($summary as $metricLabel => $s) {
            $total = $onlyNew ? $s['totalNew'] : $s['totalOld'];
            echo '<div class="summary-item">';
            echo '<span class="summary-metric">' . $metricLabel . '</span>';
            echo '<span class="summary-total">' . number_format($total, 0, '.', ',') . '</span>';
            echo '</div>';
          }
        } else {
          foreach ($summary as $metricLabel => $s) {
            $diff = $s['diff'];
            $cls = $diff > 0 ? 'diff-up' : ($diff < 0 ? 'diff-down' : 'diff-same');
            $sign = $diff > 0 ? '+' : '';

            echo '<div class="summary-item">';
            echo '<span class="summary-metric">' . $metricLabel . '</span>';
            echo '<span class="summary-diff ' . $cls . '">';
            if ($diff !== 0) echo $sign . number_format($diff, 0, '.', ',');
            else echo '変化なし';
            echo '</span>';
            if ($s['diffMonths'] > 0) {
              echo '<span class="summary-months">' . $s['diffMonths'] . 'ヶ月に変動</span>';
            }
            echo '</div>';
          }
        }
        echo '</div>';

        echo '<div class="job-card-toggle" role="button" aria-expanded="false" aria-controls="' . $tableId . '">';
        echo '<span class="toggle-icon">▶</span><span class="toggle-label">詳細を表示する</span>';
        echo '</div>';

        echo '<div class="job-card-body" id="' . $tableId . '" hidden>';
        echo '<div class="job-table-wrap"><table class="job-diff-table">';
        echo '<thead><tr><th class="col-metric"></th><th class="col-ver">区</th>';
        foreach ($monthLabels as $ml) {
          echo '<th>' . $ml . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($metrics as $metricLabel => $metricIdx) {
          if (!$onlyOld) {
            echo '<tr class="row-ver-new">';
            echo '<td class="col-metric" rowspan="' . (!$onlyNew && !$onlyOld ? 3 : 1) . '">' . $metricLabel . '</td>';
            echo '<td class="col-ver side-new"><span class="register-badge-sm badge-new">new</span></td>';
            foreach ($monthLabels as $ml) {
              $newCell = $months[$ml]['new'];
              $oldCell = $months[$ml]['old'];
              $cls = $onlyNew ? '' : $diffClass($newCell, $oldCell, $metricIdx);
              $value = $newCell !== null ? $fmt($newCell[$metricIdx]) : '—';
              echo '<td class="amount-cell ' . $cls . '">' . $value . '</td>';
            }
            echo '</tr>';
          }

          if (!$onlyNew) {
            echo '<tr class="row-ver-old">';
            if ($onlyOld) {
              echo '<td class="col-metric">' . $metricLabel . '</td>';
            }
            echo '<td class="col-ver side-old"><span class="register-badge-sm badge-old">old</span></td>';
            foreach ($monthLabels as $ml) {
              $newCell = $months[$ml]['new'];
              $oldCell = $months[$ml]['old'];
              $cls = $onlyOld ? '' : $diffClass($newCell, $oldCell, $metricIdx);
              $value = $oldCell !== null ? $fmt($oldCell[$metricIdx]) : '—';
              echo '<td class="amount-cell ' . $cls . '">' . $value . '</td>';
            }
            echo '</tr>';
          }

          if (!$onlyNew && !$onlyOld) {
            echo '<tr class="row-diff">';
            echo '<td class="col-ver diff-ver-label">差分</td>';
            foreach ($monthLabels as $ml) {
              $newCell = $months[$ml]['new'];
              $oldCell = $months[$ml]['old'];
              $cls = $diffClass($newCell, $oldCell, $metricIdx);
              $text = $diffText($newCell, $oldCell, $metricIdx);
              echo '<td class="amount-cell diff-delta ' . $cls . '">' . ($text !== '' ? $text : '') . '</td>';
            }
            echo '</tr>';
          }

          if ($metricIdx === 0) {
            echo '<tr class="metric-sep"><td colspan="' . (count($monthLabels) + 2) . '"></td></tr>';
          }
        }

        echo '</tbody></table></div>';
        echo '</div></div>';
      };
    ?>

    <?php if (!empty($jobsChanged)): ?>
    <div class="group-header">差分あり <span class="group-count"><?= count($jobsChanged) ?>件</span></div>
    <div class="job-cards">
      <?php foreach ($jobsChanged as $jobName => $months): ?>
        <?php $renderJobCard($jobName, $months, $diffClass, $diffText, $fmt, $monthLabels); ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($jobsAddedRemoved)): ?>
    <div class="group-header group-header--addremove">追加 / 削除 <span class="group-count"><?= count($jobsAddedRemoved) ?>件</span></div>
    <div class="job-cards">
      <?php foreach ($jobsAddedRemoved as $jobName => $months): ?>
        <?php $renderJobCard($jobName, $months, $diffClass, $diffText, $fmt, $monthLabels); ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($jobsChanged) && empty($jobsAddedRemoved)): ?>
    <p class="muted">差分はありません。</p>
    <?php endif; ?>
    <?php endif; ?>

    <div class="nav">
      <a href="<?= htmlspecialchars(app_url('/diff/index.php'), ENT_QUOTES, 'UTF-8') ?>">比較選択へ戻る</a>
      <a href="<?= htmlspecialchars(app_url('/view/index.php'), ENT_QUOTES, 'UTF-8') ?>">登録名一覧へ</a>
    </div>
  </div>
</div>
<script src="<?= htmlspecialchars(app_url('/assets/js/common.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(app_url('/assets/js/diff-compare.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php render_page_end(); ?>
