<?php
// /unificado/lib/session.php
declare(strict_types=1);

/**
 * Sesión consistente para todo *.ctareig.com
 * - Nombre fijo: CRSESS
 * - Domain: .ctareig.com
 * - Path: /
 * - SameSite: Lax
 * - Secure: true (HTTPS)
 */
function session_boot(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;

  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
  $cookieParams = [
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '.ctareig.com', // <- clave para subdominios
    'secure'   => true,           // exige HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
  ];
  session_name('CRSESS');
  session_set_cookie_params($cookieParams);
  session_start();

  // Regenera si no se ha hecho en este request (mitiga fixation)
  if (empty($_SESSION['_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['_regenerated'] = time();
  }
}