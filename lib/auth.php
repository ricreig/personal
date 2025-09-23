<?php
declare(strict_types=1);
/**
 * Autenticación basada en:
 *  - Tabla app_users (id, email, pass_hash, nombre, role, is_active, control)
 *  - Tabla user_station_perms (user_id, oaci, can_view, can_edit)
 *
 * expone:
 *  - auth_login($email,$pass, ['remember'=>bool]) : bool
 *  - auth_user() : ?array
 *  - is_admin() : bool                // role = 'admin'
 *  - user_oaci_perms() : ?array       // null = TODAS; array de OACI si tiene restricciones
 *  - has_access_oaci($oaci,$edit=false) : bool
 *  - has_access_estacion_id($id,$edit=false) : bool // mapea 1..9 -> OACI
 *  - auth_logout() : void
 */

require_once __DIR__ . '/db.php';

const SESSION_NAME = 'CRSESS';

// Mapa ID→OACI según tu definición
function estacion_id_to_oaci(int $id): ?string {
  $map = [
    1=>'MMSD', 2=>'MMLP', 3=>'MMSL', 4=>'MMLT',
    5=>'MMTJ', 6=>'MMML', 7=>'MMPE', 8=>'MMHO', 9=>'MMGM'
  ];
  return $map[$id] ?? null;
}

function auth_login(string $email, string $pass, array $opts=[]): bool {
  $email = trim(mb_strtolower($email));
  if ($email==='' || $pass==='') return false;

  $pdo = db();
  $st = $pdo->prepare("SELECT id, email, nombre, role, is_active, control, pass_hash
                       FROM app_users
                       WHERE email = :email
                       LIMIT 1");
  $st->execute([':email'=>$email]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
  if (!$u || (int)$u['is_active'] !== 1) return false;

  if (!password_verify($pass, (string)$u['pass_hash'])) return false;

  // Carga permisos (si no es admin)
  $perms = null; // null => todas
  if ($u['role'] !== 'admin') {
    $ps = $pdo->prepare("SELECT oaci, can_view, can_edit FROM user_station_perms WHERE user_id = :uid");
    $ps->execute([':uid'=>(int)$u['id']]);
    $perms = [];
    while ($row = $ps->fetch(PDO::FETCH_ASSOC)) {
      $perms[$row['oaci']] = [
        'view' => (int)$row['can_view'] === 1,
        'edit' => (int)$row['can_edit'] === 1,
      ];
    }
  }

  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    @session_start();
  }
  session_regenerate_id(true);

    $_SESSION['_regenerated'] = time();
    $_SESSION['auth_ok']      = true;
    $_SESSION['uid']          = (int)$u['id'];       // <- clave que auth_user espera
    $_SESSION['user_id']      = (int)$u['id'];       // compat
    $_SESSION['email']        = $u['email'];
    $_SESSION['nombre']       = $u['nombre'];
    $_SESSION['rol']          = $u['role'];          // ya la tenías así
    $_SESSION['control']      = $u['control'];
    $_SESSION['perms_oaci']   = $perms;

  // “Recordarme” (30 días)
    if ((!empty($opts['remember'])) && ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
      setcookie(session_name(), session_id(), [
        'expires'  => time() + 60*60*24*30,
        'path'     => $p['path'] ?: '/',
        'domain'   => $p['domain'] ?? '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
    }

  return true;
}


function auth_user(): ?array {
  // Asegura sesión
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    @session_start();
  }

  // Migración silenciosa por compatibilidad: user_id -> uid
  if (!isset($_SESSION['uid']) && isset($_SESSION['user_id'])) {
    $_SESSION['uid'] = (int)$_SESSION['user_id'];
  }

  if (empty($_SESSION['uid'])) return null;

  static $cached = null;
  if ($cached !== null) return $cached;

  $pdo = db();
  $st = $pdo->prepare("SELECT id, email, nombre, role, control, is_active FROM app_users WHERE id=? LIMIT 1");
  $st->execute([ (int)$_SESSION['uid'] ]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  if (!$u || (int)$u['is_active'] !== 1) return null;

  if (!isset($u['role']) || $u['role'] === null || $u['role'] === '') {
    $u['role'] = 'viewer';
  }
  $u['control'] = $u['control'] ?? null;

  $cached = $u;
  return $cached;
}

function is_admin(): bool {
  $u = auth_user();
  return $u ? (($u['role'] ?? 'viewer') === 'admin') : false;
}

function is_regional(): bool {
  $u = auth_user();
  return $u ? (($u['role'] ?? '') === 'regional') : false;
}

function is_estacion(): bool {
  $u = auth_user();
  return $u ? (($u['role'] ?? '') === 'estacion') : false;
}

function auth_logout(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    @session_start();
  }
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], true, true);
  }
  session_destroy();
}

// Añadir al FINAL de lib/auth.php (NO reemplazar archivo completo)
if (!function_exists('user_station_matrix')) {
    /**
     * Matriz de estaciones (OACI) que el usuario puede VER: ['MMTJ'=>true, ...]
     * Fuente: user_station_perms (can_view=1)
     */
    function user_station_matrix(PDO $pdo, int $userId): array {
        if ($userId <= 0) return [];
        $st = $pdo->prepare("
            SELECT UPPER(TRIM(oaci)) AS oaci, MAX(can_view) AS can_view
            FROM user_station_perms
            WHERE user_id = ?
            GROUP BY oaci
        ");
        $st->execute([$userId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $o = strtoupper(trim((string)($r['oaci'] ?? '')));
            if ($o !== '') { $out[$o] = ((int)($r['can_view'] ?? 0)) === 1; }
        }
        return $out;
    }
}
