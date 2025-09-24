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
  echo json_encode(['ok' => false, 'error' => 'unauthorized', 'msg' => 'Sesión inválida.']);
  exit;
}
$creatorRole = strtolower((string)($u['role'] ?? 'viewer'));
if ($creatorRole === 'viewer') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'forbidden', 'msg' => 'No tienes permisos para crear usuarios.']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$pdo = db();

$normalizeOaci = static function ($value): ?string {
  $oaci = strtoupper(trim((string)$value));
  if ($oaci === '') {
    return null;
  }
  if (!preg_match('/^[A-Z0-9]{4}$/', $oaci)) {
    return null;
  }
  return substr($oaci, 0, 4);
};

$rawView = array_keys($_POST['station_view'] ?? []);
$rawEdit = array_keys($_POST['station_edit'] ?? []);
$selectedView = [];
foreach ($rawView as $val) {
  $norm = $normalizeOaci($val);
  if ($norm) {
    $selectedView[$norm] = true;
  }
}
$selectedEdit = [];
foreach ($rawEdit as $val) {
  $norm = $normalizeOaci($val);
  if ($norm) {
    $selectedEdit[$norm] = true;
  }
}

$allowedView = [];
$allowedEdit = [];
if ($creatorRole !== 'admin') {
  $perms = $pdo->prepare('SELECT UPPER(TRIM(oaci)) AS oaci, MAX(can_view) AS can_view, MAX(can_edit) AS can_edit FROM user_station_perms WHERE user_id = ? GROUP BY oaci');
  $perms->execute([(int)$u['id']]);
  foreach ($perms->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $oaci = strtoupper(trim((string)($row['oaci'] ?? '')));
    if ($oaci === '') {
      continue;
    }
    if ((int)($row['can_view'] ?? 0) === 1) {
      $allowedView[$oaci] = true;
    }
    if ((int)($row['can_edit'] ?? 0) === 1) {
      $allowedEdit[$oaci] = true;
    }
  }
  if (!$allowedView) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'no_station_access', 'msg' => 'No tienes estaciones asignadas para crear usuarios.']);
    exit;
  }
  $filteredView = [];
  foreach (array_keys($selectedView) as $oaci) {
    if (isset($allowedView[$oaci])) {
      $filteredView[$oaci] = true;
    }
  }
  $selectedView = $filteredView;
  $filteredEdit = [];
  foreach (array_keys($selectedEdit) as $oaci) {
    if (isset($allowedEdit[$oaci])) {
      $filteredEdit[$oaci] = true;
    }
  }
  $selectedEdit = $filteredEdit;
  if (!$selectedView) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_station', 'msg' => 'Selecciona al menos una estación permitida.']);
    exit;
  }
} else {
  // Para administradores: garantizar que los edits estén incluidos en view
  foreach (array_keys($selectedEdit) as $oaci) {
    $selectedView[$oaci] = true;
  }
}

// Asegura que las estaciones con edición también tengan permiso de vista
foreach (array_keys($selectedEdit) as $oaci) {
  $selectedView[$oaci] = true;
}

$email = trim((string)($_POST['email'] ?? ''));
$nombre = trim((string)($_POST['nombre'] ?? ''));
$role = (string)($_POST['role'] ?? 'viewer');
$controlRaw = trim((string)($_POST['control'] ?? ''));
$pass = (string)($_POST['pass'] ?? '');
$isActive = !empty($_POST['is_active']) ? 1 : 0;

if ($email === '' || $nombre === '' || $pass === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_email']);
  exit;
}

$allowedRoles = ['admin', 'regional', 'estacion', 'viewer'];
if (!in_array($role, $allowedRoles, true)) {
  $role = 'viewer';
}

$control = $controlRaw === '' ? null : $controlRaw;
if ($control !== null) {
  $control = trim($control);
  if ($control === '') {
    $control = null;
  }
}

$st = $pdo->prepare('SELECT id FROM app_users WHERE email = ? LIMIT 1');
$st->execute([$email]);
if ($st->fetch()) {
  http_response_code(409);
  echo json_encode(['ok' => false, 'error' => 'email_in_use', 'msg' => 'El correo ya está registrado.']);
  exit;
}

$hash = password_hash($pass, PASSWORD_DEFAULT);

try {
  $pdo->beginTransaction();

  $insUser = $pdo->prepare('INSERT INTO app_users (email, nombre, pass_hash, role, control, is_active) VALUES (?,?,?,?,?,?)');
  $insUser->execute([$email, $nombre, $hash, $role, $control, $isActive]);
  $newUserId = (int)$pdo->lastInsertId();

  if (!empty($selectedView)) {
    $insPerm = $pdo->prepare('INSERT INTO user_station_perms (user_id, oaci, can_view, can_edit) VALUES (?,?,?,?)');
    foreach (array_keys($selectedView) as $oaci) {
      $canEdit = !empty($selectedEdit[$oaci]) ? 1 : 0;
      $insPerm->execute([$newUserId, $oaci, 1, $canEdit]);
    }
  }

  $pdo->commit();
  echo json_encode(['ok' => true, 'user_id' => $newUserId]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error', 'msg' => 'No se pudo crear el usuario.']);
}
