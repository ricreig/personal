<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Encabezados base para JSON y evitar cacheado por proxies
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__); // …/unificado

require_once $ROOT . '/lib/session.php';
session_boot(); // inicia sesión con cookie domain .ctareig.com

require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';
// NO require guard.php aquí: las APIs usan require_auth_api()

$pdo = db();
$u = require_auth_api();

// api/pecos_get.php?control=XXXX&year=YYYY

$control = (int)($_GET['control'] ?? 0);
$year = (int)($_GET['year'] ?? date('Y'));

$st = $pdo->prepare('SELECT e.estacion, es.oaci
                        FROM empleados e
                        LEFT JOIN estaciones es ON es.id_estacion = e.estacion
                       WHERE e.control = ?
                       LIMIT 1');
$st->execute([$control]);
$row = $st->fetch();
if (!$row || empty($row['oaci']) || !can_view_station($pdo, (string)$row['oaci'])) {
    http_response_code(403);
    exit;
}

$st = $pdo->prepare('SELECT * FROM pecos WHERE control = ? AND year = ? LIMIT 1');
$st->execute([$control, $year]);
$record = $st->fetch();

if (!$record) {
    $pdo->prepare('INSERT INTO pecos (control,year,dia1,dia2,dia3,dia4,dia5,dia6,dia7,dia8,dia9,dia10,dia11,dia12)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$control, $year, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0']);
    $st->execute([$control, $year]);
    $record = $st->fetch();
}

echo json_encode($record, JSON_UNESCAPED_UNICODE);
