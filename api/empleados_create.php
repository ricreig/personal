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
require_once $ROOT . '/lib/perm.php';

function json_exit(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$u = auth_user();
if (!$u) {
    json_exit(['ok' => false, 'error' => 'No autorizado'], 401);
}

$role = (string)($u['role'] ?? $u['rol'] ?? '');
if ($role === 'viewer') {
    json_exit(['ok' => false, 'error' => 'Sin permiso para crear registros'], 403);
}

$pdo = db();

$ESPECS = ['MANDOS','ATCO','OSIV','ADMIN','IDS'];
$LIC_TYPES = ['CTA III','OOA','MET I','TEC MTTO','CAM'];
$LCAR_TYPES = ['A','B','C','DL'];
$CLASE_TYPES = ['GPO-3','GPO-4','CLASE-3'];

$rawControl = trim((string)($_POST['control'] ?? ''));
$control = preg_replace('/\D+/', '', $rawControl);
if ($control === '') {
    json_exit(['ok' => false, 'error' => 'El No. de control es obligatorio.'], 400);
}

$check = $pdo->prepare('SELECT 1 FROM empleados WHERE control = ? LIMIT 1');
$check->execute([$control]);
if ($check->fetchColumn()) {
    json_exit(['ok' => false, 'error' => 'Ya existe un registro con ese número de control.'], 409);
}

$estacionId = (int)($_POST['estacion'] ?? 0);
if ($estacionId <= 0) {
    json_exit(['ok' => false, 'error' => 'Selecciona una estación válida.'], 400);
}

$stationStmt = $pdo->prepare('SELECT UPPER(oaci) AS oaci FROM estaciones WHERE id_estacion = ? LIMIT 1');
$stationStmt->execute([$estacionId]);
$oaci = trim((string)$stationStmt->fetchColumn());
if ($oaci === '') {
    json_exit(['ok' => false, 'error' => 'La estación seleccionada no existe.'], 400);
}

$allowed = cr_user_allowed_oaci($pdo, $u);
if ($allowed !== ['*'] && !in_array($oaci, $allowed, true)) {
    json_exit(['ok' => false, 'error' => 'No tienes permisos para registrar personal en esa estación.'], 403);
}

$normUpper = static function (mixed $value): ?string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return null;
    }
    return mb_strtoupper($value, 'UTF-8');
};

$normName = static function (mixed $value): ?string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return null;
    }
    $lower = mb_strtolower($value, 'UTF-8');
    return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
};

$normDate = static function (mixed $value): ?string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return null;
    }
    return $value;
};

$normPlain = static function (mixed $value): ?string {
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
};

$espec = strtoupper(trim((string)($_POST['espec'] ?? '')));
if ($espec !== '' && !in_array($espec, $ESPECS, true)) {
    json_exit(['ok' => false, 'error' => 'Área no válida.'], 400);
}

$tipo1 = strtoupper(trim((string)($_POST['tipo1'] ?? '')));
if ($tipo1 !== '' && !in_array($tipo1, $LIC_TYPES, true)) {
    json_exit(['ok' => false, 'error' => 'Tipo de licencia 1 no válido.'], 400);
}

$tipo2 = strtoupper(trim((string)($_POST['tipo2'] ?? '')));
if ($tipo2 !== '' && !in_array($tipo2, $LIC_TYPES, true)) {
    json_exit(['ok' => false, 'error' => 'Tipo de licencia 2 no válido.'], 400);
}

$examen1 = strtoupper(trim((string)($_POST['examen1'] ?? '')));
if ($examen1 !== '' && !in_array($examen1, $LCAR_TYPES, true)) {
    json_exit(['ok' => false, 'error' => 'Tipo LCAR/DL no válido.'], 400);
}

$examen2 = strtoupper(trim((string)($_POST['examen2'] ?? '')));
if ($examen2 !== '' && !in_array($examen2, $CLASE_TYPES, true)) {
    json_exit(['ok' => false, 'error' => 'Clase de examen médico no válida.'], 400);
}

$nombres = $normName($_POST['nombres'] ?? '');
if ($nombres === null) {
    json_exit(['ok' => false, 'error' => 'El nombre es obligatorio.'], 400);
}

$email = $normPlain($_POST['email'] ?? '');
if ($email !== null) {
    $email = mb_strtolower($email, 'UTF-8');
}

$data = [
    'control'          => $control,
    'siglas'           => $normUpper($_POST['siglas'] ?? ''),
    'nombres'          => $nombres,
    'email'            => $email,
    'rfc'              => $normUpper($_POST['rfc'] ?? ''),
    'curp'             => $normUpper($_POST['curp'] ?? ''),
    'fecha_nacimiento' => $normDate($_POST['fecha_nacimiento'] ?? ''),
    'ant'              => $normDate($_POST['ant'] ?? ''),
    'direccion'        => $normUpper($_POST['direccion'] ?? ''),
    'plaza'            => $normUpper($_POST['plaza'] ?? ''),
    'espec'            => $espec === '' ? null : $espec,
    'estacion'         => $estacionId,
    'nivel'            => $normPlain($_POST['nivel'] ?? ''),
    'nss'              => $normPlain($_POST['nss'] ?? ''),
    'puesto'           => $normUpper($_POST['puesto'] ?? ''),
    'tipo1'            => $tipo1 === '' ? null : $tipo1,
    'licencia1'        => $normPlain($_POST['licencia1'] ?? ''),
    'vigencia1'        => $normDate($_POST['vigencia1'] ?? ''),
    'tipo2'            => $tipo2 === '' ? null : $tipo2,
    'licencia2'        => $normPlain($_POST['licencia2'] ?? ''),
    'vigencia2'        => $normDate($_POST['vigencia2'] ?? ''),
    'examen1'          => $examen1 === '' ? null : $examen1,
    'examen_vig1'      => $normDate($_POST['examen_vig1'] ?? ''),
    'examen2'          => $examen2 === '' ? null : $examen2,
    'examen_vig2'      => $normDate($_POST['examen_vig2'] ?? ''),
    'rtari'            => $normUpper($_POST['rtari'] ?? ''),
    'rtari_vig'        => $normDate($_POST['rtari_vig'] ?? ''),
    'exp_med'          => $normPlain($_POST['exp_med'] ?? ''),
];

$sql = 'INSERT INTO empleados (
            control, siglas, nombres, email, rfc, curp, fecha_nacimiento, ant, direccion,
            plaza, espec, estacion, nivel, nss, puesto,
            tipo1, licencia1, vigencia1,
            tipo2, licencia2, vigencia2,
            examen1, examen_vig1, examen2, examen_vig2,
            rtari, rtari_vig, exp_med
        ) VALUES (
            :control, :siglas, :nombres, :email, :rfc, :curp, :fecha_nacimiento, :ant, :direccion,
            :plaza, :espec, :estacion, :nivel, :nss, :puesto,
            :tipo1, :licencia1, :vigencia1,
            :tipo2, :licencia2, :vigencia2,
            :examen1, :examen_vig1, :examen2, :examen_vig2,
            :rtari, :rtari_vig, :exp_med
        )';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
} catch (Throwable $e) {
    json_exit([
        'ok' => false,
        'error' => 'No se pudo crear el registro.',
        'detail' => $e->getMessage(),
    ], 500);
}

json_exit(['ok' => true, 'control' => $control]);
