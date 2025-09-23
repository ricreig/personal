<?php
declare(strict_types=1);
require_once dirname(__DIR__,1) . '/lib/bootstrap_public.php';
require_once dirname(__DIR__,1) . '/lib/paths.php';
cr_define_php_paths(); // define constantes PHP si no existen

// Cerrar sesión de forma segura
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'] ?? false, $p['httponly'] ?? true);
}
@session_destroy();

// Borra cookie de “Recordarme” si la utilizas (ajusta nombre/dom path)
if (!empty($_COOKIE['remember'])) {
  setcookie('remember', '', time() - 3600, '/', '', false, true);
}

header('Location: login.php?err=timeout');
exit;