<?php
declare(strict_types=1);

global $forecasterLastError;
if (!isset($forecasterLastError)) {
  $forecasterLastError = '';
}

function get_fixed_status_keywords(): array
{
  $defaultKeywords = [];
  $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'fixed_status_keywords.json';

  if (!file_exists($path)) {
    return $defaultKeywords;
  }

  $raw = file_get_contents($path);
  if ($raw === false || trim($raw) === '') {
    return $defaultKeywords;
  }

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    return $defaultKeywords;
  }

  $keywords = [];
  foreach ($decoded as $value) {
    if (!is_string($value)) {
      continue;
    }

    $keyword = trim($value);
    if ($keyword === '') {
      continue;
    }

    $keywords[] = $keyword;
  }

  if (count($keywords) === 0) {
    return $defaultKeywords;
  }

  return array_values(array_unique($keywords));
}

function get_forecaster_error(): string
{
  global $forecasterLastError;
  return $forecasterLastError;
}

function set_forecaster_error(string $message): void
{
  global $forecasterLastError;
  $forecasterLastError = $message;
}

function clear_forecaster_error(): void
{
  global $forecasterLastError;
  $forecasterLastError = '';
}

function index_file_path(): string
{
  return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'index.json';
}

function forecast_file_path(int $year): string
{
  return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $year . '_forecast.json';
}

