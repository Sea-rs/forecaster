<?php
declare(strict_types=1);

/**
 * 共通ヘッダーを含むページ開始タグを出力します。
 */
function render_page_start(string $title, string $pageStylesheet, string $activeMenu = '', string $bodyClass = ''): void
{
  $menuItems = [
    'import' => ['label' => 'CSV取り込み', 'href' => '/import.php'],
    'view' => ['label' => '登録名一覧', 'href' => '/view/index.php'],
    'diff' => ['label' => '差分比較', 'href' => '/diff/index.php'],
  ];

  $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  $safeBodyClass = trim($bodyClass) !== ''
    ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"'
    : '';
?>
<!doctype html>
  <html lang="ja">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?php echo $safeTitle; ?></title>
      <link rel="stylesheet" href="/assets/css/common.css">
      <link rel="stylesheet" href="<?php echo htmlspecialchars($pageStylesheet, ENT_QUOTES, 'UTF-8'); ?>">
    </head>
    <body<?php echo $safeBodyClass; ?>>
      <header class="site-header">
        <div class="site-header-inner">
          <a href="/" class="site-logo">FORECASTER</a>
          <nav class="site-nav">
            <?php

              foreach ($menuItems as $key => $item) {
                $isActive = $activeMenu === $key;
                $activeClass = $isActive ? ' is-active' : '';
                $ariaCurrent = $isActive ? ' aria-current="page"' : '';

                echo '        <a href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '" class="site-nav-link' . $activeClass . '"' . $ariaCurrent . '>';
                echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                echo "</a>\n";
              }
            ?>
        </nav>
      </div>
    </header>

<?php
}

/**
 * 共通終了タグを出力します。
 */
function render_page_end(): void
{
  echo "</body>\n";
  echo "</html>\n";
}
