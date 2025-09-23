<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function guard_is_api_request(): bool {
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $acc = $_SERVER['HTTP_ACCEPT'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return (
        stripos($xrw, 'XMLHttpRequest') !== false ||
        stripos($acc, 'application/json') !== false ||
        preg_match('#/(public/)?api/#', $uri) === 1
    );
}

function guard_deny(int $code = 401, string $msg = 'No autorizado'): void {
    if (guard_is_api_request()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $msg, 'code' => $code]);
        exit;
    }
    if (!headers_sent()) {
        header('Location: login.php?err=timeout', true, 302);
    } else {
        echo '<meta http-equiv="refresh" content="0;url=login.php?err=timeout">';
    }
    exit;
}

function require_auth_user(): array {
    $u = auth_user();
    if ($u) {
        return $u;
    }
    guard_deny(401, 'No autorizado');
    return [];
}

function require_auth_api(): array {
    $u = auth_user();
    if ($u) {
        return $u;
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'No autorizado', 'code' => 401]);
    exit;
}

function guard_allowed_oaci(PDO $pdo, array $user): array {
    if (is_admin()) {
        $st = $pdo->query("SELECT UPPER(oaci) AS o FROM estaciones WHERE oaci IS NOT NULL AND oaci<>'' ORDER BY oaci");
        return array_map(static fn(array $row): string => (string)$row['o'], $st->fetchAll(PDO::FETCH_ASSOC));
    }
    if (function_exists('user_station_matrix')) {
        $matrix = user_station_matrix($pdo, (int)($user['id'] ?? $user['uid'] ?? 0));
        if (is_array($matrix)) {
            return array_keys(array_filter($matrix));
        }
    }
    return [];
}
