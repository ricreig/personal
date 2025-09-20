<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php'; session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';
$pdo=db(); $u=auth_user(); if(!$u){ http_response_code(401); echo json_encode(['error'=>'auth']); exit; }

$mode = $_GET['mode'] ?? 'persona'; // persona | anio
$control = isset($_GET['control']) ? (int)$_GET['control'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$stations = isset($_GET['stations']) ? explode(',', (string)$_GET['stations']) : [];

function allow_oaci(PDO $pdo, array $u): array {
  if (is_admin()) {
    $st=$pdo->query("SELECT UPPER(oaci) o FROM estaciones WHERE oaci IS NOT NULL");
    return array_map(fn($r)=>$r['o'],$st->fetchAll());
  }
  if (function_exists('user_station_matrix')) {
    $m=user_station_matrix($pdo,(int)($u['id']??0)); return array_keys(array_filter($m));
  }
  return [];
}
$allow = array_map('strtoupper', allow_oaci($pdo,$u));
if ($stations) { $stations = array_values(array_intersect(array_map('strtoupper',$stations), $allow)); }
else { $stations = $allow; }

if ($mode==='persona' && $control>0) {
  $st = $pdo->prepare("SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
  $st->execute([$control]); $row=$st->fetch();
  if (!$row || !in_array(strtoupper((string)$row['oaci']), $allow, true)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
  $st=$pdo->prepare("SELECT Id, INICIA, TERMINA, DIAS, UMF, DIAGNOSTICO, FOLIO FROM incapacidad WHERE NC=? AND YEAR(STR_TO_DATE(INICIA,'%d/%m/%Y')) BETWEEN 2017 AND YEAR(NOW()) ORDER BY STR_TO_DATE(INICIA,'%d/%m/%Y') DESC, Id DESC");
  $st->execute([$control]); $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  echo json_encode(['ok'=>true,'mode'=>'persona','rows'=>$rows]);
  exit;
}

$ph = implode(',', array_fill(0, count($stations), '?'));
$sql="SELECT es.oaci, e.control, e.nombres, i.INICIA, i.TERMINA, i.DIAS, i.UMF, i.DIAGNOSTICO, i.FOLIO
      FROM incapacidad i
      INNER JOIN empleados e ON e.control=i.NC
      LEFT JOIN estaciones es ON es.id_estacion=e.estacion
      WHERE es.oaci IN ($ph) AND YEAR(STR_TO_DATE(i.INICIA,'%d/%m/%Y')) = :y
      ORDER BY es.oaci, LPAD(e.control,6,'0')";
$st=$pdo->prepare($sql);
foreach($stations as $i=>$o){ $st->bindValue($i+1,$o); }
$st->bindValue(':y',$year,PDO::PARAM_INT);
$st->execute();
$rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
echo json_encode(['ok'=>true,'mode'=>'anio','year'=>$year,'rows'=>$rows]);
