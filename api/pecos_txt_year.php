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

$year = (int)($_POST['year'] ?? $_GET['year'] ?? date('Y'));
$stations = [];
if (isset($_POST['stations']) && is_array($_POST['stations'])) $stations = $_POST['stations'];
if (isset($_POST['stations']) && is_string($_POST['stations'])) $stations = explode(',', $_POST['stations']);
$stations = array_values(array_unique(array_filter(array_map(function($s){ return strtoupper(trim((string)$s)); }, $stations))));

// permisos
$allowed = null;
if (($u['role'] ?? '') !== 'admin') {
  if (function_exists('user_station_matrix')) {
    $m = user_station_matrix($pdo, (int)$u['id'] ?? (int)$u['uid'] ?? 0);
    $allowed = array_keys(array_filter((array)$m));
  } else { $allowed = []; }
}
if ($allowed !== null) {
  $stations = $stations ? array_values(array_intersect($stations, $allowed)) : $allowed;
}
if (!$stations) { echo json_encode(['ok'=>true, 'rows'=>[], 'showOACI'=>false]); exit; }

$place = implode(',', array_fill(0, count($stations), '?'));
$sql = "SELECT e.control, e.nombres, e.fecha_nacimiento, es.oaci
        FROM empleados e
        LEFT JOIN estaciones es ON es.id_estacion=e.estacion
        WHERE es.oaci IN ($place)
        ORDER BY es.oaci, e.control";
$st = $pdo->prepare($sql);
$st->execute($stations);
$emps = $st->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
foreach ($emps as $e) {
  $ctrl = (int)$e['control'];
  $st = $pdo->prepare("SELECT dia1,dia2,dia3,dia4,dia5,dia6,dia7,dia8,dia9,dia10,dia11,dia12 FROM pecos WHERE control=? AND year=?");
  $st->execute([$ctrl, $year]); $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $st = $pdo->prepare("SELECT js,vs,dm,ds,muert,ono FROM txt WHERE control=? AND year=?");
  $st->execute([$ctrl, $year]); $t = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  $rows[] = [
    'control'=>$ctrl,
    'nombres'=>$e['nombres'],
    'oaci'=>$e['oaci'],
    'nacimiento'=>$e['fecha_nacimiento'],
    'pecos'=>$p,
    'txt'=>$t
  ];
}

echo json_encode([
  'ok'=>true,
  'showOACI' => (count($stations) > 1),
  'rows' => $rows
], JSON_UNESCAPED_UNICODE);
