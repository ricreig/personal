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

function user_oaci(PDO $pdo, array $u): array {
  if (is_admin()) {
    $st=$pdo->query("SELECT UPPER(oaci) o FROM estaciones WHERE oaci IS NOT NULL");
    return array_map(fn($r)=>$r['o'],$st->fetchAll());
  }
  if (function_exists('user_station_matrix')) {
    $m=user_station_matrix($pdo,(int)($u['id']??0)); return array_keys(array_filter($m));
  }
  return [];
}
$allow = array_map('strtoupper', user_oaci($pdo,$u));
if ($stations) { $stations = array_values(array_intersect(array_map('strtoupper',$stations), $allow)); }
else { $stations = $allow; }

if ($mode==='persona' && $control>0) {
  $st = $pdo->prepare("SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
  $st->execute([$control]); $row=$st->fetch();
  if (!$row || !in_array(strtoupper((string)$row['oaci']), $allow, true)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
  $st=$pdo->prepare("SELECT MIN(year) AS miny, MAX(year) AS maxy FROM txt WHERE control=?");
  $st->execute([$control]); $r=$st->fetch();
  $miny = (int)($r['miny'] ?? 2017); if ($miny<=0) $miny=2017;
  $maxy = (int)date('Y');
  $out=[];
  for($y=$maxy;$y>=$miny;--$y){
    $s=$pdo->prepare("SELECT * FROM txt WHERE control=? AND year=? LIMIT 1");
    $s->execute([$control,$y]); $p=$s->fetch(PDO::FETCH_ASSOC);
    if(!$p){
      $out[]=['year'=>$y,'js'=>'0','vs'=>'0','dm'=>'0','ds'=>'0','muert'=>'0','ono'=>'0','fnac'=>null];
    } else {
      $p['year']=(int)$y;
      // fecha nacimiento de empleados
      $fn = $pdo->prepare("SELECT fecha_nacimiento FROM empleados WHERE control=? LIMIT 1");
      $fn->execute([$control]); $r2=$fn->fetch();
      $p['fnac'] = $r2['fecha_nacimiento'] ?? null;
      $out[]=$p;
    }
  }
  echo json_encode(['ok'=>true,'mode'=>'persona','rows'=>$out]); exit;
}

// año
$ph = implode(',', array_fill(0, count($stations), '?'));
$sql = "SELECT es.oaci, e.control, e.nombres,
               t.js,t.vs,t.dm,t.ds,t.muert,t.ono, e.fecha_nacimiento AS fnac
        FROM empleados e
        LEFT JOIN estaciones es ON es.id_estacion=e.estacion
        LEFT JOIN txt t ON t.control=e.control AND t.year = :y
        WHERE es.oaci IN ($ph)
        ORDER BY es.oaci, LPAD(e.control,6,'0')";
$st=$pdo->prepare($sql);
$params=$stations; $st->bindValue(':y',$year,PDO::PARAM_INT);
foreach($stations as $i=>$o){ $st->bindValue($i+1,$o); }
$st->execute();
$rows=[];
while($r=$st->fetch(PDO::FETCH_ASSOC)){
  $rows[]=[
    'oaci'=>$r['oaci'],
    'control'=>$r['control'],
    'nombres'=>$r['nombres'],
    'js'=>$r['js']??'0','vs'=>$r['vs']??'0','dm'=>$r['dm']??'0','ds'=>$r['ds']??'0','muert'=>$r['muert']??'0','ono'=>$r['ono']??'0',
    'fnac'=>$r['fnac']??null
  ];
}
echo json_encode(['ok'=>true,'mode'=>'anio','year'=>$year,'rows'=>$rows]);
