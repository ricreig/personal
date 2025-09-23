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

$control = (int)($_POST['control'] ?? 0);
$year    = (int)($_POST['year'] ?? 0);
$pecos   = $_POST['pecos'] ?? [];
$txt     = $_POST['txt'] ?? [];
if ($control<=0 || $year<=0) { http_response_code(400); echo json_encode(['error'=>'bad-req']); exit; }

// permiso de edición por estación
$st = $pdo->prepare("SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
$st->execute([$control]); $oaci = $st->fetchColumn();
$canEdit = false;
if (($u['role'] ?? '') === 'admin') { $canEdit = true; }
else {
  // user_station_perms.can_edit
  $ps = $pdo->prepare("SELECT can_edit FROM user_station_perms WHERE user_id=? AND oaci=? LIMIT 1");
  $ps->execute([(int)($u['id'] ?? $u['uid'] ?? 0), $oaci]);
  $canEdit = ((int)$ps->fetchColumn() === 1);
}
if (!$canEdit) { http_response_code(403); echo json_encode(['error'=>'no-edit']); exit; }

// Normalizar arrays
$P = [];
for ($i=1;$i<=12;$i++){ $k='dia'.$i; $P[$k] = isset($pecos[$k]) ? (string)$pecos[$k] : ''; }
$T = [
  'js'    => (string)($txt['js']    ?? ''),
  'vs'    => (string)($txt['vs']    ?? ''),
  'dm'    => (string)($txt['dm']    ?? ''),
  'ds'    => (string)($txt['ds']    ?? ''),
  'muert' => (string)($txt['muert'] ?? ''),
  'ono'   => (string)($txt['ono']   ?? ''),
];

$pdo->beginTransaction();
try {
  // PECOs upsert
  $st = $pdo->prepare("SELECT COUNT(*) FROM pecos WHERE control=? AND year=?");
  $st->execute([$control,$year]);
  if ((int)$st->fetchColumn() > 0) {
    $sql = "UPDATE pecos SET dia1=?,dia2=?,dia3=?,dia4=?,dia5=?,dia6=?,dia7=?,dia8=?,dia9=?,dia10=?,dia11=?,dia12=? WHERE control=? AND year=?";
    $vals = [$P['dia1'],$P['dia2'],$P['dia3'],$P['dia4'],$P['dia5'],$P['dia6'],$P['dia7'],$P['dia8'],$P['dia9'],$P['dia10'],$P['dia11'],$P['dia12'],$control,$year];
  } else {
    $sql = "INSERT INTO pecos (dia1,dia2,dia3,dia4,dia5,dia6,dia7,dia8,dia9,dia10,dia11,dia12,control,year) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $vals = [$P['dia1'],$P['dia2'],$P['dia3'],$P['dia4'],$P['dia5'],$P['dia6'],$P['dia7'],$P['dia8'],$P['dia9'],$P['dia10'],$P['dia11'],$P['dia12'],$control,$year];
  }
  $pdo->prepare($sql)->execute($vals);

  // TXT upsert
  $st = $pdo->prepare("SELECT COUNT(*) FROM txt WHERE control=? AND year=?");
  $st->execute([$control,$year]);
  if ((int)$st->fetchColumn() > 0) {
    $sql = "UPDATE txt SET js=?,vs=?,dm=?,ds=?,muert=?,ono=? WHERE control=? AND year=?";
    $vals = [$T['js'],$T['vs'],$T['dm'],$T['ds'],$T['muert'],$T['ono'],$control,$year];
  } else {
    $sql = "INSERT INTO txt (js,vs,dm,ds,muert,ono,control,year) VALUES (?,?,?,?,?,?,?,?)";
    $vals = [$T['js'],$T['vs'],$T['dm'],$T['ds'],$T['muert'],$T['ono'],$control,$year];
  }
  $pdo->prepare($sql)->execute($vals);

  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error'=>'server','msg'=>$e->getMessage()]);
}
