<?php
require_once __DIR__ . '/bootstrap.php';
echo json_encode([
  'ok'   => true,
  'php'  => PHP_VERSION,
  'time' => date('c'),
]);