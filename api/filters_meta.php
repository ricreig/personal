<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');
try {
  $pdo = db();
  $want = isset($_GET['tipo']) ? strtolower(trim((string)$_GET['tipo'])) : '';
  $out = ['ok'=>true];
  $st = $pdo->query("SELECT DISTINCT estacion FROM empleados WHERE estacion IS NOT NULL AND estacion<>'' ORDER BY estacion");
  $out['estaciones'] = $st->fetchAll(PDO::FETCH_COLUMN);
  if ($want === 'puestos') {
    $q = $pdo->query("SELECT DISTINCT puesto FROM empleados WHERE puesto IS NOT NULL AND puesto<>'' ORDER BY puesto");
    $out['puestos'] = $q->fetchAll(PDO::FETCH_COLUMN);
  } elseif ($want === 'areas') {
    $q = $pdo->query("SELECT DISTINCT area FROM empleados WHERE area IS NOT NULL AND area<>'' ORDER BY area");
    $out['areas'] = $q->fetchAll(PDO::FETCH_COLUMN);
  } elseif ($want !== '') {
    $out['note'] = 'tipo ignorado';
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
