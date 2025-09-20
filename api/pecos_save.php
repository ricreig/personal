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

$vals = [];
for ($i=1;$i<=12;$i++) { $vals[$i] = substr((string)($_POST['dia'.$i] ?? ''), 0, 8); }

// Upsert
$st=$pdo->prepare("SELECT id FROM pecos WHERE control=? AND year=? LIMIT 1");
$st->execute([$ctrl,$year]); $ex=$st->fetch(PDO::FETCH_ASSOC);
if ($ex) {
  $st=$pdo->prepare("UPDATE pecos SET dia1=?,dia2=?,dia3=?,dia4=?,dia5=?,dia6=?,dia7=?,dia8=?,dia9=?,dia10=?,dia11=?,dia12=? WHERE control=? AND year=?");
  $st->execute([$vals[1],$vals[2],$vals[3],$vals[4],$vals[5],$vals[6],$vals[7],$vals[8],$vals[9],$vals[10],$vals[11],$vals[12], $ctrl,$year]);
} else {
  $st=$pdo->prepare("INSERT INTO pecos (control,year,dia1,dia2,dia3,dia4,dia5,dia6,dia7,dia8,dia9,dia10,dia11,dia12)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $st->execute([$ctrl,$year,$vals[1],$vals[2],$vals[3],$vals[4],$vals[5],$vals[6],$vals[7],$vals[8],$vals[9],$vals[10],$vals[11],$vals[12]]);
}

echo json_encode(['ok'=>true]);