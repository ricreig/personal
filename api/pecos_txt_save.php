<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php'; session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

$pdo=db(); $u=auth_user(); if(!$u){ http_response_code(401); echo json_encode(['error'=>'auth']); exit; }

// body JSON: {control:int, year:int, pecos:{dia1..dia12}, txt:{js,vs,dm,ds,muert,ono} }
$body = json_decode(file_get_contents('php://input'), true);
$control = (int)($body['control'] ?? 0);
$year    = (int)($body['year'] ?? 0);
if ($control<=0 || $year<=0) { http_response_code(400); echo json_encode(['error'=>'bad_request']); exit; }

// validar acceso por estación
$st = $pdo->prepare("SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
$st->execute([$control]); $row=$st->fetch();
if (!$row) { http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
$oaci = strtoupper((string)$row['oaci']);
$allow = [];
if (is_admin()) {
  $allow = [$oaci];
} else if (function_exists('user_station_matrix')) {
  $m = user_station_matrix($pdo, (int)($u['id'] ?? 0));
  foreach($m as $k=>$v){ if ($v) $allow[] = strtoupper($k); }
}
if (!in_array($oaci, $allow, true)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$pdo->beginTransaction();
try {
  if (isset($body['pecos']) && is_array($body['pecos'])) {
    $ps = $pdo->prepare("INSERT INTO pecos (control,year,dia1,dia2,dia3,dia4,dia5,dia6,dia7,dia8,dia9,dia10,dia11,dia12)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE dia1=VALUES(dia1),dia2=VALUES(dia2),dia3=VALUES(dia3),dia4=VALUES(dia4),dia5=VALUES(dia5),dia6=VALUES(dia6),
                                                 dia7=VALUES(dia7),dia8=VALUES(dia8),dia9=VALUES(dia9),dia10=VALUES(dia10),dia11=VALUES(dia11),dia12=VALUES(dia12)");
    $P = $body['pecos'];
    $vals = [ $control,$year ];
    for($i=1;$i<=12;$i++){ $vals[] = (string)($P['dia'.$i] ?? '0'); }
    $ps->execute($vals);
  }
  if (isset($body['txt']) && is_array($body['txt'])) {
    $ts = $pdo->prepare("INSERT INTO txt (control,year,js,vs,dm,ds,muert,ono)
                         VALUES (?,?,?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE js=VALUES(js),vs=VALUES(vs),dm=VALUES(dm),ds=VALUES(ds),muert=VALUES(muert),ono=VALUES(ono)");
    $T = $body['txt'];
    $ts->execute([ $control,$year,(string)($T['js']??'0'),(string)($T['vs']??'0'),(string)($T['dm']??'0'),
                   (string)($T['ds']??'0'),(string)($T['muert']??'0'),(string)($T['ono']??'0') ]);
  }
  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch(Throwable $e){
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error'=>'db','detail'=>$e->getMessage()]);
}
