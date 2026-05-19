<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/layout.php';

// ---- ダミーデータ ------------------------------------------------
// 比較元 (A) と 比較先 (B) の登録データを模擬する。
// 実際の実装では load_forecast() + register_name を2つ受け取る想定。

$registerA = 'sample-2026前半';
$registerB = 'sample-2026後半修正版';

$monthLabels = ['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'];

// jobs[ジョブ名][月][A|B][uriage|syauri]
// null = 一方にしか存在しない
$jobs = [
  'MSインフラ統合' => [
    '4月'  => ['A' => [1850000, 1850000], 'B' => [1850000, 1850000]],
    '5月'  => ['A' => [1850000, 1850000], 'B' => [2100000, 2100000]], // B 増加
    '6月'  => ['A' => [1850000, 1850000], 'B' => [1850000, 1850000]],
    '7月'  => ['A' => [0,       0],       'B' => [0,       0]],
    '8月'  => ['A' => [0,       0],       'B' => [0,       0]],
    '9月'  => ['A' => [0,       0],       'B' => [0,       0]],
    '10月' => ['A' => [0,       0],       'B' => [0,       0]],
    '11月' => ['A' => [0,       0],       'B' => [0,       0]],
    '12月' => ['A' => [0,       0],       'B' => [0,       0]],
    '1月'  => ['A' => [0,       0],       'B' => [0,       0]],
    '2月'  => ['A' => [0,       0],       'B' => [0,       0]],
    '3月'  => ['A' => [0,       0],       'B' => [0,       0]],
  ],
  '大学館WEB制作' => [
    '4月'  => ['A' => [500000, 500000], 'B' => [500000, 500000]],
    '5月'  => ['A' => [500000, 500000], 'B' => [500000, 500000]],
    '6月'  => ['A' => [500000, 500000], 'B' => [300000, 300000]], // B 減少
    '7月'  => ['A' => [0,      0],      'B' => [0,      0]],
    '8月'  => ['A' => [0,      0],      'B' => [0,      0]],
    '9月'  => ['A' => [0,      0],      'B' => [0,      0]],
    '10月' => ['A' => [0,      0],      'B' => [0,      0]],
    '11月' => ['A' => [0,      0],      'B' => [0,      0]],
    '12月' => ['A' => [0,      0],      'B' => [0,      0]],
    '1月'  => ['A' => [0,      0],      'B' => [0,      0]],
    '2月'  => ['A' => [0,      0],      'B' => [0,      0]],
    '3月'  => ['A' => [0,      0],      'B' => [0,      0]],
  ],
  '新規Bのみジョブ' => [  // B にしか存在しないジョブ
    '4月'  => ['A' => null, 'B' => [900000, 800000]],
    '5月'  => ['A' => null, 'B' => [900000, 800000]],
    '6月'  => ['A' => null, 'B' => [0,      0]],
    '7月'  => ['A' => null, 'B' => [0,      0]],
    '8月'  => ['A' => null, 'B' => [0,      0]],
    '9月'  => ['A' => null, 'B' => [0,      0]],
    '10月' => ['A' => null, 'B' => [0,      0]],
    '11月' => ['A' => null, 'B' => [0,      0]],
    '12月' => ['A' => null, 'B' => [0,      0]],
    '1月'  => ['A' => null, 'B' => [0,      0]],
    '2月'  => ['A' => null, 'B' => [0,      0]],
    '3月'  => ['A' => null, 'B' => [0,      0]],
  ],
  'Aのみ撤退案件' => [ // A にしか存在しないジョブ
    '4月'  => ['A' => [300000, 250000], 'B' => null],
    '5月'  => ['A' => [300000, 250000], 'B' => null],
    '6月'  => ['A' => [0,      0],      'B' => null],
    '7月'  => ['A' => [0,      0],      'B' => null],
    '8月'  => ['A' => [0,      0],      'B' => null],
    '9月'  => ['A' => [0,      0],      'B' => null],
    '10月' => ['A' => [0,      0],      'B' => null],
    '11月' => ['A' => [0,      0],      'B' => null],
    '12月' => ['A' => [0,      0],      'B' => null],
    '1月'  => ['A' => [0,      0],      'B' => null],
    '2月'  => ['A' => [0,      0],      'B' => null],
    '3月'  => ['A' => [0,      0],      'B' => null],
  ],
];

// ---- ヘルパー ---------------------------------------------------
$fmt = static fn(?int $v): string => $v === null ? '—' : number_format($v, 0, '.', ',');

/** A と B の値を比べてdiffクラスを返す */
$diffClass = static function (?array $a, ?array $b, int $idx): string {
  $av = $a !== null ? $a[$idx] : null;
  $bv = $b !== null ? $b[$idx] : null;

  if ($av === null && $bv === null) return '';
  if ($av === null) return 'diff-added';
  if ($bv === null) return 'diff-removed';
  if ($av === $bv) return 'diff-same';
  return $bv > $av ? 'diff-up' : 'diff-down';
};

/** 差分値テキスト */
$diffText = static function (?array $a, ?array $b, int $idx): string {
  $av = $a !== null ? $a[$idx] : null;
  $bv = $b !== null ? $b[$idx] : null;

  if ($av === null || $bv === null) return '';
  $diff = $bv - $av;
  if ($diff === 0) return '';
  return ($diff > 0 ? '+' : '') . number_format($diff, 0, '.', ',');
};

// ---- ジョブを「差分あり」「新規・削除」に分類 ------------------
$jobsChanged      = [];
$jobsAddedRemoved = [];

