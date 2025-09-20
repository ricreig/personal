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

// helper estaciones permitidas
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
  // validar que control pertenezca a OACI permitida
  $st = $pdo->prepare("SELECT es.oaci FROM empleados e LEFT JOIN estaciones es ON es.id_estacion=e.estacion WHERE e.control=? LIMIT 1");
  $st->execute([$control]); $row=$st->fetch();
  if (!$row || !in_array(strtoupper((string)$row['oaci']), $allow, true)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
  // Años desde primer registro al actual para pecos
  $st=$pdo->prepare("SELECT MIN(year) AS miny, MAX(year) AS maxy FROM pecos WHERE control=?");
  $st->execute([$control]); $r=$st->fetch();
  $miny = (int)($r['miny'] ?? 2017); if ($miny<=0) $miny=2017;
  $maxy = (int)date('Y');
  $out=[];
  for($y=$maxy;$y>=$miny;--$y){
    $s=$pdo->prepare("SELECT * FROM pecos WHERE control=? AND year=? LIMIT 1");
    $s->execute([$control,$y]); $p=$s->fetch(PDO::FETCH_ASSOC);
    if(!$p){
      $out[]=['year'=>$y,'dia1'=>'0','dia2'=>'0','dia3'=>'0','dia4'=>'0','dia5'=>'0','dia6'=>'0','dia7'=>'0','dia8'=>'0','dia9'=>'0','dia10'=>'0','dia11'=>'0','dia12'=>'0'];
    } else {
      $p['year']=(int)$y; $out[]=$p;
    }
  }
  echo json_encode(['ok'=>true,'mode'=>'persona','rows'=>$out]); exit;
}

// mode año
$ph = implode(',', array_fill(0, count($stations), '?'));
$sql = "SELECT es.oaci, e.control, e.nombres,
               p.dia1,p.dia2,p.dia3,p.dia4,p.dia5,p.dia6,p.dia7,p.dia8,p.dia9,p.dia10,p.dia11,p.dia12
        FROM empleados e
        LEFT JOIN estaciones es ON es.id_estacion=e.estacion
        LEFT JOIN pecos p ON p.control=e.control AND p.year = :y
        WHERE es.oaci IN ($ph)
        ORDER BY es.oaci, LPAD(e.control,6,'0')";
$st=$pdo->prepare($sql);
$params=$stations; $st->bindValue(':y',$year,PDO::PARAM_INT);
foreach($stations as $i=>$o){ $st->bindValue($i+1,$o); } // positional for IN
$st->execute();
$rows=[];
while($r=$st->fetch(PDO::FETCH_ASSOC)){
  $rows[]=[
    'oaci'=>$r['oaci'],
    'control'=>$r['control'],
    'nombres'=>$r['nombres'],
    'dia1'=>$r['dia1']??'0','dia2'=>$r['dia2']??'0','dia3'=>$r['dia3']??'0','dia4'=>$r['dia4']??'0','dia5'=>$r['dia5']??'0','dia6'=>$r['dia6']??'0',
    'dia7'=>$r['dia7']??'0','dia8'=>$r['dia8']??'0','dia9'=>$r['dia9']??'0','dia10'=>$r['dia10']??'0','dia11'=>$r['dia11']??'0','dia12'=>$r['dia12']??'0'
  ];
}
echo json_encode(['ok'=>true,'mode'=>'anio','year'=>$year,'rows'=>$rows]);
