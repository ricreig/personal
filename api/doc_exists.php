<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__);

require_once $ROOT . '/lib/session.php';
session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

$user = auth_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$control = trim((string)($_GET['control'] ?? ''));
if ($control === '') {
    http_response_code(400);
    echo json_encode(['error' => 'control requerido']);
    exit;
}

$pdo = db();

$emp = $pdo->prepare('SELECT estacion FROM empleados WHERE control = ? LIMIT 1');
$emp->execute([$control]);
$empRow = $emp->fetch(PDO::FETCH_ASSOC);
if (!$empRow) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$station = (string)($empRow['estacion'] ?? '');

$canOaci = static function (PDO $pdo, array $auth, string $stationCode): bool {
    if (function_exists('is_admin') && is_admin()) {
        return true;
    }
    if ($stationCode === '') {
        return false;
    }
    if (function_exists('user_station_matrix')) {
        $matrix = user_station_matrix($pdo, (int)($auth['id'] ?? 0));
        return empty($matrix) || !empty($matrix[$stationCode]);
    }
    return false;
};

if (!$canOaci($pdo, $user, $station)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$data = [
    'licencia1' => ['exists' => false],
    'licencia2' => ['exists' => false],
    'examen_medico' => ['exists' => false],
    'rtari' => ['exists' => false],
    'nombramientos' => ['exists' => false],
    'misc' => ['exists' => false, 'count' => 0, 'items' => []],
];

$tableExists = $pdo->query("SHOW TABLES LIKE 'documentos_personal'")->fetchColumn();
if (!$tableExists) {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

$docs = $pdo->prepare(
    'SELECT tipo, file_path, mime, size_bytes, updated_at
     FROM documentos_personal
     WHERE control = ?
     ORDER BY updated_at DESC'
);
$docs->execute([$control]);

$mapKey = static function (string $tipo): string {
    $t = strtolower(trim($tipo));
    if (str_starts_with($t, 'doc_extra')) {
        return 'misc';
    }
    if (in_array($t, ['licencia1', 'licencia1_front', 'licencia1_back', 'doc_licencia1'], true)) {
        return 'licencia1';
    }
    if (in_array($t, ['licencia2', 'licencia2_front', 'licencia2_back', 'doc_licencia2'], true)) {
        return 'licencia2';
    }
    if (in_array($t, ['examen_medico', 'cert_medico'], true)) {
        return 'examen_medico';
    }
    if ($t === 'rtari') {
        return 'rtari';
    }
    if (in_array($t, ['nombramiento', 'nombramientos', 'certificado'], true)) {
        return 'nombramientos';
    }
    return 'misc';
};

while ($row = $docs->fetch(PDO::FETCH_ASSOC)) {
    $tipo = (string)($row['tipo'] ?? '');
    if ($tipo === '') {
        continue;
    }
    $key = $mapKey($tipo);
    $item = [
        'exists' => true,
        'tipo' => $tipo,
        'filename' => basename((string)($row['file_path'] ?? '')),
        'mime' => (string)($row['mime'] ?? ''),
        'size' => (int)($row['size_bytes'] ?? 0),
        'updated_at' => $row['updated_at'] ?? null,
        'url' => 'doc_view.php?control=' . rawurlencode($control) . '&tipo=' . rawurlencode($tipo),
      ];

    if ($key === 'misc') {
        $data['misc']['items'][] = $item;
        $data['misc']['count'] = count($data['misc']['items']);
        $data['misc']['exists'] = $data['misc']['count'] > 0;
        continue;
    }

    $current = $data[$key] ?? null;
    if (!$current['exists'] || $tipo === $key) {
        $data[$key] = $item;
    }
}

echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
