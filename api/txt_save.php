<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php'; session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

$u = auth_user(); if (!$u) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
$pdo = db();

$ctrl = (int)($_POST['control'] ?? 0);
$year = (int)($_POST['year'] ?? 0);
if ($ctrl<=0 || $year<=0) { http_response_code(400); echo json_encode(['error'=>'bad_request']); exit; }

$st=$pdo->prepare("SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
$st->execute([$ctrl]); $row=$st->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }

function can_oaci(PDO $pdo, array $u, string $oaci): bool {
  if (function_exists('is_admin') && is_admin()) return true;
  if (function_exists('user_station_matrix')) {
    $m = user_station_matrix($pdo, (int)($u['id'] ?? 0));
    return !($m) || !empty($m[$oaci]);
  }
  return false;
}
if (!can_oaci($pdo, $u, (string)$row['oaci'])) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$js = substr((string)($_POST['js'] ?? ''), 0, 8);
$vs = substr((string)($_POST['vs'] ?? ''), 0, 8);
$dm = substr((string)($_POST['dm'] ?? ''), 0, 8);
$ds = substr((string)($_POST['ds'] ?? ''), 0, 8);
$mu = substr((string)($_POST['muert'] ?? ''), 0, 8);
$on = substr((string)($_POST['ono'] ?? ''), 0, 8);

// Upsert
$st=$pdo->prepare("SELECT id FROM txt WHERE control=? AND year=? LIMIT 1");
$st->execute([$ctrl,$year]); $ex=$st->fetch(PDO::FETCH_ASSOC);
if ($ex) {
  $st=$pdo->prepare("UPDATE txt SET js=?,vs=?,dm=?,ds=?,muert=?,ono=? WHERE control=? AND year=?");
  $st->execute([$js,$vs,$dm,$ds,$mu,$on,$ctrl,$year]);
} else {
  $st=$pdo->prepare("INSERT INTO txt (control,year,js,vs,dm,ds,muert,ono) VALUES (?,?,?,?,?,?,?,?)");
  $st->execute([$ctrl,$year,$js,$vs,$dm,$ds,$mu,$on]);
}

echo json_encode(['ok'=>true]);