<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

session_boot();
$pdo = db();
$u = auth_user();
if (!$u) { http_response_code(401); echo json_encode(['error'=>'no-auth']); exit; }

// estaciones visibles
$stations = [];
if (($u['role'] ?? '') === 'admin') {
  $st = $pdo->query("SELECT UPPER(TRIM(oaci)) AS oaci FROM estaciones WHERE oaci IS NOT NULL AND oaci<>'' ORDER BY oaci");
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) { $stations[] = $r['oaci']; }
} else {
  if (function_exists('user_station_matrix')) {
    $m = user_station_matrix($pdo, (int)$u['id'] ?? (int)$u['uid'] ?? 0);
    $stations = array_values(array_keys(array_filter((array)$m)));
  }
}
// fallback: si no trae nada, intenta deducir por su control
if (!$stations) {
  $st = $pdo->prepare("SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control = ? LIMIT 1");
  $st->execute([ (int)($u['control'] ?? 0) ]);
  $o = $st->fetchColumn();
  if ($o) $stations = [ strtoupper(trim((string)$o)) ];
}

$stations = array_values(array_unique(array_filter(array_map('trim', $stations))));

$curr = (int)date('Y');
$years = [];
for ($y=2017; $y <= $curr+1; $y++) $years[] = $y;

echo json_encode([
  'ok' => true,
  'stations' => $stations,
  'defaultStations' => $stations,
  'years' => $years,
  'defaultYear' => $curr
], JSON_UNESCAPED_UNICODE);
