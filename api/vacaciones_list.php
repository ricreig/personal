<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php'; session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';
require_once $ROOT . '/lib/vacaciones_calc.php';
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
  // validar OACI del control
  $st = $pdo->prepare("SELECT e.nombres, e.ant, e.tipo1, es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
  $st->execute([$control]); $emp=$st->fetch(PDO::FETCH_ASSOC);
  if (!$emp || !in_array(strtoupper((string)$emp['oaci']), $allow, true)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

  // histórico
  $st=$pdo->prepare("SELECT year, tipo, periodo, inicia, reanuda, dias, resta FROM vacaciones WHERE control=? ORDER BY year DESC, tipo ASC, periodo ASC, id DESC");
  $st->execute([$control]); $hist=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // resumen año actual (base 10/10 para VAC p1/p2)
  $y = (int)date('Y');
  $fechaSol = $y . '-' . str_pad((int)date('m'),2,'0',STR_PAD_LEFT) . '-' . str_pad((int)date('d'),2,'0',STR_PAD_LEFT);
  $ant = vc_dias_ant($emp['ant'] ?? null, $y . '-12-31');
  $pr  = vc_dias_pr($emp['ant'] ?? null, $emp['tipo1'] ?? null, $y . '-12-31');

  // usados por tipo/periodo en el año
  $st=$pdo->prepare("SELECT tipo, periodo, SUM(dias) sd FROM vacaciones WHERE control=? AND year=? GROUP BY tipo,periodo");
  $st->execute([$control,$y]);
  $usadosMap=[];
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ $key=($r['tipo']??'').':'.(string)($r['periodo']??0); $usadosMap[$key]=(int)$r['sd']; }
  $vac1_rest = vc_restantes(10, (int)($usadosMap['VAC:1'] ?? 0));
  $vac2_rest = vc_restantes(10, (int)($usadosMap['VAC:2'] ?? 0));
  $ant_rest  = vc_restantes($ant, (int)($usadosMap['ANT:0'] ?? 0));
  $pr_rest   = vc_restantes($pr,  (int)($usadosMap['PR:0']  ?? 0));

  echo json_encode([
    'ok'=>true,
    'mode'=>'persona',
    'nombre'=>$emp['nombres'] ?? '',
    'historico'=>$hist,
    'resumen'=>[
      'year'=>$y,
      'vac1_rest'=>$vac1_rest,
      'vac2_rest'=>$vac2_rest,
      'ant_base'=>$ant, 'ant_rest'=>$ant_rest,
      'pr_base'=>$pr,   'pr_rest'=>$pr_rest
    ]
  ]);
  exit;
}

// Por año y estaciones: resumen por persona
$ph = implode(',', array_fill(0, count($stations), '?'));
$sql = "SELECT es.oaci, e.control, e.nombres, e.ant, e.tipo1
        FROM empleados e
        LEFT JOIN estaciones es ON es.id_estacion=e.estacion
        WHERE es.oaci IN ($ph)
        ORDER BY es.oaci, LPAD(e.control,6,'0')";
$st=$pdo->prepare($sql);
foreach($stations as $i=>$o){ $st->bindValue($i+1,$o); }
$st->execute();
$rows=[];
while($e=$st->fetch(PDO::FETCH_ASSOC)){
  $ant  = vc_dias_ant($e['ant'] ?? null, $year.'-12-31');
  $pr   = vc_dias_pr($e['ant'] ?? null, $e['tipo1'] ?? null, $year.'-12-31');
  $us = $pdo->prepare("SELECT tipo, periodo, SUM(dias) sd FROM vacaciones WHERE control=? AND year=? GROUP BY tipo,periodo");
  $us->execute([$e['control'],$year]);
  $uMap=[]; foreach($us->fetchAll(PDO::FETCH_ASSOC) as $r){ $uMap[($r['tipo']??'').':'.(string)($r['periodo']??0)] = (int)$r['sd']; }
  $rows[] = [
    'oaci'=>$e['oaci'],
    'control'=>$e['control'],
    'nombres'=>$e['nombres'],
    'vac1_rest'=>vc_restantes(10,(int)($uMap['VAC:1']??0)),
    'vac2_rest'=>vc_restantes(10,(int)($uMap['VAC:2']??0)),
    'ant_base'=>$ant, 'ant_rest'=>vc_restantes($ant,(int)($uMap['ANT:0']??0)),
    'pr_base'=>$pr, 'pr_rest'=>vc_restantes($pr,(int)($uMap['PR:0']??0)),
  ];
}
echo json_encode(['ok'=>true,'mode'=>'anio','year'=>$year,'rows'=>$rows]);
