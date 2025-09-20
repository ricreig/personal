<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__);

require_once $ROOT . '/lib/session.php';
session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

$pdo = db();
$u   = auth_user();
if (!$u) { http_response_code(401); echo json_encode(['error'=>'auth']); exit; }

function oaci_permitidas(PDO $pdo, array $u): array {
  // admin: todas
  if (is_admin()) {
    $st = $pdo->query("SELECT UPPER(oaci) AS oaci FROM estaciones WHERE oaci IS NOT NULL ORDER BY oaci");
    return array_values(array_filter(array_map(fn($r)=>trim((string)$r['oaci']), $st->fetchAll())));
  }
  if (function_exists('user_station_matrix')) {
    $m = user_station_matrix($pdo, (int)($u['id'] ?? 0));
    return array_values(array_keys(array_filter((array)$m)));
  }
  return [];
}

$OACI = oaci_permitidas($pdo, $u);

// Personas por OACI
$personas = [];
if ($OACI) {
  $ph = implode(',', array_fill(0, count($OACI), '?'));
  $st = $pdo->prepare("
    SELECT e.control, e.nombres, es.oaci
    FROM empleados e
    LEFT JOIN estaciones es ON es.id_estacion = e.estacion
    WHERE es.oaci IN ($ph)
    ORDER BY es.oaci, LPAD(e.control, 6, '0')
  ");
  $st->execute($OACI);
  $personas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$minYear = 2017;
$maxYear = (int)date('Y'); // sin +1
$years   = range($maxYear, $minYear);

echo json_encode([
  'ok'=>true,
  'stations'=>$OACI,
  'personas'=>$personas,
  'years'=>$years,
]);
