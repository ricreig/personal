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

$control = (int)($_GET['control'] ?? 0);
$year = (int)($_GET['year'] ?? date('Y'));
if ($control<=0) { http_response_code(400); echo json_encode(['error'=>'bad-req']); exit; }

// permisos por estación
$st = $pdo->prepare("SELECT es.oaci, e.nombres FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
$st->execute([$control]);
$emp = $st->fetch(PDO::FETCH_ASSOC);
if (!$emp) { http_response_code(404); echo json_encode(['error'=>'not-found']); exit; }

$can = false;
if (($u['role'] ?? '') === 'admin') $can = true;
else if (function_exists('user_station_matrix')) {
  $m = user_station_matrix($pdo, (int)$u['id'] ?? (int)$u['uid'] ?? 0);
  $can = !empty($m[$emp['oaci']]);
}
if (!$can) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$st = $pdo->prepare("SELECT dia1,dia2,dia3,dia4,dia5,dia6,dia7,dia8,dia9,dia10,dia11,dia12 FROM pecos WHERE control=? AND year=?");
$st->execute([$control, $year]); $p = $st->fetch(PDO::FETCH_ASSOC);
if (!$p) $p = ['dia1'=>'','dia2'=>'','dia3'=>'','dia4'=>'','dia5'=>'','dia6'=>'','dia7'=>'','dia8'=>'','dia9'=>'','dia10'=>'','dia11'=>'','dia12'=>''];

$st = $pdo->prepare("SELECT js,vs,dm,ds,muert,ono FROM txt WHERE control=? AND year=?");
$st->execute([$control, $year]); $t = $st->fetch(PDO::FETCH_ASSOC);
if (!$t) $t = ['js'=>'','vs'=>'','dm'=>'','ds'=>'','muert'=>'','ono'=>''];

echo json_encode(['ok'=>true, 'control'=>$control, 'year'=>$year, 'nombre'=>$emp['nombres'] ?? '', 'pecos'=>$p, 'txt'=>$t], JSON_UNESCAPED_UNICODE);
