<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
header('Content-Type: application/json; charset=utf-8');
$mode = $_GET['mode'] ?? 'anio';
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$control = isset($_GET['control']) ? intval($_GET['control']) : 0;
$stations = isset($_GET['stations']) && $_GET['stations']!=='' ? explode(',', $_GET['stations']) : [];
$PR_SCHEDULE = [
  ['min'=>5,'max'=>9,'dias'=>5],
  ['min'=>10,'max'=>14,'dias'=>7],
  ['min'=>15,'max'=>19,'dias'=>9],
  ['min'=>20,'max'=>24,'dias'=>11],
  ['min'=>25,'max'=>100,'dias'=>12],
];
$ANTIG_SCHEDULE = [
  ['min'=>10,'max'=>14,'dias'=>2],
  ['min'=>15,'max'=>19,'dias'=>4],
  ['min'=>20,'max'=>24,'dias'=>6],
  ['min'=>25,'max'=>100,'dias'=>8],
];
function antiguedad_en_anio(DateTime $ingreso, int $year): int {
  $cut = new DateTime(sprintf('%d-12-31', $year));
  $diff = $ingreso->diff($cut);
  return max(0, (int)$diff->y);
}
function match_schedule(array $sched, int $ant): int {
  foreach ($sched as $r) {
    if ($ant >= $r['min'] && $ant <= $r['max']) return (int)$r['dias'];
  }
  return 0;
}
function usados_por_tipo(PDO $db, int $control, int $year): array {
  $q = "SELECT tipo, COALESCE(SUM(dias),0) dias FROM vacaciones_mov WHERE control=? AND year=? GROUP BY tipo";
  $st = $db->prepare($q);
  $st->execute([$control, $year]);
  $out = ['VAC'=>0, 'PR'=>0, 'ANT'=>0];
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $t = strtoupper($r['tipo']);
    if (isset($out[$t])) $out[$t] = (int)$r['dias'];
  }
  return $out;
}
try {
  $db = db();
  $rows = [];
  if ($mode === 'persona' && $control > 0) {
    $q = "SELECT e.control, e.estacion, e.espec, e.fingreso AS ingreso, e.nombres, y.year
          FROM empleados e
          JOIN (SELECT DISTINCT year FROM pecos ORDER BY year DESC) y
          WHERE e.control = ?";
    $st = $db->prepare($q);
    $st->execute([$control]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $ing = new DateTime($r['ingreso']);
      $ant = antiguedad_en_anio($ing, (int)$r['year']);
      $vac_asig = 20;
      $pr_asig  = ($r['espec'] === 'CTA') ? match_schedule($PR_SCHEDULE, $ant) : 0;
      $ant_asig = match_schedule($ANTIG_SCHEDULE, $ant);
      $u = usados_por_tipo($db, (int)$r['control'], (int)$r['year']);
      $rows[] = [
        'year'=>(int)$r['year'],'dias_asig'=>$vac_asig,'pr_asig'=>$pr_asig,'ant_asig'=>$ant_asig,
        'dias_usados'=>$u['VAC'],'pr_usados'=>$u['PR'],'ant_usados'=>$u['ANT'],
        'dias_left'=>$vac_asig-$u['VAC'],'pr_left'=>$pr_asig-$u['PR'],'ant_left'=>$ant_asig-$u['ANT'],
      ];
    }
  } else {
    $params = [];
    $inClause = '';
    if (!empty($stations)) {
      $inClause = ' AND e.estacion IN (' . implode(',', array_fill(0, count($stations), '?')) . ')';
      $params = $stations;
    }
    $q = "SELECT e.control, e.estacion, e.espec, e.fingreso AS ingreso, e.nombres
          FROM empleados e
          WHERE e.activo=1".$inClause." ORDER BY e.estacion, e.control";
    $st = $db->prepare($q);
    $st->execute($params);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $ing = new DateTime($r['ingreso']);
      $ant = antiguedad_en_anio($ing, $year);
      $vac_asig = 20;
      $pr_asig  = ($r['espec'] === 'CTA') ? match_schedule($PR_SCHEDULE, $ant) : 0;
      $ant_asig = match_schedule($ANTIG_SCHEDULE, $ant);
      $u = usados_por_tipo($db, (int)$r['control'], $year);
      $rows[] = [
        'oaci'=>$r['oaci'],'control'=>(int)$r['control'],'nombres'=>$r['nombres'],'ant'=>$ant,
        'dias_asig'=>$vac_asig,'pr_asig'=>$pr_asig,'ant_asig'=>$ant_asig,
        'dias_usados'=>$u['VAC'],'pr_usados'=>$u['PR'],'ant_usados'=>$u['ANT'],
        'dias_left'=>$vac_asig-$u['VAC'],'pr_left'=>$pr_asig-$u['PR'],'ant_left'=>$ant_asig-$u['ANT'],
      ];
    }
  }
  echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
