<?php
declare(strict_types=1);

// Encabezados base para JSON y evitar cacheado por proxies
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__); // …/unificado

require_once $ROOT . '/lib/session.php';
session_boot();                         // inicia sesión con cookie domain .ctareig.com

require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';
// NO require guard.php aquí: las APIs usan require_auth_api()

$u = require_auth_api();

// api/txt_get.php?control=XXXX&year=YYYY

$control = (int)($_GET['control'] ?? 0);
$year = (int)($_GET['year'] ?? date('Y'));

$st=$pdo->prepare("SELECT e.estacion, es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
$st->execute([$control]); $row=$st->fetch();
if (!$row || (empty($row['oaci']) || !can_view_station($pdo, $row['oaci']))) { http_response_code(403); exit; }

$st=$pdo->prepare("SELECT * FROM txt WHERE control=? AND year=? LIMIT 1");
$st->execute([$control, $year]); $r=$st->fetch();
if (!$r) {
  $pdo->prepare("INSERT INTO txt (control,year,js,vs,dm,ds,muert,ono) VALUES (?,?,?,?,?,?,?,?)")
      ->execute([$control,$year,'0','0','0','0','0','0']);
  $st->execute([$control, $year]); $r=$st->fetch();
}
header('Content-Type: application/json'); echo json_encode($r);