<?php
declare(strict_types=1);

// Encabezados base para JSON y evitar cacheado por proxies
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__); // …/unificado

require_once $ROOT . '/lib/session.php';
session_boot();                         // inicia sesión con cookie domain .ctareig.com

require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';
// NO require guard.php aquí: las APIs usan require_auth_api()

$u = auth_user();

function oaci_from_estacion(?int $n): string {
  return match($n) {
    1 => 'MMSD', 2 => 'MMLP', 3 => 'MMSL', 4 => 'MMLT',
    5 => 'MMTJ', 6 => 'MMML', 7 => 'MMPE', 8 => 'MMHO', 9 => 'MMGM',
    default => ''
  };
}

try {
    $pdo = db();
  $u = auth_user();
  $control = trim($_GET['control'] ?? '');
  if ($control === '') throw new Exception('control requerido', 400);

  // Traer estación del empleado sin depender de tabla "estaciones"
  $st = $pdo->prepare("SELECT estacion FROM empleados WHERE control=? LIMIT 1");
  $st->execute([$control]);
  $row = $st->fetch();
  if (!$row) throw new Exception('control no encontrado', 404);

  $oaci = oaci_from_estacion((int)$row['estacion']);
  // Validar permiso con tu misma matriz de estaciones (si eres admin, pasa)
  if (!is_admin()) {
    $matrix = user_station_matrix($pdo, (int)$u['id']); // devuelve ['MMTJ'=>true,...]
    if (empty($matrix[$oaci])) throw new Exception('sin permiso', 403);
  }

  // documentos_personal puede no existir aún -> devolver todo falso en ese caso
  $has = [
    'lic1'=>['unified'=>false,'front'=>false,'back'=>false],
    'lic2'=>['unified'=>false,'front'=>false,'back'=>false],
    'med'=>false, 'rtari'=>false, 'cert'=>false,
    'doc_lic1'=>false, 'doc_lic2'=>false,
    'extras'=>0
  ];

  // Verificar existencia de la tabla
  $tbl = $pdo->query("SHOW TABLES LIKE 'documentos_personal'")->fetchColumn();
  if (!$tbl) { echo json_encode($has, JSON_UNESCAPED_UNICODE); exit; }

  $q = $pdo->prepare("SELECT tipo FROM documentos_personal WHERE control=?");
  $q->execute([$control]);

  while ($t = $q->fetchColumn()) {
    if ($t==='licencia1') $has['lic1']['unified']=true;
    if ($t==='licencia1_front') $has['lic1']['front']=true;
    if ($t==='licencia1_back')  $has['lic1']['back']=true;

    if ($t==='licencia2') $has['lic2']['unified']=true;
    if ($t==='licencia2_front') $has['lic2']['front']=true;
    if ($t==='licencia2_back')  $has['lic2']['back']=true;

    if ($t==='examen_medico') $has['med']=true;
    if ($t==='rtari')         $has['rtari']=true;
    if ($t==='certificado')   $has['cert']=true;

    if ($t==='doc_licencia1') $has['doc_lic1']=true;
    if ($t==='doc_licencia2') $has['doc_lic2']=true;

    if (strpos($t, 'doc_extra')===0) $has['extras']++;
  }

  echo json_encode($has, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $code = ($e->getCode()>=300 && $e->getCode()<600) ? (int)$e->getCode() : 500;
  http_response_code($code);
  echo json_encode(['error'=>$e->getMessage()]);
}
