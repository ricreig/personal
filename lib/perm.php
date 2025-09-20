<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * Mapa estación numérica → OACI (mismo que usamos en la API)
 */
function cr_oaci_from_num(?int $n): ?string {
  return match((int)$n) {
    1=>'MMSD',2=>'MMLP',3=>'MMSL',4=>'MMLT',5=>'MMTJ',6=>'MMML',7=>'MMPE',8=>'MMHO',9=>'MMGM',
    default=>null
  };
}

/**
 * Devuelve lista de OACI permitidos para el usuario (o ['*'] si admin)
 */
function cr_user_allowed_oaci(PDO $pdo, array $user): array {
  $role = $user['rol'] ?? $user['role'] ?? '';
  if ($role === 'admin') return ['*'];
  if (function_exists('user_station_matrix')) {
    $m = user_station_matrix($pdo, (int)($user['id'] ?? 0)); // ['MMTJ'=>true,...]
    return array_keys(array_filter((array)$m));
  }
  return []; // sin matriz → nada
}

/**
 * ¿El usuario puede gestionar al trabajador con ese control?
 * Regla: admin ⇒ sí. Si no, estación del trabajador ∈ estaciones permitidas del usuario.
 */
function cr_can_manage_control(PDO $pdo, array $user, $control): bool {
  $allow = cr_user_allowed_oaci($pdo, $user);
  if ($allow === ['*']) return true;

  $st = $pdo->prepare("SELECT estacion FROM empleados WHERE control = ? LIMIT 1");
  $st->execute([ (string)$control ]);
  $estNum = $st->fetchColumn();
  if ($estNum === false) return false;

  $oaci = cr_oaci_from_num((int)$estNum);
  if (!$oaci) return false;

  return in_array($oaci, $allow, true);
}
