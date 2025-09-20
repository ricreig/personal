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

$id = (int)($_POST['id'] ?? 0);
$ctrl = (int)($_POST['control'] ?? 0);
$folio = substr((string)($_POST['folio'] ?? ''), 0, 12);
$inicia = substr((string)($_POST['inicia'] ?? ''), 0, 10);
$termina= substr((string)($_POST['termina'] ?? ''), 0, 10);
$dias   = (int)($_POST['dias'] ?? 0);
$umf    = substr((string)($_POST['umf'] ?? ''), 0, 4);
$diag   = substr((string)($_POST['diag'] ?? ''), 0, 52);

if ($ctrl<=0) { http_response_code(400); echo json_encode(['error'=>'bad_request']); exit; }

// validar permisos por OACI del control
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

if ($id>0) {
  $st=$pdo->prepare("UPDATE incapacidad SET NC=?, FOLIO=?, INICIA=?, TERMINA=?, DIAS=?, UMF=?, DIAGNOSTICO=? WHERE Id=?");
  $st->execute([$ctrl,$folio,$inicia,$termina,$dias,$umf,$diag,$id]);
} else {
  $st=$pdo->prepare("INSERT INTO incapacidad (NC, FOLIO, INICIA, TERMINA, DIAS, UMF, DIAGNOSTICO) VALUES (?,?,?,?,?,?,?)");
  $st->execute([$ctrl,$folio,$inicia,$termina,$dias,$umf,$diag]);
}

echo json_encode(['ok'=>true]);