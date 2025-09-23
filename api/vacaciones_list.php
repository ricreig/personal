<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php';
session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/guard.php';
require_once $ROOT . '/lib/vacaciones_calc.php';

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
        echo json_encode(['ok' => true, 'mode' => 'persona', 'rows' => [], 'summary' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $st = $pdo->prepare('SELECT e.control, e.nombres, e.ant, e.tipo1, e.fingreso, es.oaci
                          FROM empleados e
                          LEFT JOIN estaciones es ON es.id_estacion = e.estacion
                          WHERE e.control = ? LIMIT 1');
    $st->execute([$control]);
    $emp = $st->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $oaci = strtoupper((string)($emp['oaci'] ?? ''));
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
    $mov = $pdo->prepare(
        'SELECT year, tipo, periodo, inicia, reanuda, dias, resta, obs
         FROM vacaciones
         WHERE control = ?
         ORDER BY year DESC, tipo ASC, periodo ASC, id DESC'
    );
    $mov->execute([$control]);
    $movRows = [];
    $years = [];
    while ($row = $mov->fetch(PDO::FETCH_ASSOC)) {
        $row['year'] = (int)$row['year'];
        $row['dias'] = (int)($row['dias'] ?? 0);
        $row['resta'] = isset($row['resta']) ? (int)$row['resta'] : null;
        $movRows[] = $row;
        $years[$row['year']] = true;
    }
    $persona = [
        'control' => (int)$emp['control'],
        'nombres' => $emp['nombres'],
        'oaci'    => $oaci,
        'estacion'=> $oaci,
        'ant'     => $emp['ant'] ?? null,
        'ingreso' => $emp['fingreso'] ?? ($emp['ant'] ?? null),
        'puesto'  => $emp['tipo1'] ?? null,
    ];
    $summary = vc_summary($persona, $movRows, $year);
    echo json_encode([
        'ok' => true,
        'mode' => 'persona',
        'year' => $year,
        'persona' => [
            'control' => (int)$emp['control'],
            'control_fmt' => vc_format_control($emp['control']),
            'nombres' => $emp['nombres'],
            'estacion' => $oaci,
            'puesto' => $emp['tipo1'] ?? '',
        ],
        'summary' => $summary,
        'rows' => $movRows,
        'available_years' => (function (array $years): array {
            $keys = array_keys($years);
            rsort($keys, SORT_NUMERIC);
            return $keys;
        })($years),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$stations) {
    echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$empStmt = $pdo->prepare('SELECT e.control, e.nombres, e.ant, e.tipo1, e.fingreso, es.oaci
                           FROM empleados e
                           LEFT JOIN estaciones es ON es.id_estacion = e.estacion
                           WHERE es.oaci IN (' . implode(',', array_fill(0, count($stations), '?')) . ')
                           ORDER BY es.oaci, LPAD(e.control, 6, "0")');
$empStmt->execute($stations);
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$employees) {
    echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$controls = array_map(static fn(array $row): int => (int)$row['control'], $employees);
$placeholders = implode(',', array_fill(0, count($controls), '?'));
$yearPlaceholders = implode(',', array_fill(0, 2, '?'));
$movStmt = $pdo->prepare(
    'SELECT control, year, tipo, SUM(dias) AS dias
     FROM vacaciones
     WHERE control IN (' . $placeholders . ') AND year IN (' . $yearPlaceholders . ')
     GROUP BY control, year, tipo'
);
$params = array_merge($controls, [$year, $year - 1]);
$movStmt->execute($params);
$movMap = [];
while ($row = $movStmt->fetch(PDO::FETCH_ASSOC)) {
    $ctrl = (int)$row['control'];
    $movMap[$ctrl][] = [
        'year' => (int)$row['year'],
        'tipo' => $row['tipo'],
        'dias' => (int)$row['dias'],
    ];
}
$rows = [];
foreach ($employees as $emp) {
    $ctrl = (int)$emp['control'];
    $oaci = strtoupper((string)($emp['oaci'] ?? ''));
    if ($allow && !in_array($oaci, $allow, true)) {
        continue;
    }
    $persona = [
        'control' => $ctrl,
        'nombres' => $emp['nombres'],
        'oaci'    => $oaci,
        'estacion'=> $oaci,
        'ant'     => $emp['ant'] ?? null,
        'ingreso' => $emp['fingreso'] ?? ($emp['ant'] ?? null),
        'puesto'  => $emp['tipo1'] ?? null,
    ];
    $summary = vc_summary($persona, $movMap[$ctrl] ?? [], $year);
    $rows[] = $summary;
}

echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
