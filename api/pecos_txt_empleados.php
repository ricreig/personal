<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_auth_api();header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

session_boot();
$pdo = db();
$u = auth_user();
if (!$u) { http_response_code(401); echo json_encode(['error'=>'no-auth']); exit; }

$stations = [];
if (isset($_POST['stations']) && is_array($_POST['stations'])) $stations = $_POST['stations'];
if (isset($_POST['stations']) && is_string($_POST['stations'])) $stations = explode(',', $_POST['stations']);
$stations = array_values(array_unique(array_filter(array_map(function($s){ return strtoupper(trim((string)$s)); }, $stations))));

// permisos
$allowed = [];
if (($u['role'] ?? '') === 'admin') {
  $allowed = null; // all
} else {
  if (function_exists('user_station_matrix')) {
    $m = user_station_matrix($pdo, (int)$u['id'] ?? (int)$u['uid'] ?? 0);
    $allowed = array_keys(array_filter((array)$m));
  } else { $allowed = []; }
}

if ($allowed !== null) {
  // limitar a allowed
  $stations = $stations ? array_values(array_intersect($stations, $allowed)) : $allowed;
}
if (!$stations) { echo json_encode(['ok'=>true, 'data'=>[]]); exit; }

$place = implode(',', array_fill(0, count($stations), '?'));
$sql = "SELECT e.control, e.nombres, e.estacion, es.oaci
        FROM empleados e
        LEFT JOIN estaciones es ON es.id_estacion = e.estacion
        WHERE es.oaci IN ($place)
        ORDER BY es.oaci, e.control";
$st = $pdo->prepare($sql);
$st->execute($stations);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['ok'=>true, 'data'=>$rows], JSON_UNESCAPED_UNICODE);