function load_forecast(int $year): array
{
  $path = forecast_file_path($year);

  if (!file_exists($path)) {
    return [];
  }

  $raw = file_get_contents($path);
  if ($raw === false || trim($raw) === '') {
    return [];
  }

  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

function save_forecast(int $year, array $forecastData): bool
{
  $path = forecast_file_path($year);
  $dir = dirname($path);

  if (!is_dir($dir)) {
    if (!mkdir($dir, 0777, true)) {
      set_forecaster_error('ディレクトリ作成に失敗しました: ' . $path);
      return false;
    }
  }

  $json = json_encode($forecastData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) {
    set_forecaster_error('JSON エンコードに失敗しました: ' . json_last_error_msg());
    return false;
  }

  $result = file_put_contents($path, $json, LOCK_EX);
  if ($result === false) {
    set_forecaster_error('JSON ファイル保存に失敗しました: ' . $path);
    return false;
  }

  clear_forecaster_error();
  return true;
}

function load_forecast_index(): array
{
  $path = index_file_path();

  if (!file_exists($path)) {
    return [];
  }

  $raw = file_get_contents($path);
  if ($raw === false || trim($raw) === '') {
    return [];
  }

  $decoded = json_decode($raw, true);
  return is_array($decoded) ? array_values($decoded) : [];
}

function save_forecast_index(array $fileNames): bool
{
  $path = index_file_path();
  $dir = dirname($path);

  if (!is_dir($dir)) {
    if (!mkdir($dir, 0777, true)) {
      set_forecaster_error('ディレクトリ作成に失敗しました: ' . $path);
      return false;
    }
  }

  $json = json_encode(array_values($fileNames), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) {
    set_forecaster_error('JSON エンコードに失敗しました: ' . json_last_error_msg());
    return false;
  }

  $result = file_put_contents($path, $json, LOCK_EX);
  if ($result === false) {
    set_forecaster_error('インデックス ファイル保存に失敗しました: ' . $path);
    return false;
  }

  clear_forecaster_error();
  return true;
}

function register_name_exists(int $year, string $registerName): bool
{
  $forecast = load_forecast($year);
  return array_key_exists($registerName, $forecast);
}

function row_value(array $row, string $jpKey, string $enKey): string
{
  $value = $row[$jpKey] ?? $row[$enKey] ?? '';
  return trim((string)$value);
}

function normalize_job_record(array $row): array
{
  return [
    'job_code' => row_value($row, 'ジョブコード', 'job_code'),
    'job_name' => row_value($row, 'ジョブ名', 'job_name'),
    'job_seikyusaki' => row_value($row, '請求先', 'job_seikyusaki'),
    'job_keiyaku' => row_value($row, '契約形態', 'job_keiyaku'),
    'job_type' => row_value($row, 'ジョブ種別', 'job_type'),
    'job_kakudo' => row_value($row, '確度', 'job_kakudo'),
    'job_jotai' => row_value($row, '状態', 'job_jotai'),
    'job_busyo' => row_value($row, '制作カンパニー', 'job_busyo'),
    'job_start' => row_value($row, '受注予定日', 'job_start'),
    'job_end' => row_value($row, '納品予定日', 'job_end'),
    'job_seikyu' => row_value($row, '請求額', 'job_seikyu'),
    'job_uriage' => row_value($row, '売上', 'job_uriage'),
    'job_gaityuu' => row_value($row, '外注費', 'job_gaityuu'),
    'job_syauri' => row_value($row, '社売', 'job_syauri'),
    'job_genka' => row_value($row, '社内原価', 'job_genka'),
    'job_rieki' => row_value($row, '利益', 'job_rieki'),
  ];
}

function next_job_key(array $existingJobs, string $baseKey): string
{
  if (!array_key_exists($baseKey, $existingJobs)) {
    return $baseKey;
  }

  $serial = 2;
  while (array_key_exists($baseKey . '-' . $serial, $existingJobs)) {
    $serial++;
  }

  return $baseKey . '-' . $serial;
}

function save_named_forecast_records(int $year, string $registerName, array $rows): bool
{
  $data = load_forecast($year);

  $current = $data[$registerName] ?? [];
  if (!is_array($current)) {
    $current = [];
  }

  $existingJobs = [];
  if (isset($current['records']) && is_array($current['records'])) {
    // Backward-compatible migration from previous {"records": [...]} format.
    foreach ($current['records'] as $legacyRow) {
      if (!is_array($legacyRow)) {
        continue;
      }

      $normalized = normalize_job_record($legacyRow);
      $baseKey = trim($normalized['job_code'] . '-' . $normalized['job_jotai'], '-');
      if ($baseKey === '') {
        continue;
      }

      $jobKey = next_job_key($existingJobs, $baseKey);
      $existingJobs[$jobKey] = $normalized;
    }
  } else {
    foreach ($current as $jobKey => $jobRecord) {
      if (is_array($jobRecord)) {
        $existingJobs[(string)$jobKey] = $jobRecord;
      }
    }
  }

  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }

    $normalized = normalize_job_record($row);
    $baseKey = trim($normalized['job_code'] . '-' . $normalized['job_jotai'], '-');
    if ($baseKey === '') {
      continue;
    }

    $jobKey = next_job_key($existingJobs, $baseKey);
    $existingJobs[$jobKey] = $normalized;
  }

  $data[$registerName] = $existingJobs;

  if (!save_forecast($year, $data)) {
    return false;
  }

  $index = load_forecast_index();
  $fileName = $year . '_forecast.json';

  if (!in_array($fileName, $index, true)) {
    $index[] = $fileName;
  }

  return save_forecast_index($index);
}

