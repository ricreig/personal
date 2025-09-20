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

$id  = (int)($_POST['id'] ?? 0);
$ctrl= (string)($_POST['control'] ?? '');
$year= (int)($_POST['year'] ?? 0);
$tipo= substr((string)($_POST['tipo'] ?? ''), 0, 3); // VAC | ANT | PR
$periodo = (int)($_POST['periodo'] ?? 0);
$inicia  = substr((string)($_POST['inicia'] ?? ''), 0, 10);
$reanuda = substr((string)($_POST['reanuda'] ?? ''), 0, 10);
$dias    = (int)($_POST['dias'] ?? 0);
$resta   = (int)($_POST['resta'] ?? 0);
$obs     = substr((string)($_POST['obs'] ?? ''), 0, 50);

$control = (int)$ctrl;
if ($control<=0 || $year<=0 || $tipo==='') { http_response_code(400); echo json_encode(['error'=>'bad_request']); exit; }

// validar permisos por OACI del control
$st=$pdo->prepare("SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
$st->execute([$control]); $row=$st->fetch(PDO::FETCH_ASSOC);
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

if ($id>0) {
  $st=$pdo->prepare("UPDATE vacaciones SET control=?, year=?, tipo=?, periodo=?, inicia=?, reanuda=?, dias=?, resta=?, obs=? WHERE id=?");
  $st->execute([$control,$year,$tipo,$periodo,$inicia,$reanuda,$dias,$resta,$obs,$id]);
} else {
  $st=$pdo->prepare("INSERT INTO vacaciones (control,year,tipo,periodo,inicia,reanuda,dias,resta,obs) VALUES (?,?,?,?,?,?,?,?,?)");
  $st->execute([$control,$year,$tipo,$periodo,$inicia,$reanuda,$dias,$resta,$obs]);
}

echo json_encode(['ok'=>true]);