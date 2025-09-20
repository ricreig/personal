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

try {
    // ---- Validar parámetro ----
    $tipo = $_GET['tipo'] ?? '';
    if ($tipo !== 'est' && $tipo !== 'area') {
        http_response_code(400);
        echo json_encode(['ok'=>false, 'error'=>'tipo inválido']);
        exit;
    }

    // ---- Filtro por permisos (si no es admin) ----
    $where  = '';
    $params = [];

    if (!is_admin()) {
        // Matriz de estaciones que puede ver el usuario
        // Debe regresar algo tipo ['MMHO'=>true, 'MMTJ'=>true, ...]
        $matrix = user_station_matrix($pdo, (int)$u['id']);
        if (empty($matrix)) {
            echo json_encode([]); // sin permisos => lista vacía
            exit;
        }
        $in     = implode(',', array_fill(0, count($matrix), '?'));
        $where  = "WHERE es.oaci IN ($in)";
        $params = array_keys($matrix);
    }

    // ---- Query según tipo ----
    if ($tipo === 'est') {
        $sql = "SELECT DISTINCT es.oaci
                FROM empleados e
                LEFT JOIN estaciones es ON es.id_estacion = e.estacion
                $where
                ORDER BY es.oaci";
    } else { // 'area'
        $sql = "SELECT DISTINCT e.espec
                FROM empleados e
                LEFT JOIN estaciones es ON es.id_estacion = e.estacion
                $where
                ORDER BY e.espec";
    }

    $st = $pdo->prepare($sql);
    $st->execute($params);

    $out = [];
    while ($row = $st->fetch(PDO::FETCH_NUM)) {
        $v = trim((string)$row[0]);
        if ($v !== '') $out[] = $v;
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'Exception', 'msg'=>$e->getMessage()]);
}