function save_forecast_with_edits(int $year, string $sourceRegister, string $newRegister, array $cellEdits, array $addedJobs = []): bool
{
  $monthPattern = '/(\d{4})年(\d{1,2})月/u';
  $fiscalMonths = ['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'];
  $statusValues = ['固定', '按分', '変動', 'その他'];
  $fixedStatusKeywords = get_fixed_status_keywords();
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

  $toFloat = static function (string $value): float {
    $normalized = str_replace(',', '', trim($value));
    return preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) === 1 ? (float)$normalized : 0.0;
  };

  $extractJobMeta = static function (string $jobName, string $fallback) use ($monthPattern, $stripMonthFromJobName, $fiscalMonths): array {
    $baseJobName = $jobName;
    $monthLabel = null;

    if ($jobName !== '' && preg_match($monthPattern, $jobName, $m) === 1) {
      $monthLabel = (int)$m[2] . '月';
      $baseJobName = $stripMonthFromJobName($jobName);
    }

    if ($baseJobName === '') {
      $baseJobName = $jobName !== '' ? $jobName : $fallback;
    }

    if ($monthLabel !== null && !in_array($monthLabel, $fiscalMonths, true)) {
      $monthLabel = null;
    }

    return [$baseJobName, $monthLabel];
  };

  $monthNumber = static function (string $monthLabel): ?int {
    if (preg_match('/^(\d{1,2})月$/u', $monthLabel, $m) !== 1) {
      return null;
    }

    $month = (int)$m[1];
    if ($month < 1 || $month > 12) {
      return null;
    }

    return $month;
  };

  $hasFixedStatusKeyword = static function (string $jobName) use ($fixedStatusKeywords): bool {
    foreach ($fixedStatusKeywords as $keyword) {
      if ($keyword !== '' && mb_strpos($jobName, $keyword) !== false) {
        return true;
      }
    }

    return false;
  };

  $normalizeStatus = static function (string $status, string $jobKey, string $jobName = '') use ($statusValues, $hasFixedStatusKeyword): string {
    $status = trim($status);
    if ($status === '固定') {
      return '固定';
    }

    if ($jobName !== '' && $hasFixedStatusKeyword($jobName)) {
      return '固定';
    }

    if (in_array($status, $statusValues, true)) {
      return $status;
    }

    if (preg_match('/-(固定|按分|変動)(?:-\d+)?$/u', $jobKey, $m) === 1) {
      return (string)$m[1];
    }

    return 'その他';
  };

  $isMetricMap = static function (array $value): bool {
    if (count($value) === 0) {
      return false;
    }

    foreach ($value as $metric => $metricValue) {
      if (!in_array((string)$metric, ['uriage', 'syauri', 'seikyu'], true)) {
        return false;
      }
      if (!is_scalar($metricValue)) {
        return false;
      }
    }

    return true;
  };

  $isMonthMap = static function (array $value) use ($isMetricMap): bool {
    foreach ($value as $k => $v) {
      if (preg_match('/^\d{1,2}月$/u', (string)$k) !== 1) {
        return false;
      }

      if (is_scalar($v)) {
        continue;
      }

      if (is_array($v) && $isMetricMap($v)) {
        continue;
      }

      return false;
    }

    return count($value) > 0;
  };

  $normalizedEdits = [];

  $setNormalizedEdit = static function (string $status, string $jobName, string $monthLabel, string $metric, $value) use (&$normalizedEdits): void {
    if (!is_scalar($value) || !in_array($metric, ['uriage', 'syauri', 'seikyu'], true)) {
      return;
    }

    if (!isset($normalizedEdits[$status])) {
      $normalizedEdits[$status] = [];
    }
    if (!isset($normalizedEdits[$status][$jobName])) {
      $normalizedEdits[$status][$jobName] = [];
    }
    if (!isset($normalizedEdits[$status][$jobName][$monthLabel])) {
      $normalizedEdits[$status][$jobName][$monthLabel] = [];
    }

    $normalizedEdits[$status][$jobName][$monthLabel][$metric] = (float)$value;
  };

  $normalizeMonthEdits = static function (string $status, string $jobName, array $months) use ($setNormalizedEdit): void {
    foreach ($months as $monthLabel => $editedValue) {
      $monthLabel = (string)$monthLabel;
      if (preg_match('/^\d{1,2}月$/u', $monthLabel) !== 1) {
        continue;
      }

      if (is_array($editedValue)) {
        foreach ($editedValue as $metric => $metricValue) {
          $setNormalizedEdit($status, $jobName, $monthLabel, (string)$metric, $metricValue);
        }
        continue;
      }

      // 旧形式: 単一値は請求額として扱い、売上/社売へもフォールバック可能に保持する。
      $setNormalizedEdit($status, $jobName, $monthLabel, 'seikyu', $editedValue);
    }
  };

  foreach ($cellEdits as $topKey => $topValue) {
    if (!is_array($topValue)) {
      continue;
    }

    // 旧形式: { jobName: {"4月": "..."} }
    if ($isMonthMap($topValue)) {
      $normalizeMonthEdits('__legacy__', (string)$topKey, $topValue);
      continue;
    }

    // 新形式: { status: { jobName: {"4月": {"uriage": "...", "syauri": "..."}} } }
    foreach ($topValue as $jobName => $months) {
      if (!is_array($months) || !$isMonthMap($months)) {
        continue;
      }

      $normalizeMonthEdits((string)$topKey, (string)$jobName, $months);
    }
  }

  $resolveEditedTotal = static function (string $jobStatus, string $baseJobName, string $monthLabel, string $metric, float $default) use ($normalizedEdits): float {
    if (isset($normalizedEdits[$jobStatus][$baseJobName][$monthLabel][$metric])) {
      return (float)$normalizedEdits[$jobStatus][$baseJobName][$monthLabel][$metric];
    }

    if ($metric !== 'seikyu' && isset($normalizedEdits[$jobStatus][$baseJobName][$monthLabel]['seikyu'])) {
      return (float)$normalizedEdits[$jobStatus][$baseJobName][$monthLabel]['seikyu'];
    }

    if (isset($normalizedEdits['__legacy__'][$baseJobName][$monthLabel][$metric])) {
      return (float)$normalizedEdits['__legacy__'][$baseJobName][$monthLabel][$metric];
    }

    if ($metric !== 'seikyu' && isset($normalizedEdits['__legacy__'][$baseJobName][$monthLabel]['seikyu'])) {
      return (float)$normalizedEdits['__legacy__'][$baseJobName][$monthLabel]['seikyu'];
    }

    return $default;
  };

  $normalizedAddedJobs = [];
  foreach ($addedJobs as $job) {
    if (!is_array($job)) {
      continue;
    }

    $jobName = trim((string)($job['jobName'] ?? ''));
    if ($jobName === '') {
      continue;
    }

    $jobStatus = trim((string)($job['status'] ?? ''));
    if (!in_array($jobStatus, ['固定', '按分', '変動'], true)) {
      $jobStatus = 'その他';
    }

    $months = $job['months'] ?? [];
    if (!is_array($months)) {
      continue;
    }

    $normalizedMonths = [];
    foreach ($months as $monthLabel => $monthAmount) {
      $monthLabel = (string)$monthLabel;
      if (!in_array($monthLabel, $fiscalMonths, true)) {
        continue;
      }

      $uriage = 0;
      $syauri = 0;

      if (is_array($monthAmount)) {
        $uriage = (int)round($toFloat((string)($monthAmount['uriage'] ?? '0')));
        $syauri = (int)round($toFloat((string)($monthAmount['syauri'] ?? '0')));
      } else {
        $legacy = (int)round($toFloat((string)$monthAmount));
        $uriage = $legacy;
        $syauri = $legacy;
      }

      $normalizedMonths[$monthLabel] = [
        'uriage' => $uriage,
        'syauri' => $syauri,
      ];
    }

    if (count($normalizedMonths) === 0) {
      continue;
    }

    $normalizedAddedJobs[] = [
      'status' => $jobStatus,
      'jobName' => $jobName,
      'months' => $normalizedMonths,
    ];
  }

  $generateAddedJobKey = static function (string $jobStatus) use (&$existingKeys): string {
    while (true) {
      $now = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
      if ($now === false) {
        $timestamp = date('YmdHis');
      } else {
        $timestamp = $now->format('YmdHisu');
      }

      $jobKey = 'MPJ-' . $timestamp . '-' . $jobStatus;
      if (!isset($existingKeys[$jobKey])) {
        $existingKeys[$jobKey] = true;
        return $jobKey;
      }

      usleep(1);
    }
  };

  $forecast = load_forecast($year);
  $sourceJobs = $forecast[$sourceRegister] ?? [];

  if (!is_array($sourceJobs)) {
    return false;
  }

  // ジョブ名 + 月単位で行をグループ化し、編集後の合計を厳密に合わせる。
  $groups = [];
  $templatesByJob = [];
  $templatesByAnyStatus = [];
  $existingKeys = [];
  foreach ($sourceJobs as $jobKey => $job) {
    if (!is_array($job)) {
      continue;
    }

    $existingKeys[(string)$jobKey] = true;

    $jobStatus = $normalizeStatus(
      (string)($job['job_jotai'] ?? ''),
      (string)$jobKey,
      trim((string)($job['job_name'] ?? ''))
    );

    [$baseJobName, $monthLabel] = $extractJobMeta(
      trim((string)($job['job_name'] ?? '')),
      (string)$jobKey
    );

    if (!isset($templatesByJob[$jobStatus])) {
      $templatesByJob[$jobStatus] = [];
    }
    if (!isset($templatesByJob[$jobStatus][$baseJobName])) {
      $templatesByJob[$jobStatus][$baseJobName] = $job;
    }
    if (!isset($templatesByAnyStatus[$baseJobName])) {
      $templatesByAnyStatus[$baseJobName] = $job;
    }

    if ($monthLabel === null) {
      continue;
    }

    if (!isset($groups[$jobStatus])) {
      $groups[$jobStatus] = [];
    }
    if (!isset($groups[$jobStatus][$baseJobName])) {
      $groups[$jobStatus][$baseJobName] = [];
    }
    if (!isset($groups[$jobStatus][$baseJobName][$monthLabel])) {
      $groups[$jobStatus][$baseJobName][$monthLabel] = [
        'rowKeys' => [],
        'origValues' => [
          'uriage' => [],
          'syauri' => [],
        ],
        'origTotal' => [
          'uriage' => 0.0,
          'syauri' => 0.0,
        ],
      ];
    }

    $origUriage = $toFloat((string)($job['job_uriage'] ?? '0'));
    $origSyauri = $toFloat((string)($job['job_syauri'] ?? '0'));
    $groups[$jobStatus][$baseJobName][$monthLabel]['rowKeys'][] = (string)$jobKey;
    $groups[$jobStatus][$baseJobName][$monthLabel]['origValues']['uriage'][(string)$jobKey] = $origUriage;
    $groups[$jobStatus][$baseJobName][$monthLabel]['origValues']['syauri'][(string)$jobKey] = $origSyauri;
    $groups[$jobStatus][$baseJobName][$monthLabel]['origTotal']['uriage'] += $origUriage;
    $groups[$jobStatus][$baseJobName][$monthLabel]['origTotal']['syauri'] += $origSyauri;
  }

  // 元データをコピーし、新規登録名データだけ編集値を反映。
  $newJobs = [];
  foreach ($sourceJobs as $jobKey => $job) {
    if (is_array($job)) {
      $newJobs[(string)$jobKey] = $job;
    }
  }

  foreach ($groups as $jobStatus => $jobsByName) {
    foreach ($jobsByName as $baseJobName => $months) {
      foreach ($months as $monthLabel => $group) {
      $rowKeys = $group['rowKeys'];

      if (count($rowKeys) === 0) {
        continue;
      }

      foreach (['uriage', 'syauri'] as $metric) {
        $origValues = $group['origValues'][$metric] ?? [];
        $origTotal = (float)($group['origTotal'][$metric] ?? 0.0);
        $targetTotal = $resolveEditedTotal((string)$jobStatus, (string)$baseJobName, (string)$monthLabel, $metric, $origTotal);

        $assigned = [];

        if (abs($origTotal) > 0.001) {
          foreach ($rowKeys as $rowKey) {
            $origValue = (float)($origValues[$rowKey] ?? 0.0);
            $assigned[$rowKey] = (int)round($origValue * ($targetTotal / $origTotal));
          }
        } else {
          foreach ($rowKeys as $rowKey) {
            $assigned[$rowKey] = 0;
          }
        }

        // 丸め誤差を先頭行に寄せて、合計が必ず編集値になるよう補正。
        $targetRounded = (int)round($targetTotal);
        $assignedTotal = array_sum($assigned);
        $diff = $targetRounded - $assignedTotal;
        $firstRowKey = $rowKeys[0];
        $assigned[$firstRowKey] = (int)($assigned[$firstRowKey] + $diff);

        foreach ($rowKeys as $rowKey) {
          if (!isset($newJobs[$rowKey]) || !is_array($newJobs[$rowKey])) {
            continue;
          }

          if ($metric === 'uriage') {
            $newJobs[$rowKey]['job_uriage'] = (string)$assigned[$rowKey];
            continue;
          }

          $newJobs[$rowKey]['job_syauri'] = (string)$assigned[$rowKey];
        }
      }
    }
    }
  }

  // 元データに存在しない「0表示セル」の編集値を新規行として反映。
  foreach ($normalizedEdits as $jobStatus => $jobsByName) {
    if (!is_array($jobsByName)) {
      continue;
    }

    foreach ($jobsByName as $baseJobName => $months) {
      if (!is_array($months)) {
        continue;
      }

      foreach ($months as $monthLabel => $editedValue) {
        if (isset($groups[$jobStatus][$baseJobName][$monthLabel])) {
          continue;
        }
        if ($jobStatus === '__legacy__' && isset($groups['固定'][$baseJobName][$monthLabel])) {
          continue;
        }
        if ($jobStatus === '__legacy__' && isset($groups['按分'][$baseJobName][$monthLabel])) {
          continue;
        }
        if ($jobStatus === '__legacy__' && isset($groups['変動'][$baseJobName][$monthLabel])) {
          continue;
        }

        $editedByMetric = [
          'uriage' => 0,
          'syauri' => 0,
        ];

        if (is_array($editedValue)) {
          if (array_key_exists('uriage', $editedValue)) {
            $editedByMetric['uriage'] = (int)round((float)$editedValue['uriage']);
          } elseif (array_key_exists('seikyu', $editedValue)) {
            $editedByMetric['uriage'] = (int)round((float)$editedValue['seikyu']);
          }

          if (array_key_exists('syauri', $editedValue)) {
            $editedByMetric['syauri'] = (int)round((float)$editedValue['syauri']);
          } elseif (array_key_exists('seikyu', $editedValue)) {
            $editedByMetric['syauri'] = (int)round((float)$editedValue['seikyu']);
          }
        } else {
          $legacyValue = (int)round((float)$editedValue);
          $editedByMetric['uriage'] = $legacyValue;
          $editedByMetric['syauri'] = $legacyValue;
        }

        if ($editedByMetric['uriage'] === 0 && $editedByMetric['syauri'] === 0) {
          continue;
        }

        $resolvedStatus = $jobStatus === '__legacy__'
          ? $normalizeStatus(
            (string)($templatesByAnyStatus[$baseJobName]['job_jotai'] ?? ''),
            '',
            trim((string)($templatesByAnyStatus[$baseJobName]['job_name'] ?? ''))
          )
          : $jobStatus;

        $template = $templatesByJob[$resolvedStatus][$baseJobName] ?? ($templatesByAnyStatus[$baseJobName] ?? null);
        if (!is_array($template)) {
          continue;
        }

        $month = $monthNumber((string)$monthLabel);
        if ($month === null) {
          continue;
        }

        $calendarYear = $month >= 4 ? $year : $year + 1;
        $startDate = sprintf('%d/%d/1', $calendarYear, $month);
        $endDay = (int)date('t', strtotime(sprintf('%04d-%02d-01', $calendarYear, $month)));
        $endDate = sprintf('%d/%d/%d', $calendarYear, $month, $endDay);

        $newJob = $template;
        $newJob['job_jotai'] = $resolvedStatus === 'その他' ? (string)($newJob['job_jotai'] ?? '') : $resolvedStatus;
        $newJob['job_name'] = (string)$baseJobName . '（' . $calendarYear . '年' . $month . '月分）';
        $newJob['job_start'] = $startDate;
        $newJob['job_end'] = $endDate;
        $newJob['job_uriage'] = (string)$editedByMetric['uriage'];
        $newJob['job_syauri'] = (string)$editedByMetric['syauri'];
        $newJob['job_seikyu'] = (string)$editedByMetric['uriage'];

        $baseKey = trim((string)($newJob['job_code'] ?? '') . '-' . (string)($newJob['job_jotai'] ?? ''), '-');
        if ($baseKey === '') {
          $baseKey = 'job';
        }

        $newKey = next_job_key($existingKeys, $baseKey);
        $existingKeys[$newKey] = true;
        $newJobs[$newKey] = $newJob;
      }
    }
  }

  // ジョブ追加フォームで指定された新規ジョブを反映。
  foreach ($normalizedAddedJobs as $addedJob) {
    $jobStatus = (string)($addedJob['status'] ?? 'その他');
    $jobName = (string)$addedJob['jobName'];
    $months = (array)$addedJob['months'];

    foreach ($fiscalMonths as $monthLabel) {
      $monthData = $months[$monthLabel] ?? ['uriage' => 0, 'syauri' => 0];
      $uriage = (int)($monthData['uriage'] ?? 0);
      $syauri = (int)($monthData['syauri'] ?? 0);

      if ($uriage === 0 && $syauri === 0) {
        continue;
      }

      $month = $monthNumber($monthLabel);
      if ($month === null) {
        continue;
      }

      $calendarYear = $month >= 4 ? $year : $year + 1;
      $startDate = sprintf('%d/%d/1', $calendarYear, $month);
      $endDay = (int)date('t', strtotime(sprintf('%04d-%02d-01', $calendarYear, $month)));
      $endDate = sprintf('%d/%d/%d', $calendarYear, $month, $endDay);

      $newJob = [
        'job_code' => 'NEW',
        'job_name' => $jobName . '（' . $calendarYear . '年' . $month . '月分）',
        'job_seikyusaki' => '',
        'job_keiyaku' => '',
        'job_type' => '',
        'job_kakudo' => '',
        'job_jotai' => $jobStatus,
        'job_busyo' => '',
        'job_start' => $startDate,
        'job_end' => $endDate,
        'job_seikyu' => (string)$uriage,
        'job_uriage' => (string)$uriage,
        'job_gaityuu' => '0',
        'job_syauri' => (string)$syauri,
        'job_genka' => '0',
        'job_rieki' => '0',
      ];

      $newKey = $generateAddedJobKey($jobStatus);
      $newJobs[$newKey] = $newJob;
    }
  }

  $forecast[$newRegister] = $newJobs;

  if (!save_forecast($year, $forecast)) {
    return false;
  }

  $index = load_forecast_index();
  $fileName = $year . '_forecast.json';

  if (!in_array($fileName, $index, true)) {
    $index[] = $fileName;
  }

  return save_forecast_index($index);
}

function remove_forecast_index_entry(string $fileName): bool
{
  $index = load_forecast_index();
  $filtered = array_values(array_filter($index, static fn($name): bool => (string)$name !== $fileName));
  return save_forecast_index($filtered);
}

function delete_named_forecast_register(int $year, string $registerName): bool
{
  if ($year <= 0 || $registerName === '') {
    return false;
  }

  $forecast = load_forecast($year);
  if (!array_key_exists($registerName, $forecast)) {
    return false;
  }

  unset($forecast[$registerName]);

  $fileName = $year . '_forecast.json';
  $path = forecast_file_path($year);

  if (count($forecast) === 0) {
    if (file_exists($path) && !unlink($path)) {
      return false;
    }

    return remove_forecast_index_entry($fileName);
  }

  if (!save_forecast($year, $forecast)) {
    return false;
  }

  // インデックス欠損時の自己修復
  $index = load_forecast_index();
  if (!in_array($fileName, $index, true)) {
    $index[] = $fileName;
    return save_forecast_index($index);
  }

  return true;
}
