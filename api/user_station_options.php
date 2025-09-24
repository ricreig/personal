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

try {
    $user = auth_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        return;
    }
    $role = strtolower((string)($user['role'] ?? 'viewer'));
    if ($role === 'viewer') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        return;
    }

    $pdo = db();

    $metaStations = [];
    $hasEstaciones = $pdo->query("SHOW TABLES LIKE 'estaciones'")->fetchColumn();
    if ($hasEstaciones) {
        $stmt = $pdo->query("SELECT UPPER(TRIM(COALESCE(oaci,''))) AS oaci, COALESCE(nombre,'') AS nombre, COALESCE(region,'') AS region FROM estaciones WHERE oaci IS NOT NULL AND oaci <> ''");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $oaci = strtoupper(trim((string)($row['oaci'] ?? '')));
            if ($oaci === '') {
                continue;
            }
            $metaStations[$oaci] = [
                'oaci' => $oaci,
                'nombre' => (string)($row['nombre'] ?? ''),
                'region' => (string)($row['region'] ?? ''),
            ];
        }
    }

    if (!$metaStations) {
        $fallback = $pdo->query("SELECT DISTINCT estacion FROM empleados WHERE estacion IS NOT NULL AND estacion <> ''");
        foreach ($fallback->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $oaci = estacion_id_to_oaci((int)$id);
            if (!$oaci) {
                continue;
            }
            $metaStations[$oaci] = [
                'oaci' => $oaci,
                'nombre' => '',
                'region' => '',
            ];
        }
    }

    if ($role === 'admin') {
        if (!$metaStations) {
            for ($i = 1; $i <= 20; $i++) {
                $oaci = estacion_id_to_oaci($i);
                if ($oaci) {
                    $metaStations[$oaci] = [
                        'oaci' => $oaci,
                        'nombre' => '',
                        'region' => '',
                    ];
                }
            }
        }
        $stations = [];
        foreach ($metaStations as $info) {
            $stations[] = [
                'oaci' => $info['oaci'],
                'nombre' => $info['nombre'],
                'region' => $info['region'],
                'can_view' => true,
                'can_edit' => true,
            ];
        }
        echo json_encode(['ok' => true, 'stations' => $stations], JSON_UNESCAPED_UNICODE);
        return;
    }

    $st = $pdo->prepare("SELECT UPPER(TRIM(oaci)) AS oaci, MAX(can_view) AS can_view, MAX(can_edit) AS can_edit FROM user_station_perms WHERE user_id = ? GROUP BY oaci");
    $st->execute([(int)$user['id']]);
    $stations = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $oaci = strtoupper(trim((string)($row['oaci'] ?? '')));
        if ($oaci === '' || (int)($row['can_view'] ?? 0) !== 1) {
            continue;
        }
        $info = $metaStations[$oaci] ?? [
            'oaci' => $oaci,
            'nombre' => '',
            'region' => '',
        ];
        $stations[] = [
            'oaci' => $info['oaci'],
            'nombre' => $info['nombre'],
            'region' => $info['region'],
            'can_view' => true,
            'can_edit' => ((int)($row['can_edit'] ?? 0) === 1),
        ];
    }

    echo json_encode(['ok' => true, 'stations' => $stations], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error'], JSON_UNESCAPED_UNICODE);
}
