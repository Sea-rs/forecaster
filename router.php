<?php
declare(strict_types=1);

$publicRoot = __DIR__ . DIRECTORY_SEPARATOR . 'public';
$publicRootReal = realpath($publicRoot);

if ($publicRootReal === false) {
  http_response_code(500);
  echo 'public directory is missing.';
  exit;
}

$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$requestPath = rawurldecode($requestPath);

if ($requestPath === '/') {
  $requestPath = '/index.php';
}

$requestedFullPath = $publicRootReal . str_replace('/', DIRECTORY_SEPARATOR, $requestPath);
if (is_dir($requestedFullPath)) {
  $requestedFullPath = rtrim($requestedFullPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
}

$targetPath = realpath($requestedFullPath);
$inPublic = $targetPath !== false
  && ($targetPath === $publicRootReal || str_starts_with($targetPath, $publicRootReal . DIRECTORY_SEPARATOR));

if (!$inPublic || !is_file($targetPath)) {
  http_response_code(404);
  echo 'Not Found';
  exit;
}

$extension = strtolower((string)pathinfo($targetPath, PATHINFO_EXTENSION));

if ($extension === 'php') {
  $_SERVER['SCRIPT_FILENAME'] = $targetPath;
  $_SERVER['SCRIPT_NAME'] = str_replace('\\', '/', $requestPath);
  require $targetPath;
  exit;
}

$contentType = (string)(mime_content_type($targetPath) ?: 'application/octet-stream');
header('Content-Type: ' . $contentType);
header('Content-Length: ' . (string)filesize($targetPath));
readfile($targetPath);
exit;
