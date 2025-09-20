<?php
declare(strict_types=1);
require_once dirname(__DIR__). '/lib/bootstrap_public.php';

header('Content-Type: application/json; charset=utf-8');

$u = null;
try {
  $u = auth_user();
} catch (Throwable $e) {
  $u = ['_auth_user_error' => $e->getMessage()];
}

echo json_encode([
  'host'     => $_SERVER['HTTP_HOST'] ?? '',
  'uri'      => $_SERVER['REQUEST_URI'] ?? '',
  'session'  => [
    'name'   => session_name(),
    'id'     => session_id(),
    'vars'   => $_SESSION ?? [],
  ],
  'auth_user' => $u,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);