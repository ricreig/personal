<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php';
session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/guard.php';

$pdo = db();
$user = require_auth_api();
$allow = array_map('strtoupper', guard_allowed_oaci($pdo, $user));

$mode = $_GET['mode'] ?? 'persona';
$control = isset($_GET['control']) ? max(0, (int)$_GET['control']) : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$stations = isset($_GET['stations']) ? array_filter(array_map('trim', explode(',', (string)$_GET['stations']))) : [];
if ($stations) {
    $stations = array_values(array_intersect(array_map('strtoupper', $stations), $allow));
} else {
    $stations = $allow;
}

if ($mode === 'persona') {
    if ($control <= 0) {
        echo json_encode(['ok' => true, 'mode' => 'persona', 'rows' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $st = $pdo->prepare('SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion = e.estacion WHERE e.control = ? LIMIT 1');
    $st->execute([$control]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $oaci = strtoupper((string)($row['oaci'] ?? ''));
    if ($oaci === '') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($allow && !in_array($oaci, $allow, true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $yearsStmt = $pdo->prepare('SELECT MIN(year) AS miny, MAX(year) AS maxy FROM txt WHERE control = ?');
    $yearsStmt->execute([$control]);
    $bounds = $yearsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $minYear = max(2017, (int)($bounds['miny'] ?? $year));
    $maxYear = max($minYear, (int)($bounds['maxy'] ?? $year));
    $rows = [];
    $fn = $pdo->prepare('SELECT fecha_nacimiento FROM empleados WHERE control = ? LIMIT 1');
    $fn->execute([$control]);
    $fnac = $fn->fetchColumn();
    for ($y = $maxYear; $y >= $minYear; --$y) {
        $stmt = $pdo->prepare('SELECT year, js, vs, dm, ds, muert, ono FROM txt WHERE control = ? AND year = ? LIMIT 1');
        $stmt->execute([$control, $y]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $row = ['year' => $y, 'js' => '0', 'vs' => '0', 'dm' => '0', 'ds' => '0', 'muert' => '0', 'ono' => '0'];
        } else {
            $row['year'] = (int)$row['year'];
            foreach (['js', 'vs', 'dm', 'ds', 'muert', 'ono'] as $key) {
                $row[$key] = (string)($row[$key] ?? '0');
            }
        }
        $row['fnac'] = $fnac ?: null;
        $rows[] = $row;
    }
    echo json_encode(['ok' => true, 'mode' => 'persona', 'rows' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$stations) {
    echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$placeholders = implode(',', array_fill(0, count($stations), '?'));
$sql = "SELECT es.oaci, e.control, e.nombres, e.fecha_nacimiento AS fnac,
               t.js, t.vs, t.dm, t.ds, t.muert, t.ono
        FROM empleados e
        LEFT JOIN estaciones es ON es.id_estacion = e.estacion
        LEFT JOIN txt t ON t.control = e.control AND t.year = ?
        WHERE es.oaci IN ($placeholders)
        ORDER BY es.oaci, LPAD(e.control, 6, '0')";
$stmt = $pdo->prepare($sql);
$params = array_merge([$year], $stations);
$stmt->execute($params);
$rows = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'oaci'    => $row['oaci'],
        'control' => $row['control'],
        'nombres' => $row['nombres'],
        'js'      => (string)($row['js'] ?? '0'),
        'vs'      => (string)($row['vs'] ?? '0'),
        'dm'      => (string)($row['dm'] ?? '0'),
        'ds'      => (string)($row['ds'] ?? '0'),
        'muert'   => (string)($row['muert'] ?? '0'),
        'ono'     => (string)($row['ono'] ?? '0'),
        'fnac'    => $row['fnac'] ?? null,
    ];
}

echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
