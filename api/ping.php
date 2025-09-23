<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/bootstrap.php';
require_auth_api();
echo json_encode([
  'ok'   => true,
  'php'  => PHP_VERSION,
  'time' => date('c'),
]);
