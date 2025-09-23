<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_auth_api();

/**
 * DataTables (server-side) — Empleados
 * - Vistas: view=lic | view=datos
 * - La BD guarda estación como NÚMERO; la UI muestra OACI (4 letras)
 * - Permisos por estación en SQL; orden por fechas en PHP cuando aplique
 */

@ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php';
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

try {
  session_boot();
  $pdo = db();
  $u   = auth_user();

  // ---------------- DataTables params
  $draw   = isset($_POST['draw'])   ? (int)$_POST['draw']   : (int)($_GET['draw'] ?? 1);
  $start  = isset($_POST['start'])  ? (int)$_POST['start']  : (int)($_GET['start'] ?? 0);
  $length = isset($_POST['length']) ? (int)$_POST['length'] : (int)($_GET['length'] ?? 25);
  if ($length < 1 || $length > 500) $length = 25;

  $searchVal = '';
  if (isset($_POST['search']['value'])) $searchVal = trim((string)$_POST['search']['value']);
  elseif (isset($_GET['search']))       $searchVal = trim((string)$_GET['search']);

  $view = $_GET['view'] ?? $_POST['view'] ?? 'lic'; // lic | datos

  $orderCol = 0; $orderDir = 'asc';
  if (isset($_POST['order'][0]['column'])) $orderCol = (int)$_POST['order'][0]['column'];
  if (isset($_POST['order'][0]['dir']))    $orderDir = (strtolower($_POST['order'][0]['dir'] ?? '')==='desc') ? 'desc' : 'asc';

  if (!$u) {
    http_response_code(401);
    echo json_encode(['error'=>'no-auth','draw'=>$draw,'data'=>[],'recordsTotal'=>0,'recordsFiltered'=>0]);
    exit;
  }

  // ---------------- Helpers
  function oaci_from_num(?int $n): ?string {
    return match((int)$n) { 1=>'MMSD',2=>'MMLP',3=>'MMSL',4=>'MMLT',5=>'MMTJ',6=>'MMML',7=>'MMPE',8=>'MMHO',9=>'MMGM', default=>null };
  }
  function oaci_to_num(string $oaci): ?int {
    return match(strtoupper(trim($oaci))) { 'MMSD'=>1,'MMLP'=>2,'MMSL'=>3,'MMLT'=>4,'MMTJ'=>5,'MMML'=>6,'MMPE'=>7,'MMHO'=>8,'MMGM'=>9, default=>null };
  }
  function ymd_for_order(?string $s): string {
    $s = trim((string)$s);
    if ($s === '') return '';
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~',$s,$m)) return $m[1].$m[2].$m[3];
    if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~',$s,$m)) return $m[3].$m[2].$m[1];
    return '';
  }
  function days_to(?string $s): ?int {
    $y = ymd_for_order($s);
    if ($y==='') return null;
    $d = DateTime::createFromFormat('Ymd',$y); if(!$d) return null;
    $d->setTime(0,0,0);
    $today = new DateTime('today');
    return (int)floor(($d->getTimestamp()-$today->getTimestamp())/86400);
  }
  function permisos_oaci_del_usuario(PDO $pdo, array $user): array {
    $role = $user['rol'] ?? $user['role'] ?? '';
    if ($role === 'admin') return ['*'];
    if (function_exists('user_station_matrix')) {
      $m = user_station_matrix($pdo, (int)($user['id'] ?? 0)); // ['MMTJ'=>true,...]
      return array_keys(array_filter((array)$m));
    }
    return []; // sin matriz → nada
  }
  if (!function_exists('user_can_row')) {
    function user_can_row(array $u, array $row): bool { return true; }
  }

  // ---------------- Ordenamiento robusto (usa columns[i][data])
  // Campos que podemos ordenar directamente en SQL
  $sortableSql = [
    'control'=>'control','nombres'=>'nombres','rfc'=>'rfc','curp'=>'curp','puesto'=>'puesto',
    'plaza'=>'plaza','nivel'=>'nivel','espec'=>'espec','estacion'=>'estacion','email'=>'email'
  ];
  // Campos que viajan como objetos {display,order} y conviene ordenar en PHP
  $sortInPhp = ['fecha_nacimiento','ant','vigencia1','vigencia2','examen_vig1','examen_vig2','rtari_vig'];

  // Estación por OACI visible
  function sql_order_expr_estacion(): string {
    return "(CASE estacion
              WHEN 1 THEN 'MMSD'
              WHEN 2 THEN 'MMLP'
              WHEN 3 THEN 'MMSL'
              WHEN 4 THEN 'MMLT'
              WHEN 5 THEN 'MMTJ'
              WHEN 6 THEN 'MMML'
              WHEN 7 THEN 'MMPE'
              WHEN 8 THEN 'MMHO'
              WHEN 9 THEN 'MMGM'
            END)";
  }

  $clientColKey = $_POST['columns'][$orderCol]['data'] ?? null;
  $orderSql   = "";
  $orderField = null;

  if ($clientColKey && in_array($clientColKey, $sortInPhp, true)) {
    $orderField = null; // se ordena en PHP más abajo
  } elseif ($clientColKey && isset($sortableSql[$clientColKey])) {
    if ($clientColKey === 'estacion') {
      $orderSql   = "ORDER BY " . sql_order_expr_estacion() . " $orderDir";
      $orderField = 'estacion';
    } else {
      $orderSql   = "ORDER BY " . $sortableSql[$clientColKey] . " $orderDir";
      $orderField = $sortableSql[$clientColKey];
    }
  } else {
    // Por defecto
    $orderSql   = "ORDER BY nombres $orderDir";
    $orderField = 'nombres';
  }

  // ---------------- Filtros (permisos + UI)
  $filters = $_POST['filters'] ?? [];
  if (!is_array($filters)) $filters = [];

  $onlyWithLic = !empty($_POST['onlyWithLic']) || !empty($_POST['onlyLicensed']);

  $permitidosOACI = permisos_oaci_del_usuario($pdo, $u);   // ['*'] o ['MMTJ',...]
  $isAdmin        = ($permitidosOACI === ['*']);

  $estSelOACI = [];
  if (!empty($filters['estacion']) && is_array($filters['estacion'])) {
    $estSelOACI = array_values(array_unique(array_map('strtoupper', array_map('trim', $filters['estacion']))));
  }

  $efectivosOACI = $isAdmin ? $estSelOACI
                            : ( $estSelOACI ? array_values(array_intersect($permitidosOACI, $estSelOACI))
                                            : $permitidosOACI );

  $efectivosNUM = array_values(array_filter(array_map('oaci_to_num', $efectivosOACI), fn($n)=>$n!==null));

  if (!$isAdmin && !count($efectivosNUM)) {
    echo json_encode(['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $where = [];
  $args  = [];

  if (count($efectivosNUM)) {
    $place = implode(',', array_fill(0, count($efectivosNUM), '?'));
    $where[] = "estacion IN ($place)";
    $args = array_merge($args, $efectivosNUM);
  }

  if (!empty($filters['espec']) && is_array($filters['espec'])) {
    $esp = array_values(array_filter(array_map('trim', $filters['espec'])));
    if ($esp) {
      $place = implode(',', array_fill(0, count($esp), '?'));
      $where[] = "espec IN ($place)";
      $args = array_merge($args, $esp);
    }
  }

  $tipoNom = $filters['tipoNom'] ?? 'all';
  if ($tipoNom === 'confianza')      $where[] = "UPPER(plaza) LIKE 'C%'";
  elseif ($tipoNom === 'base')       $where[] = "UPPER(plaza) NOT LIKE 'C%'";

  if ($onlyWithLic && $view === 'lic') {
    $where[] = "((licencia1 IS NOT NULL AND licencia1 <> '') OR (licencia2 IS NOT NULL AND licencia2 <> ''))";
  }

  // --- Filtro “solo vencidos” (Licencia / Médico / RTARI)
  $onlyExpired = (int)($filters['onlyExpiredLic'] ?? 0) === 1;
  if ($onlyExpired) {
    $where[] = "(
      (vigencia1 IS NOT NULL AND vigencia1 <> '' AND COALESCE(STR_TO_DATE(vigencia1, '%Y-%m-%d'), STR_TO_DATE(vigencia1, '%d/%m/%Y')) < CURDATE())
      OR (vigencia2 IS NOT NULL AND vigencia2 <> '' AND COALESCE(STR_TO_DATE(vigencia2, '%Y-%m-%d'), STR_TO_DATE(vigencia2, '%d/%m/%Y')) < CURDATE())
      OR (examen_vig1 IS NOT NULL AND examen_vig1 <> '' AND COALESCE(STR_TO_DATE(examen_vig1, '%Y-%m-%d'), STR_TO_DATE(examen_vig1, '%d/%m/%Y')) < CURDATE())
      OR (examen_vig2 IS NOT NULL AND examen_vig2 <> '' AND COALESCE(STR_TO_DATE(examen_vig2, '%Y-%m-%d'), STR_TO_DATE(examen_vig2, '%d/%m/%Y')) < CURDATE())
      OR (rtari_vig IS NOT NULL AND rtari_vig <> '' AND COALESCE(STR_TO_DATE(rtari_vig, '%Y-%m-%d'), STR_TO_DATE(rtari_vig, '%d/%m/%Y')) < CURDATE())
    )";
  }

  if ($searchVal !== '') {
    $where[] = "(nombres LIKE ? OR control LIKE ? OR email LIKE ? OR rfc LIKE ? OR curp LIKE ? OR puesto LIKE ? OR plaza LIKE ?)";
    $like = "%{$searchVal}%";
    array_push($args, $like,$like,$like,$like,$like,$like,$like);
  }

  $whereSql = $where ? "WHERE ".implode(" AND ", $where) : "";

  // ---------------- SQL base
  $baseFields = "control,nombres,email,rfc,ant,direccion,plaza,espec,estacion,siglas,fecha_nacimiento,curp,nivel,nss,puesto,tipo1,licencia1,vigencia1,tipo2,licencia2,vigencia2,examen1,examen_vig1,examen2,examen_vig2,rtari,rtari_vig,exp_med";
  $sqlFrom    = "FROM empleados";

  // Totales
  $total = (int)$pdo->query("SELECT COUNT(*) $sqlFrom")->fetchColumn();

  $stCount = $pdo->prepare("SELECT COUNT(*) $sqlFrom $whereSql");
  $stCount->execute($args);
  $filteredCount = (int)$stCount->fetchColumn();

  // -------- SELECT principal (solo posicionales: evita mezcla con :lim/:off)
  $sql = "SELECT $baseFields $sqlFrom $whereSql "
       . ($orderSql ?: "ORDER BY nombres $orderDir")
       . " LIMIT ? OFFSET ?";
  $st  = $pdo->prepare($sql);

  // mismos args del WHERE + paginación
  $params   = $args;
  $params[] = (int)$length;
  $params[] = (int)$start;

  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Si orden es por fecha (no SQL), ordena en PHP
  if (!$orderField) {
    // DataTables ya pidió una columna concreta (clientColKey)
    $key = $clientColKey ?: 'nombres';
    usort($rows, function($a,$b) use($key,$orderDir){
      $ka = ymd_for_order($a[$key] ?? '');
      $kb = ymd_for_order($b[$key] ?? '');
      if ($ka === $kb) return 0;
      return ($orderDir==='desc') ? strcmp($kb,$ka) : strcmp($ka,$kb);
    });
  }

  // Salida por vista
  $out = [];
  foreach ($rows as $i => $r) {
    $oaci = oaci_from_num((int)$r['estacion']) ?: (string)$r['estacion'];

    if ($view === 'datos') {
      $out[] = [
        'rownum'   => $start + $i + 1,
        'control'  => (string)$r['control'],
        'nombres'  => $r['nombres'],
        'rfc'      => $r['rfc'],
        'curp'     => $r['curp'],
        'puesto'   => $r['puesto'],
        'plaza'    => $r['plaza'],
        'nivel'    => $r['nivel'],
        'espec'    => $r['espec'],
        'estacion' => $oaci,
        'ant'      => [ 'display'=> ($r['ant'] ? str_replace('-', '/', $r['ant']) : ''), 'order'=> ymd_for_order($r['ant']) ],
        'fecha_nacimiento' => [ 'display'=> ($r['fecha_nacimiento'] ? str_replace('-', '/', $r['fecha_nacimiento']) : ''), 'order'=> ymd_for_order($r['fecha_nacimiento']) ],
        'email'    => $r['email'],
        'acciones' => ''
      ];
    } else { // lic
      $out[] = [
        'rownum'   => $start + $i + 1,
        'control'  => (string)$r['control'],
        'nombres'  => $r['nombres'],
        'fecha_nacimiento' => [ 'display'=> ($r['fecha_nacimiento'] ? str_replace('-', '/', $r['fecha_nacimiento']) : ''), 'order'=> ymd_for_order($r['fecha_nacimiento']) ],
        'ant'      => [ 'display'=> ($r['ant'] ? str_replace('-', '/', $r['ant']) : ''), 'order'=> ymd_for_order($r['ant']) ],
        'espec'    => $r['espec'],
        'estacion' => $oaci,
        'plaza'    => $r['plaza'],
        'puesto'   => $r['puesto'],
        'nivel'    => $r['nivel'],
        'rfc'      => $r['rfc'],
        'curp'     => $r['curp'],

        'tipo1'      => $r['tipo1'],
        'licencia1'  => $r['licencia1'],
        'vigencia1'  => [ 'display'=>$r['vigencia1'], 'order'=> ymd_for_order($r['vigencia1']), 'days'=> days_to($r['vigencia1']) ],

        'tipo2'      => $r['tipo2'],
        'licencia2'  => $r['licencia2'],
        'vigencia2'  => [ 'display'=>$r['vigencia2'], 'order'=> ymd_for_order($r['vigencia2']), 'days'=> days_to($r['vigencia2']) ],

        // Importante: etiquetas en el cliente (Psicofísico / Anexo)
        'examen1'     => $r['examen1'],
        'examen_vig1' => [ 'display'=>$r['examen_vig1'], 'order'=> ymd_for_order($r['examen_vig1']), 'days'=> days_to($r['examen_vig1']) ],
        'examen2'     => $r['examen2'],
        'examen_vig2' => [ 'display'=>$r['examen_vig2'], 'order'=> ymd_for_order($r['examen_vig2']), 'days'=> days_to($r['examen_vig2']) ],

        'rtari'       => $r['rtari'],
        'rtari_vig'   => [ 'display'=>$r['rtari_vig'], 'order'=> ymd_for_order($r['rtari_vig']), 'days'=> days_to($r['rtari_vig']) ],

        'email'    => $r['email'],
        'acciones' => ''
      ];
    }
  }

  echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $total,
    'recordsFiltered' => $filteredCount,
    'data'            => $out
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'error'   => 'server',
    'message' => $e->getMessage(),
    'line'    => $e->getLine(),
    'file'    => basename($e->getFile())
  ], JSON_UNESCAPED_UNICODE);
}