foreach ($jobs as $jobName => $months) {
  $hasA = false; $hasB = false;
  foreach ($months as $m) {
    if ($m['A'] !== null) $hasA = true;
    if ($m['B'] !== null) $hasB = true;
  }
  if ($hasA && $hasB) {
    $hasDiff = false;
    foreach ($months as $m) {
      for ($idx = 0; $idx <= 1; $idx++) {
        if (($m['A'][$idx] ?? null) !== ($m['B'][$idx] ?? null)) { $hasDiff = true; break 2; }
      }
    }
    if ($hasDiff) $jobsChanged[$jobName] = $months;
  } else {
    $jobsAddedRemoved[$jobName] = $months;
  }
}

render_page_start('FORECASTER | 比較テスト', '/assets/css/compare_test.css', 'view', 'compare-test-page');
?>
  <div class="wrap">
    <div class="panel">
      <h1>登録データ比較 <span class="test-badge">TEST</span></h1>

      <div class="compare-legend">
        <span class="legend-item diff-up">増加</span>
        <span class="legend-item diff-down">減少</span>
        <span class="legend-item diff-added">Bで追加</span>
        <span class="legend-item diff-removed">Aのみ（削除）</span>
      </div>

      <div class="compare-header-bar">
        <div class="compare-register-label">
          <span class="register-badge badge-a">A</span>
          <span><?= htmlspecialchars($registerA, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="compare-vs">vs</div>
        <div class="compare-register-label">
          <span class="register-badge badge-b">B</span>
          <span><?= htmlspecialchars($registerB, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>

      <?php
        // ---- カードレンダラー ----------------------------------------
        $renderJobCard = static function (
          string $jobName,
          array  $months,
          callable $diffClass,
          callable $diffText,
          callable $fmt,
          array $monthLabels
        ): void {
          $onlyA = true; $onlyB = true;
          foreach ($months as $m) {
            if ($m['A'] !== null) $onlyB = false;
            if ($m['B'] !== null) $onlyA = false;
          }

          $cardClass = $onlyA ? 'job-card job-card--only-a'
                     : ($onlyB ? 'job-card job-card--only-b'
                     : 'job-card job-card--changed');

          echo '<div class="' . $cardClass . '">';
          echo '<div class="job-card-header">';
          echo '<span class="job-card-name">' . htmlspecialchars($jobName, ENT_QUOTES, 'UTF-8') . '</span>';
          if ($onlyA) echo '<span class="job-badge badge-only-a">Aのみ（削除）</span>';
          if ($onlyB) echo '<span class="job-badge badge-only-b">Bで追加</span>';
          if (!$onlyA && !$onlyB) echo '<span class="job-badge badge-changed">差分あり</span>';
          echo '</div>';

          echo '<div class="job-card-body">';
          echo '<div class="job-table-wrap"><table class="job-diff-table">';
          echo '<thead><tr><th class="col-metric"></th><th class="col-ver">区</th>';
          foreach ($monthLabels as $ml) {
            echo '<th>' . $ml . '</th>';
          }
          echo '</tr></thead><tbody>';

          foreach (['売上' => 0, '社売' => 1] as $metricLabel => $metricIdx) {
            // A 行
            if (!$onlyB) {
              echo '<tr class="row-ver-a">';
              echo '<td class="col-metric" rowspan="' . (!$onlyA && !$onlyB ? 3 : 1) . '">' . $metricLabel . '</td>';
              echo '<td class="col-ver side-a"><span class="register-badge-sm badge-a">A</span></td>';
              foreach ($monthLabels as $ml) {
                $cellA = $months[$ml]['A'];
                $cellB = $months[$ml]['B'];
                $cls   = $onlyA ? '' : $diffClass($cellA, $cellB, $metricIdx);
                $val   = $cellA !== null ? $fmt($cellA[$metricIdx]) : '—';
                echo '<td class="amount-cell ' . $cls . '">' . $val . '</td>';
              }
              echo '</tr>';
            }

            // B 行
            if (!$onlyA) {
              echo '<tr class="row-ver-b">';
              if ($onlyB) {
                echo '<td class="col-metric">' . $metricLabel . '</td>';
              }
              echo '<td class="col-ver side-b"><span class="register-badge-sm badge-b">B</span></td>';
              foreach ($monthLabels as $ml) {
                $cellA = $months[$ml]['A'];
                $cellB = $months[$ml]['B'];
                $cls   = $onlyB ? '' : $diffClass($cellA, $cellB, $metricIdx);
                $val   = $cellB !== null ? $fmt($cellB[$metricIdx]) : '—';
                echo '<td class="amount-cell ' . $cls . '">' . $val . '</td>';
              }
              echo '</tr>';
            }

            // 差分行（両方ある場合のみ）
            if (!$onlyA && !$onlyB) {
              echo '<tr class="row-diff">';
              echo '<td class="col-ver diff-ver-label">差分</td>';
              foreach ($monthLabels as $ml) {
                $cellA = $months[$ml]['A'];
                $cellB = $months[$ml]['B'];
                $cls   = $diffClass($cellA, $cellB, $metricIdx);
                $dt    = $diffText($cellA, $cellB, $metricIdx);
                echo '<td class="amount-cell diff-delta ' . $cls . '">' . ($dt !== '' ? $dt : '') . '</td>';
              }
              echo '</tr>';
            }

            // 指標間の区切り（売上の後）
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
      <div class="group-header group-header--addrem">新規・削除 <span class="group-count"><?= count($jobsAddedRemoved) ?>件</span></div>
      <div class="job-cards">
        <?php foreach ($jobsAddedRemoved as $jobName => $months): ?>
          <?php $renderJobCard($jobName, $months, $diffClass, $diffText, $fmt, $monthLabels); ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="nav">
        <a href="/view/index.php">一覧へ戻る</a>
      </div>
    </div>
  </div>
  <?php render_page_end(); ?>
