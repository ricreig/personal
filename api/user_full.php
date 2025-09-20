<?php
declare(strict_types=1);

// /api/user_full.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$ROOT = dirname(__DIR__);

require_once $ROOT . '/lib/session.php';
session_boot();

require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

function _json($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$ctrl = (int)($_GET['ctrl'] ?? 0);
if ($ctrl <= 0) { http_response_code(400); _json(['ok'=>false,'error'=>'ctrl requerido']); }

$pdo = db();

// Empleado + OACI
$st = $pdo->prepare("SELECT e.*, es.oaci
                     FROM empleados e
                     LEFT JOIN estaciones es ON es.id_estacion = e.estacion
                     WHERE e.control = ? LIMIT 1");
$st->execute([$ctrl]);
$emp = $st->fetch(PDO::FETCH_ASSOC);
if (!$emp) { http_response_code(404); _json(['ok'=>false,'error'=>'No encontrado']); }

$oaci = strtoupper(trim((string)($emp['oaci'] ?? '')));

// Permisos: preferir has_access_oaci si existe
$canView = true;
if (function_exists('has_access_oaci')) {
  $canView = has_access_oaci($oaci, false);
} elseif (function_exists('user_station_matrix')) {
  $u = auth_user();
  $mat = user_station_matrix($pdo, (int)($u['id'] ?? 0));
  if (is_array($mat) && $mat) { $canView = !empty($mat[$oaci]); }
}
if (!$canView) { http_response_code(403); _json(['ok'=>false,'error'=>'Sin permiso']); }

// PECOs
$st = $pdo->prepare("SELECT year, dia1,dia2,dia3,dia4,dia5,dia6,dia7,dia8,dia9,dia10,dia11,dia12
                     FROM pecos WHERE control=? ORDER BY year DESC");
$st->execute([$ctrl]);
$pecos = $st->fetchAll(PDO::FETCH_ASSOC);

// TXT
$st = $pdo->prepare("SELECT year, js,vs,dm,ds,muert,ono
                     FROM txt WHERE control=? ORDER BY year DESC");
$st->execute([$ctrl]);
$txt = $st->fetchAll(PDO::FETCH_ASSOC);

// Vacaciones histórico
$st = $pdo->prepare("SELECT year, tipo, periodo, inicia, reanuda, dias, resta, obs
                     FROM vacaciones WHERE control=? ORDER BY year DESC, periodo ASC");
$st->execute([$ctrl]);
$vac_hist = $st->fetchAll(PDO::FETCH_ASSOC);

// Resumen año actual (reglas del sistema viejo)
// ANT a partir de antigüedad (e.ant en formato dd/mm/yyyy) vs 1° de enero del año actual
function calc_dias_ant(?string $ant, int $year): mixed {
  if (!$ant) return "NO INFO";
  $parts = explode('/', $ant);
  if (count($parts) !== 3) return "NO INFO";
  $antISO = sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
  $d1 = date_create($antISO);
  $d2 = date_create(sprintf('%04d-%02d-%02d', $year, (int)$parts[1], (int)$parts[0]));
  if (!$d1 || !$d2) return "NO INFO";
  $diff = date_diff($d1, $d2);
  $years = (int)$diff->y;
  if ($years >= 35) return 6;
  if ($years >= 30) return 5;
  if ($years >= 25) return 4;
  if ($years >= 20) return 3;
  if ($years >= 15) return 2;
  if ($years >= 10) return 1;
  return "-";
}
// PR solo si tipo1 == 'CTA III'
function calc_pr(?string $ant, string $tipo1, int $year): mixed {
  if (trim(strtoupper($tipo1)) !== 'CTA III') return "-";
  if (!$ant) return "NO INFO";
  $parts = explode('/', $ant);
  if (count($parts) !== 3) return "NO INFO";
  $antISO = sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
  $d1 = date_create($antISO);
  $d2 = date_create(sprintf('%04d-%02d-%02d', $year, (int)$parts[1], (int)$parts[0]));
  if (!$d1 || !$d2) return "NO INFO";
  $years = (int)date_diff($d1, $d2)->y;
  if ($years >= 20) return 10;
  if ($years >= 17) return 9;
  if ($years >= 14) return 8;
  if ($years >= 11) return 7;
  if ($years >= 8)  return 6;
  if ($years >= 5)  return 5;
  return "-";
}
function sum_vac(PDO $pdo, int $ctrl, int $year, string $tipo, int $periodo): int {
  $st = $pdo->prepare("SELECT SUM(dias) AS s FROM vacaciones
                       WHERE control=? AND year=? AND tipo=? AND periodo=?");
  $st->execute([$ctrl, $year, $tipo, $periodo]);
  $s = (int)($st->fetchColumn() ?: 0);
  return $s;
}
$yearNow = (int)date('Y');
$vac1_nom = 10;
$vac2_nom = 10;
$ant_nom  = calc_dias_ant($emp['ant'] ?? null, $yearNow);
$pr_nom   = calc_pr($emp['ant'] ?? null, (string)($emp['tipo1'] ?? ''), $yearNow);

$vac_resumen = [
  'year'     => $yearNow,
  'vac1_rem' => ($vac1_nom !== "-" ? max(0, $vac1_nom - sum_vac($pdo, $ctrl, $yearNow, 'VAC', 1)) : "-"),
  'vac2_rem' => ($vac2_nom !== "-" ? max(0, $vac2_nom - sum_vac($pdo, $ctrl, $yearNow, 'VAC', 2)) : "-"),
  'ant_rem'  => (is_numeric($ant_nom) ? max(0, (int)$ant_nom - sum_vac($pdo, $ctrl, $yearNow, 'ANT', 0)) : $ant_nom),
  'pr_rem'   => (is_numeric($pr_nom)  ? max(0, (int)$pr_nom  - sum_vac($pdo, $ctrl, $yearNow, 'PR',  0)) : $pr_nom),
];

// Incapacidades (tabla "incapacidad": NC = control)
$st = $pdo->prepare("SELECT INICIA, TERMINA, DIAS, UMF, DIAGNOSTICO, FOLIO
                     FROM incapacidad WHERE NC = ? ORDER BY INICIA DESC");
$st->execute([$ctrl]);
$incap = $st->fetchAll(PDO::FETCH_ASSOC);

_json([
  'ok' => true,
  'empleado' => [
    'control' => $emp['control'],
    'nombres' => $emp['nombres'],
    'oaci'    => $oaci,
    'espec'   => $emp['espec'],
    'nivel'   => $emp['nivel'],
    'plaza'   => $emp['plaza'],
    'puesto'  => $emp['puesto'],
    'curp'    => $emp['curp'],
    'fecha_nacimiento' => $emp['fecha_nacimiento'],
    'ant'     => $emp['ant'],
    'email'   => $emp['email'],
    'tipo1'   => $emp['tipo1'],
    'vigencia1' => $emp['vigencia1'],
    'tipo2'   => $emp['tipo2'],
    'vigencia2' => $emp['vigencia2'],
    'examen_vig1' => $emp['examen_vig1'], // anexo
    'examen_vig2' => $emp['examen_vig2'], // psicofísico
    'rtari'   => $emp['rtari'],
    'rtari_vig' => $emp['rtari_vig'],
  ],
  'pecos' => $pecos,
  'txt'   => $txt,
  'vac_hist' => $vac_hist,
  'vac_resumen' => $vac_resumen,
  'incap' => $incap
]);
