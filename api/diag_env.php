<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok'=>true,
  'php_version'=>PHP_VERSION,
  'sapi'=>PHP_SAPI,
  'pdo_drivers'=>class_exists('PDO')?PDO::getAvailableDrivers():[],
  'ini'=>['display_errors'=>ini_get('display_errors'),'log_errors'=>ini_get('log_errors'),'error_reporting'=>ini_get('error_reporting'),'date.timezone'=>ini_get('date.timezone')]
], JSON_UNESCAPED_UNICODE);
