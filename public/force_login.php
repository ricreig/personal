<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap_public.php';

// ⚠️ SOLO PARA PRUEBA TEMPORAL. Borra después.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1']) && php_sapi_name() !== 'cli') {
  // Permite acceso remoto solo para diagnosticar — si quieres, comenta este bloque.
}

$_SESSION['auth_ok'] = true;
$_SESSION['user_id'] = 1; // ajusta a un ID real si tu auth_user() usa esto
$_SESSION['email']   = 'ricardo.reig@gmail.com';
$_SESSION['nombre']  = 'Ricardo (forzado)';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok' => true,
  'msg' => 'Sesión forzada para diagnóstico',
  'session' => $_SESSION
], JSON_UNESCAPED_UNICODE);