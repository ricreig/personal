<?php
declare(strict_types=1);

@ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__,1);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

session_boot();
$pdo = db();
$u   = auth_user();
if (!$u) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'no-auth']); exit; }

$field = isset($_GET['field']) ? trim((string)$_GET['field']) : '';
if ($field === '') { echo json_encode(['ok'=>true, 'data'=>[]]); exit; }

/** ========= helpers permiso/estación ========= **/

// Mapa estación numérica → OACI
function oaci_from_estacion(?int $n): string {
  return match($n) {
    1=>'MMSD', 2=>'MMLP', 3=>'MMSL', 4=>'MMLT',
    5=>'MMTJ', 6=>'MMML', 7=>'MMPE', 8=>'MMHO', 9=>'MMGM',
    default=>''
  };
}

// Mapa inverso OACI → estación numérica (para normalizar matrices)
function estacion_id_from_oaci(string $oaci): ?int {
  static $rev = [
    'MMSD'=>1,'MMLP'=>2,'MMSL'=>3,'MMLT'=>4,
    'MMTJ'=>5,'MMML'=>6,'MMPE'=>7,'MMHO'=>8,'MMGM'=>9
  ];
  $o = strtoupper(trim($oaci));
  return $rev[$o] ?? null;
}

// Devuelve lista de IDs de estación permitidos para el usuario; null = todas (admin)
function allowed_station_ids(PDO $pdo, array $user): ?array {
  $role = ($user['rol'] ?? $user['role'] ?? '') ?: '';
  if ($role === 'admin' || $role === 'superuser') return null; // todos

  if (!function_exists('user_station_matrix')) {
    // si no hay matriz, por seguridad devolver vacío (nada)
    return [];
  }
  $matrix = user_station_matrix($pdo, (int)($user['id'] ?? 0));
  if (!$matrix || !is_array($matrix)) return [];

  $ids = [];
  foreach ($matrix as $key => $val) {
    if (!$val) continue;
    // aceptar claves numéricas (IDs) u OACI
    if (is_numeric($key)) {
      $ids[(int)$key] = true;
    } else {
      $id = estacion_id_from_oaci((string)$key);
      if ($id) $ids[$id] = true;
    }
  }
  // IDs válidos únicamente (1..9). Nunca incluir 0.
  $ids = array_keys($ids);
  $ids = array_values(array_filter($ids, fn($n)=> is_int($n) && $n>0));
  return $ids;
}

try {
  $allowIds = allowed_station_ids($pdo, $u); // null=todas, []=ninguna

  if ($field === 'estacion') {
    // Tomar DISTINCT ids numéricos y filtrar por permisos
    $q = "SELECT DISTINCT estacion FROM empleados WHERE estacion IS NOT NULL AND estacion <> 0";
    $params = [];
    if (is_array($allowIds)) {
      if (!count($allowIds)) { echo json_encode(['ok'=>true,'data'=>[]]); exit; }
      $place = implode(',', array_fill(0, count($allowIds), '?'));
      $q .= " AND estacion IN ($place)";
      $params = $allowIds;
    }
    $q .= " ORDER BY estacion";
    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    $nums = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $oacis = [];
    foreach ($nums as $n) {
      $o = oaci_from_estacion((int)$n);
      if ($o !== '') $oacis[$o] = true;
    }
    $list = array_keys($oacis);
    sort($list, SORT_STRING);

    echo json_encode(['ok'=>true, 'data'=>$list], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($field === 'espec') {
    // Listar áreas visibles en filas permitidas
    $params = [];
    $where  = "WHERE espec IS NOT NULL AND espec<>''";
    if (is_array($allowIds)) {
      if (!count($allowIds)) { echo json_encode(['ok'=>true,'data'=>[]]); exit; }
      $place = implode(',', array_fill(0, count($allowIds), '?'));
      $where .= " AND estacion IN ($place)";
      $params = $allowIds;
    }
    $stmt = $pdo->prepare("SELECT DISTINCT espec FROM empleados $where ORDER BY espec");
    $stmt->execute($params);
    $vals = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // limpia y ordena
    $vals = array_values(array_filter(array_map(fn($s)=> trim((string)$s), $vals), fn($s)=> $s!==''));
    echo json_encode(['ok'=>true, 'data'=>$vals], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Campo no soportado
  echo json_encode(['ok'=>true, 'data'=>[]]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>'server', 'data'=>[]]);
}