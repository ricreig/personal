<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');
$out = ['ok'=>false];
try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  $out['select1'] = $pdo->query('SELECT 1 AS one')->fetch(PDO::FETCH_ASSOC);
  $out['empleados_count'] = ($pdo->query('SELECT COUNT(*) AS c FROM empleados')->fetch(PDO::FETCH_ASSOC))['c'] ?? null;
  $out['driver'] = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  $out['server_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
  $out['client_version'] = $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION);
  $out['ok']=true;
} catch (Throwable $e) {
  http_response_code(500); $out['error']=$e->getMessage();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
