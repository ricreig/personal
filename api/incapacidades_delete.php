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
if ($id<=0) { http_response_code(400); echo json_encode(['error'=>'bad_request']); exit; }

// recuperar control para validar permisos
$st=$pdo->prepare("SELECT i.NC AS control, es.oaci FROM incapacidad i JOIN empleados e ON e.control=i.NC LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE i.Id=? LIMIT 1");
$st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
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

$st=$pdo->prepare("DELETE FROM incapacidad WHERE Id=?");
$st->execute([$id]);
echo json_encode(['ok'=>true]);