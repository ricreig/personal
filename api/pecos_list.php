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
    $hist = [];
    $yearsStmt = $pdo->prepare('SELECT MIN(year) AS miny, MAX(year) AS maxy FROM pecos WHERE control = ?');
    $yearsStmt->execute([$control]);
    $bounds = $yearsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $minYear = max(2017, (int)($bounds['miny'] ?? $year));
    $maxYear = max($minYear, (int)($bounds['maxy'] ?? $year));
    for ($y = $maxYear; $y >= $minYear; --$y) {
        $stmt = $pdo->prepare('SELECT year, dia1,dia2,dia3,dia4,dia5,dia6,dia7,dia8,dia9,dia10,dia11,dia12 FROM pecos WHERE control = ? AND year = ? LIMIT 1');
        $stmt->execute([$control, $y]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $row = ['year' => $y];
            for ($i = 1; $i <= 12; $i++) {
                $row['dia' . $i] = '0';
            }
        } else {
            $row['year'] = (int)$row['year'];
            for ($i = 1; $i <= 12; $i++) {
                $row['dia' . $i] = (string)($row['dia' . $i] ?? '0');
            }
        }
        $hist[] = $row;
    }
    echo json_encode(['ok' => true, 'mode' => 'persona', 'rows' => $hist], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$stations) {
    echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$placeholders = implode(',', array_fill(0, count($stations), '?'));
$sql = "SELECT es.oaci, e.control, e.nombres,
               p.dia1, p.dia2, p.dia3, p.dia4, p.dia5, p.dia6, p.dia7, p.dia8, p.dia9, p.dia10, p.dia11, p.dia12
        FROM empleados e
        LEFT JOIN estaciones es ON es.id_estacion = e.estacion
        LEFT JOIN pecos p ON p.control = e.control AND p.year = ?
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
        'dia1' => (string)($row['dia1'] ?? '0'),
        'dia2' => (string)($row['dia2'] ?? '0'),
        'dia3' => (string)($row['dia3'] ?? '0'),
        'dia4' => (string)($row['dia4'] ?? '0'),
        'dia5' => (string)($row['dia5'] ?? '0'),
        'dia6' => (string)($row['dia6'] ?? '0'),
        'dia7' => (string)($row['dia7'] ?? '0'),
        'dia8' => (string)($row['dia8'] ?? '0'),
        'dia9' => (string)($row['dia9'] ?? '0'),
        'dia10' => (string)($row['dia10'] ?? '0'),
        'dia11' => (string)($row['dia11'] ?? '0'),
        'dia12' => (string)($row['dia12'] ?? '0'),
    ];
}

echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
