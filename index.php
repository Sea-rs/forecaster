<?php
declare(strict_types=1);

require_once __DIR__ . '/src/layout.php';

render_page_start('FORECASTER | TOP', '/assets/css/index.css');
?>
  <main class="card-wrap">
  <div class="card">
  <h1>FORECASTER</h1>
  <p>
    CSVデータを取り込み、JSONファイルに保存し、内容を確認するためのシンプルなWebアプリです。
    まずは基礎機能のみの第1段実装です。
  </p>
  <div class="links">
    <a href="<?= htmlspecialchars(app_url('/import.php'), ENT_QUOTES, 'UTF-8') ?>">CSV取り込みページへ</a>
    <a href="<?= htmlspecialchars(app_url('/view/index.php'), ENT_QUOTES, 'UTF-8') ?>">Viewページへ</a>
    <a href="<?= htmlspecialchars(app_url('/diff/index.php'), ENT_QUOTES, 'UTF-8') ?>">差分比較ページへ</a>
  </div>
  <p class="sub">次段でデータ検証・検索・集計機能を拡張予定です。</p>
  </div>
  </main>
<?php render_page_end(); ?>
