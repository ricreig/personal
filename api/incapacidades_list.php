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
    $stmt = $pdo->prepare('SELECT Id, INICIA, TERMINA, DIAS, UMF, DIAGNOSTICO, FOLIO FROM incapacidad WHERE NC = ? AND YEAR(STR_TO_DATE(INICIA, "%d/%m/%Y")) BETWEEN 2017 AND YEAR(NOW()) ORDER BY STR_TO_DATE(INICIA, "%d/%m/%Y") DESC, Id DESC');
    $stmt->execute([$control]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['ok' => true, 'mode' => 'persona', 'rows' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$stations) {
    echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$placeholders = implode(',', array_fill(0, count($stations), '?'));
$sql = "SELECT es.oaci, e.control, e.nombres, i.INICIA, i.TERMINA, i.DIAS, i.UMF, i.DIAGNOSTICO, i.FOLIO
        FROM incapacidad i
        INNER JOIN empleados e ON e.control = i.NC
        LEFT JOIN estaciones es ON es.id_estacion = e.estacion
        WHERE es.oaci IN ($placeholders) AND YEAR(STR_TO_DATE(i.INICIA, '%d/%m/%Y')) = ?
        ORDER BY es.oaci, LPAD(e.control, 6, '0')";
$stmt = $pdo->prepare($sql);
$params = array_merge($stations, [$year]);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode(['ok' => true, 'mode' => 'anio', 'year' => $year, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
