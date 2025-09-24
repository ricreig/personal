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

$u = auth_user();
if (!$u) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('bad_request', 400);
    }

    $pdo = db();

    $st = $pdo->prepare(
        'SELECT v.id, v.control, es.oaci
         FROM vacaciones v
         LEFT JOIN empleados e ON e.control = v.control
         LEFT JOIN estaciones es ON es.id_estacion = e.estacion
         WHERE v.id = ?
         LIMIT 1'
    );
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('not_found', 404);
    }

    $oaci = (string)($row['oaci'] ?? '');

    $canOaci = static function (PDO $pdo, array $user, string $station) : bool {
        if (function_exists('is_admin') && is_admin()) {
            return true;
        }
        if (function_exists('user_station_matrix')) {
            $matrix = user_station_matrix($pdo, (int)($user['id'] ?? 0));
            return empty($matrix) || !empty($matrix[$station]);
        }
        return false;
    };

    if (!$canOaci($pdo, $u, $oaci)) {
        throw new RuntimeException('forbidden', 403);
    }

    $del = $pdo->prepare('DELETE FROM vacaciones WHERE id = ? LIMIT 1');
    $del->execute([$id]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    $code = (int)$e->getCode();
    if ($code < 400 || $code > 599) {
        $code = 500;
    }
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}

