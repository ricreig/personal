<?php
declare(strict_types=1);
/**
 * diagnose.php — Panel de diagnóstico integral (dashboard)
 * Ubicación: /unificado/public/diagnose.php
 *
 * Parámetros:
 *  ?http=1&email=...&pass=...  -> prueba login + endpoints con cookies
 *  ?write_test=1               -> prueba de escritura en docs/
 *  ?json=1                     -> salida JSON (incluye logs)
 *  ?logs=0                     -> desactivar escaneo de logs
 */@ini_set('display_errors','0');
@ini_set('log_errors','1');

$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';

// ---------- utils ----------
function rpath(...$parts){ return preg_replace('#/+#','/', join('/', $parts)); }
function ok($msg,$extra=[]){ return ['ok'=>true,'msg'=>$msg]+$extra; }
function fail($msg,$extra=[]){ return ['ok'=>false,'msg'=>$msg]+$extra; }
function read_head($p,$n=10){ if(!is_file($p))return ''; $h=@fopen($p,'r'); if(!$h)return ''; $o=''; for($i=0;$i<$n && !feof($h);$i++) $o.=fgets($h); fclose($h); return $o; }
function extl($e){ return extension_loaded($e) ? ok("Extensión: $e") : fail("Falta extensión: $e"); }
function writable_dir($d){ if(!is_dir($d)) return fail("No existe dir: $d"); if(!is_writable($d)) return fail("No escribible: $d"); return ok("Escribible: $d"); }
function write_probe($dir){ $f=rtrim($dir,'/').'/.__diag_'.bin2hex(random_bytes(4)).'.tmp'; $w=@file_put_contents($f,'ok'); if($w===false) return fail("No se pudo escribir en $dir"); $ok=is_file($f); @unlink($f); return $ok?ok("Prueba escritura OK en $dir"):fail("Fallo prueba escritura en $dir"); }
function base_url_from_script(): string {
  $script = $_SERVER['SCRIPT_NAME'] ?? '/';
  return rtrim(str_replace('diagnose.php','',$script),'/').'/';
}
// Sustituye la versión anterior
function curl_json(string $url, array $opts = [], ?array &$raw = null): array {
  if (!function_exists('curl_init')) {
    $tmp = ['status'=>0,'headers'=>'','raw'=>''];
    $raw = $tmp;
    return ['ok'=>false, 'msg'=>"cURL no disponible para $url"];
  }

  $ch = curl_init($url);
  $defaults = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT      => 'Diag/1.2'
  ];
  foreach ($opts as $k=>$v) { $defaults[$k] = $v; }
  curl_setopt_array($ch, $defaults);

  $resp = curl_exec($ch);
  if ($resp === false) {
    $err = curl_error($ch);
    curl_close($ch);
    $tmp = ['status'=>0,'headers'=>'','raw'=>''];
    $raw = $tmp;
    return ['ok'=>false, 'msg'=>"cURL: $err"];
  }

  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $hsz    = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $hdr    = substr($resp, 0, $hsz);
  $bod    = substr($resp, $hsz);
  curl_close($ch);

  $tmp = ['status'=>$status, 'headers'=>$hdr, 'raw'=>$bod];
  $raw = $tmp; // ok aunque el caller haya pasado null

  // Acepta 2xx como OK; también 401 lo marcamos como “protegido pero alcanzable”
  $okStatus = ($status >= 200 && $status < 300) || $status === 401;

  $json = json_decode($bod, true);
  if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
    $msg = "HTTP $status (no-JSON)";
    if ($status === 401) $msg = "HTTP 401 (protegido)";
    return ['ok'=>$okStatus, 'msg'=>$msg, 'body'=>$bod, 'status'=>$status];
  }

  $msg = "HTTP $status";
  if ($status === 401) $msg = "HTTP 401 (protegido)";
  return ['ok'=>$okStatus, 'msg'=>$msg, 'json'=>$json, 'status'=>$status];
}

function human_bytes(int $b): string {
  $u=['B','KB','MB','GB']; $i=0; $v=$b*1.0;
  while($v>=1024 && $i<count($u)-1){ $v/=1024; $i++; }
  return number_format($v, ($i?1:0)).' '.$u[$i];
}
function tail_file(string $file, int $maxBytes=200000, int $maxLines=200): string {
  if(!is_file($file)) return '';
  $size = filesize($file);
  $start = ($size>$maxBytes)? ($size-$maxBytes) : 0;
  $fh = fopen($file,'r');
  if($start>0) fseek($fh,$start);
  $buf = stream_get_contents($fh);
  fclose($fh);
  $lines = preg_split("/\r\n|\n|\r/", (string)$buf);
  $lines = array_slice($lines, -$maxLines);
  return implode("\n", $lines);
}
function colorize_log(string $text): string {
  $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $text = preg_replace('/(PHP Fatal error)/', '<span class="log fatal">$1</span>', $text);
  $text = preg_replace('/(Fatal error)/',     '<span class="log fatal">$1</span>', $text);
  $text = preg_replace('/(Warning)/',         '<span class="log warn">$1</span>', $text);
  $text = preg_replace('/(Notice)/',          '<span class="log note">$1</span>', $text);
  $text = preg_replace('/(Deprecated)/',      '<span class="log depr">$1</span>', $text);
  return $text;
}

// ---------- rutas FS ----------
$PUBLIC = __DIR__;
$ROOT   = dirname($PUBLIC,1);

$fs = [
  'root_api'  => $ROOT.'/api',
  'root_docs' => $ROOT.'/docs',
  'pub_api'   => $PUBLIC.'/api',
  'pub_docs'  => $PUBLIC.'/docs',
  'lib'       => $ROOT.'/lib',
  'assets'    => $PUBLIC.'/assets',
  'includes'  => $PUBLIC.'/includes'
];

// ---------- checks básicos ----------
$checks=[];
$checks[] = ok('PHP '.PHP_VERSION);
foreach (['pdo','pdo_mysql','mbstring','fileinfo','json','openssl','curl'] as $e) $checks[] = extl($e);
foreach (['lib','assets','includes'] as $d) {
  $checks[] = is_dir($fs[$d]) ? ok("Existe: ".$fs[$d]) : fail("No existe: ".$fs[$d]);
}

// Detección API/DOCS por FS
$api_location = is_dir($fs['root_api']) ? 'root' : (is_dir($fs['pub_api']) ? 'public' : 'missing');
$docs_location= is_dir($fs['root_docs'])? 'root' : (is_dir($fs['pub_docs'])? 'public' : 'missing');
$checks[] = $api_location!=='missing' ? ok("API en $api_location (".($fs[$api_location==='root'?'root_api':'pub_api']).")") : fail('API no encontrada ni en root/api ni en public/api');
$checks[] = $docs_location!=='missing'? ok("DOCS en $docs_location (".($fs[$docs_location==='root'?'root_docs':'pub_docs']).")") : fail('DOCS no encontrada ni en root/docs ni en public/docs');

// Permisos docs (si existe)
$docs_dir = $docs_location==='root' ? $fs['root_docs'] : ($docs_location==='public' ? $fs['pub_docs'] : null);
if ($docs_dir) {
  $checks[] = writable_dir($docs_dir);
  if (!empty($_GET['write_test'])) $checks[] = write_probe($docs_dir);
}

// HEAD/FOOT includes → assets.php
$head = $fs['includes'].'/HEAD-CSS.php';
$foot = $fs['includes'].'/Foot-js.php';
$needle = "require_once __DIR__ . '/assets.php'";
foreach ([$head,$foot] as $f) {
  if (!is_file($f)) { $checks[] = fail("No existe: $f"); }
  else {
    $txt = file_get_contents($f);
    $checks[] = (strpos($txt,$needle)!==false) ? ok("OK include assets.php en ".basename($f)) : fail("Falta require assets.php en ".basename($f));
  }
}

// DB check
$db_ok = null;
if (is_file($fs['lib'].'/db.php')) {
  require_once $fs['lib'].'/db.php';
  if (function_exists('db')) {
    try { $pdo=db(); $ver=$pdo->query("SELECT VERSION()")->fetchColumn(); $checks[] = ok("DB OK: $ver"); $db_ok=true; }
    catch(Throwable $e){ $checks[] = fail("DB fallo: ".$e->getMessage()); $db_ok=false; }
  } else $checks[] = fail('lib/db.php no define db()');
} else $checks[] = fail('No existe lib/db.php');

// ---------- candidatos HTTP para API ----------
$BASE_URL = base_url_from_script();
$http_candidates = [];
if ($api_location==='public') {
  $http_candidates['public_api'] = $BASE_URL.'api/';
} elseif ($api_location==='root') {
  $http_candidates['root_via_parent'] = rtrim($BASE_URL,'/').'/../api/';
  $http_candidates['root_absolute']   = '/api/';
  $parts = explode('/', trim($BASE_URL,'/'));
  if (count($parts)>=2) {
    array_pop($parts);
    $http_candidates['root_explicit'] = '/'.implode('/',$parts).'/api/';
  }
}

// Probar cada candidato con POST mínimo a trabajadores_list.php
$http_tests = [];
$cj = sys_get_temp_dir().'/__diag_cj_'.bin2hex(random_bytes(3)).'.txt';
foreach ($http_candidates as $label=>$base) {
  $url = $scheme.'://'.$host.rtrim($base,'/').'/trabajadores_list.php';
  $raw=[];
  $resp = curl_json($url, [
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS => http_build_query(['draw'=>1,'start'=>0,'length'=>1,'search[value]'=>'']),
    CURLOPT_COOKIEJAR=>$cj, CURLOPT_COOKIEFILE=>$cj
  ], $raw);
  $http_tests[] = ['label'=>$label,'base'=>$base,'url'=>$url,'result'=>$resp,'detail'=>$raw];
}
@unlink($cj);

// Elegir base API válida (acepta 200/401/403 como "alcanzable")
$API_URL_DETECTED = null;
foreach ($http_tests as $t) {
  $st = (int)($t['detail']['status'] ?? 0);
  if (in_array($st, [200,401,403], true)) {
    $API_URL_DETECTED = rtrim($t['base'],'/').'/';
    break;
  }
}

// ---------- whoami opcional ----------
$whoami = null;
$wraw   = null;
$who_url = $scheme.'://'.$host.$BASE_URL.'/personal/whoami.php';
$whoami = curl_json($who_url, [CURLOPT_HTTPGET=>true], $wraw);

// ---------- HTTP opcional (login + endpoints) ----------
$http_flow=[];
if (!empty($_GET['http'])) {
  $email = $_GET['email'] ?? '';
  $pass  = $_GET['pass']  ?? '';
  $base_full = $scheme.'://'.$host.$BASE_URL;
  $http_flow[] = ok("HTTP BASE: ".$base_full);
  $cj = sys_get_temp_dir().'/__diag_cj_'.bin2hex(random_bytes(4)).'.txt';

  $r=[]; $http_flow[] = curl_json($base_full.'login.php', [
    CURLOPT_HTTPGET=>true, CURLOPT_COOKIEJAR=>$cj, CURLOPT_COOKIEFILE=>$cj
  ], $r) + ['detail'=>$r];

  if ($email && $pass) {
    $r2=[]; $http_flow[] = curl_json($base_full.'login.php', [
      CURLOPT_POST=>true,
      CURLOPT_POSTFIELDS=>http_build_query(['email'=>$email,'pass'=>$pass]),
      CURLOPT_COOKIEJAR=>$cj, CURLOPT_COOKIEFILE=>$cj
    ], $r2) + ['detail'=>$r2];
  } else $http_flow[] = fail('Faltan ?email=&pass=');

  $api_use = $API_URL_DETECTED ?: ($http_candidates['public_api'] ?? $http_candidates['root_via_parent'] ?? $http_candidates['root_explicit'] ?? $http_candidates['root_absolute'] ?? null);
  if ($api_use) {
    $url = $scheme.'://'.$host.rtrim($api_use,'/').'/trabajadores_list.php';
    $r3=[]; $http_flow[] = curl_json($url, [
      CURLOPT_POST=>true,
      CURLOPT_POSTFIELDS=>http_build_query(['draw'=>1,'start'=>0,'length'=>1]),
      CURLOPT_COOKIEJAR=>$cj, CURLOPT_COOKIEFILE=>$cj
    ], $r3) + ['detail'=>$r3];
    $r4=[]; $http_flow[] = curl_json($scheme.'://'.$host.rtrim($api_use,'/').'/doc_exists.php?control=TEST', [
      CURLOPT_HTTPGET=>true, CURLOPT_COOKIEJAR=>$cj, CURLOPT_COOKIEFILE=>$cj
    ], $r4) + ['detail'=>$r4];
  } else {
    $http_flow[] = fail('No se pudo determinar una API_URL válida para probar.');
  }
  @unlink($cj);
}

// ---------- escaneo de logs ----------
$scan_logs = !isset($_GET['logs']) || $_GET['logs']!=='0';
$logs = ['total_files'=>0,'total_size'=>0,'items'=>[]];

if ($scan_logs) {
  $rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );

  $maxShow = 20; $shown = 0;

  foreach ($rii as $f) {
    /** @var SplFileInfo $f */
    if (!$f->isFile()) continue;
    $name = $f->getFilename();
    $isLog = ($name === 'error_log' || $name === 'error_log.txt' || substr($name, -4) === '.log');
    if (!$isLog) continue;

    $path = $f->getPathname();
    $rel  = ltrim(str_replace($ROOT,'',$path),'/');
    $sz   = $f->getSize();
    $mt   = $f->getMTime();

    $logs['total_files']++;
    $logs['total_size'] += $sz;
    if ($shown >= $maxShow) continue;

    $tailRaw = tail_file($path, 200000, 200);
    $lines   = preg_split("/\r\n|\n|\r/", (string)$tailRaw);
    $preview = '';
    for ($i = count($lines)-1; $i >= 0; $i--) {
      $ln = trim((string)$lines[$i]);
      if ($ln !== '') { $preview = $ln; break; }
    }
    $tailDisplay = implode("\n", array_reverse($lines));

    $logs['items'][] = [
      'folder'=> dirname($rel),
      'file'  => basename($rel),
      'rel'   => $rel,
      'size'  => $sz,
      'mtime' => $mt,
      'preview' => $preview,
      'tail'  => $tailDisplay
    ];
    $shown++;
  }
}

// ---------- salida estructurada ----------
$summary = [
  'env'=>[
    'host'=>$host, 'scheme'=>$scheme,
    'DOCUMENT_ROOT'=>($_SERVER['DOCUMENT_ROOT'] ?? ''),
    'SCRIPT_NAME'=>($_SERVER['SCRIPT_NAME'] ?? ''),
    '__DIR__'=>$PUBLIC, 'ROOT'=>$ROOT,
    'BASE_URL'=>$BASE_URL
  ],
  'fs'=>$fs,
  'detected'=>[
    'api_location'=>$api_location,
    'docs_location'=>$docs_location,
    'API_URL_candidates'=>$http_candidates,
    'API_URL_detected'=>$API_URL_DETECTED
  ],
  'checks'=>$checks,
  'http_tests'=>$http_tests,
  'http_flow'=>$http_flow,
  'logs'=>$logs,
  'whoami'=>$whoami
];

if (!empty($_GET['json'])) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($summary, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  exit;
}

// ---------- HTML ----------
function badge($ok){ return $ok?'<span class="b ok">OK</span>':'<span class="b fail">FAIL</span>'; }
$api_hint = $summary['detected']['API_URL_detected'] ?: ($summary['detected']['API_URL_candidates']['public_api'] ?? $summary['detected']['API_URL_candidates']['root_explicit'] ?? $summary['detected']['API_URL_candidates']['root_via_parent'] ?? '/api/');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel de Diagnóstico — Control Regional</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{
  --bg:#0e0f14; --card:#151a23; --fg:#e6eef7; --mut:#9db2ce;
  --ok:#1db954; --fail:#ff4d4f; --hl:#2b7f86; --border:#20283a;
  --warn:#f2c24f; --info:#6aa8ff; --good:#25b06a;
}
*{box-sizing:border-box}
html,body{background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;font-size:15px;line-height:1.4}

/* Topbar fija */
.topbar{
  position:sticky; top:0; z-index:50;
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  background:linear-gradient(90deg,#121728,#0e0f14);
  border-bottom:1px solid var(--border);
  padding:10px 14px;
}
.topbar h1{margin:0; font-size:1.05rem; font-weight:700; letter-spacing:.2px}
.topbar .spacer{flex:1}
.topbar .utc{
  font-variant-numeric:tabular-nums; font-weight:600; opacity:.9;
  padding:.25rem .5rem; border:1px solid var(--border); border-radius:.5rem; background:#0f1420;
}
.actions{display:flex; gap:.5rem; flex-wrap:wrap}
.btn{
  display:inline-block; text-decoration:none; cursor:pointer; user-select:none;
  background:#2b7f86; color:#fff; border:1px solid #256e74;
  padding:.5rem .75rem; border-radius:.55rem; font-weight:600; font-size:.92rem;
}
.btn.alt{ background:transparent; color:#cfe0ff; border-color:#2a3346 }
.btn:hover{ filter:brightness(1.05) }

/* Layout */
.wrap{max-width:1200px; margin:16px auto; padding:0 14px}
.grid{display:grid; gap:12px; grid-template-columns:repeat(12,1fr)}
.card{background:var(--card); border:1px solid var(--border); border-radius:12px; padding:14px; overflow:hidden}
.card h3{margin:0 0 8px 0; font-size:1.02rem}
.col-3{grid-column:span 3}.col-4{grid-column:span 4}.col-6{grid-column:span 6}.col-8{grid-column:span 8}.col-12{grid-column:span 12}

/* Badges */
.badges{display:flex;flex-wrap:wrap;gap:6px}
.b{display:inline-block;padding:.22rem .5rem;border-radius:.5rem;font-size:.8rem;border:1px solid transparent;white-space:nowrap}
.ok{background:rgba(29,185,84,.12);color:var(--ok);border-color:rgba(29,185,84,.35)}
.fail{background:rgba(255,77,79,.12);color:var(--fail);border-color:rgba(255,77,79,.35)}

/* Key/value */
.kv{display:grid; grid-template-columns:180px 1fr; gap:6px}
.small{color:var(--mut); font-size:.9rem}

/* Código y pre */
code{color:#bfefff; word-break:break-all}
pre{white-space:pre-wrap; background:#0b0e14; border:1px solid #22293a; padding:10px; border-radius:8px; max-height:280px; overflow:auto; font-size:.82rem; word-break:break-word}

/* Tablas contenidas */
.table-wrap{width:100%; overflow:auto; -webkit-overflow-scrolling:touch; border:1px solid var(--border); border-radius:10px; background:#0f1420}
.table{width:100%; border-collapse:collapse; table-layout:fixed}
.table th,.table td{border-bottom:1px solid #223045; padding:8px; text-align:left; vertical-align:top}
.table th{color:#cfe3ff; font-weight:600}
.table td code,.cut{word-break:break-word}

/* Stats */
.stat{font-size:1.4rem; font-weight:700}
.stat-label{color:var(--mut); font-size:.82rem}

/* Log highlighting + freshness */
.log.fatal{color:#ff7b7d; font-weight:700}
.log.warn{color:#ffcf66; font-weight:700}
.log.note{color:#7bd4ff; font-weight:700}
.log.depr{color:#cfa9ff; font-weight:700}
.alert{padding:.5rem .7rem; border-radius:.5rem; border:1px solid}
.alert.orange{background:rgba(242,194,79,.12); border-color:rgba(242,194,79,.4); color:#ffdc8b}
.alert.blue{background:rgba(106,168,255,.12); border-color:rgba(106,168,255,.35); color:#d7e6ff}
.alert.green{background:rgba(37,176,106,.12); border-color:rgba(37,176,106,.4); color:#c7f1db}

/* Responsive */
@media (max-width: 980px){
  .col-6,.col-8,.col-4,.col-3{grid-column:span 12}
  .kv{grid-template-columns:1fr}
}
</style>
</head>

<body>
    
    <body>
  <div class="topbar">
    <h1>Panel de Diagnóstico — Control Regional</h1>
    <div class="spacer"></div>
    <div class="utc" id="utcClock">UTC 00:00:00</div>
    <div class="actions">
      <button class="btn alt" id="btnJson">Ver JSON</button>
      <button class="btn alt" id="btnWrite">Probar escritura docs/</button>
      <button class="btn alt" id="btnProbe">Probe control</button>
      <button class="btn" id="btnWhoami">whoami</button>
    </div>
  </div>

<div class="wrap">
  <div class="header">
    <h1 class="h1">Diagnóstico — Control Regional</h1>

  </div>

  <div class="grid">
    <!-- Logs (resumen con último log) -->
    <?php
      $lastItem = null;
      foreach ($logs['items'] as $it) {
        if (!$lastItem || (int)$it['mtime'] > (int)$lastItem['mtime']) $lastItem = $it;
      }
      $lastPreview = '';
      if ($lastItem) {
        $tailLines = preg_split("/\r\n|\n|\r/", (string)$lastItem['tail']);
        $tailLines = array_reverse(array_filter($tailLines, fn($x)=>trim((string)$x) !== ''));
        $last2 = array_slice($tailLines, -2);
        $lastPreview = implode("\n", $last2);
      }
    ?>
    <div class="card col-4">
      <h3>Logs</h3>
      <div class="stat"><?= (int)$logs['total_files'] ?> <span class="stat-label">archivos</span></div>
      <div class="stat"><?= human_bytes((int)$logs['total_size']) ?> <span class="stat-label">acumulado</span></div>
      <?php if ($lastItem): ?>
        <div class="small" style="margin-top:8px">
          <div><strong>Último:</strong> <code><?= htmlspecialchars($lastItem['rel']) ?></code></div>
          <div><strong>Modif.:</strong> <?= date('Y-m-d H:i:s', (int)$lastItem['mtime']) ?> UTC</div>
          <pre class="small"><?= colorize_log($lastPreview) ?></pre>
        </div>
      <?php else: ?>
        <div class="small" style="margin-top:6px">No se encontraron logs.</div>
      <?php endif; ?>
    </div>

        <!-- Logs recientes (por carpeta) con colores por frescura -->
    <div class="card col-12">
      <h3>Logs recientes (por carpeta)</h3>
      <?php if ($logs['items']): ?>
        <?php foreach ($logs['items'] as $item):
          $mins = max(0, floor((time() - (int)$item['mtime'])/60));
          $cls  = ($mins < 2) ? 'orange' : (($mins < 10) ? 'blue' : 'green');
        ?>
          <details style="margin-bottom:10px">
            <summary style="display:flex; gap:8px; align-items:center; flex-wrap:wrap">
              <span class="alert <?= $cls ?>"><?= $mins ?> min</span>
              <strong><?= htmlspecialchars($item['folder'] ?: '.') ?>/<?= htmlspecialchars($item['file']) ?></strong>
              <span class="small">— <?= human_bytes((int)$item['size']) ?> — <?= date('Y-m-d H:i:s', (int)$item['mtime']) ?> UTC</span>
              <?php if (!empty($item['preview'])): ?>
                <span class="small" style="opacity:.9">• Última línea: <code class="cut"><?= htmlspecialchars($item['preview']) ?></code></span>
              <?php endif; ?>
            </summary>
            <pre><?= colorize_log($item['tail']) ?></pre>
          </details>
        <?php endforeach; ?>
        <?php if ($logs['total_files'] > count($logs['items'])): ?>
          <div class="small" style="margin-top:6px">
            Mostrando <?=count($logs['items'])?> de <?= (int)$logs['total_files'] ?> logs encontrados.
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="small">No se encontraron archivos de log. (Desactívalo con <code>?logs=0</code> si es necesario).</div>
      <?php endif; ?>
    </div>

    <!-- Candidatos API (HTTP) -->
    <div class="card col-6">
      <h3>Candidatos API (HTTP)</h3>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th style="width:30%">Base</th><th style="width:18%">Estado</th><th>Detalle</th></tr></thead>
          <tbody>
          <?php foreach ($http_tests as $t): ?>
            <?php $st=(int)($t['detail']['status'] ?? 0); $reach=in_array($st,[200,401,403],true); ?>
            <tr>
              <td><code class="cut"><?=htmlspecialchars($t['base'])?></code><div class="small cut"><?=htmlspecialchars($t['url'])?></div></td>
              <td><?= $reach ? badge(true) : badge(false) ?> <span class="small">HTTP <?= $st ?></span></td>
              <td class="small cut"><?= htmlspecialchars(mb_strimwidth((string)($t['detail']['headers'] ?? ''),0,260,'…','UTF-8')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
        <!-- API -->
    <div class="card col-4">
      <h3>API</h3>
      <div class="kv">
        <div>Ubicación FS</div><div><strong><?=htmlspecialchars($api_location)?></strong></div>
        <div>Detectada HTTP</div>
        <div>
          <?php
            $reachable = (bool)$API_URL_DETECTED;
            echo $reachable ? badge(true).' <code>'.htmlspecialchars($API_URL_DETECTED).'</code>' : badge(false).' No detectada';
          ?>
        </div>
        <div>Sugerencia</div><div><code>&lt;script&gt;window.API_BASE = <?=json_encode($api_hint)?>;&lt;/script&gt;</code></div>
      </div>
    </div>

    <!-- DOCS -->
    <div class="card col-4">
      <h3>DOCS</h3>
      <div class="kv">
        <div>Ubicación FS</div><div><strong><?=htmlspecialchars($docs_location)?></strong></div>
        <?php if ($docs_dir): ?>
        <div>Ruta</div><div><code><?=htmlspecialchars($docs_dir)?></code></div>
        <div>Permisos</div><div><span class="b ok">OK</span> <span class="small">Usa “Probar escritura” para comprobar IO.</span></div>
        <?php endif; ?>
      </div>
    </div>
    <!-- Checks del entorno + whoami (whoami inline) -->
    <div class="card col-6" id="envCard">
      <h3>Checks del entorno</h3>
      <div class="badges" style="margin-bottom:6px">
        <?php foreach ($checks as $c): ?>
          <span class="b <?= $c['ok']?'ok':'fail' ?>"><?= htmlspecialchars($c['msg']) ?></span>
        <?php endforeach; ?>
      </div>
      <div class="kv small" style="margin-top:6px">
        <div>Host</div><div><?=htmlspecialchars($summary['env']['host'])?></div>
        <div>PHP</div><div><?=htmlspecialchars(PHP_VERSION)?></div>
        <div>DOCUMENT_ROOT</div><div><code class="cut"><?=htmlspecialchars($summary['env']['DOCUMENT_ROOT'])?></code></div>
        <div>SCRIPT_NAME</div><div><code class="cut"><?=htmlspecialchars($summary['env']['SCRIPT_NAME'])?></code></div>
        <div>__DIR__ (public)</div><div><code class="cut"><?=htmlspecialchars($PUBLIC)?></code></div>
        <div>ROOT</div><div><code class="cut"><?=htmlspecialchars($ROOT)?></code></div>
        <div>BASE_URL</div><div><code class="cut"><?=htmlspecialchars($summary['env']['BASE_URL'])?></code></div>
        <div>Whoami</div>
        <div>
          <span id="whoamiBadge"><?= in_array((int)($summary['whoami']['status'] ?? 0),[200],true) ? badge(true) : badge(false) ?></span>
          <div id="whoamiBox" class="small" style="margin-top:6px">
            <?php
              $wbody = $summary['whoami']['json'] ?? ($summary['whoami']['body'] ?? null);
              if ($wbody) {
                $out = is_array($wbody) ? json_encode($wbody, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : (string)$wbody;
                echo '<pre id="whoamiPre">'.htmlspecialchars($out).'</pre>';
              } else {
                echo '<pre id="whoamiPre" class="small">Pulsa “whoami” arriba para ejecutar inline…</pre>';
              }
            ?>
          </div>
        </div>
      </div>
    </div>

    <!-- HEAD / FOOT preview -->
    <div class="card col-12">
      <h3>HEAD-CSS.php / Foot-js.php (primeras líneas)</h3>
      <div class="grid">
        <div class="col-6">
          <div class="small">HEAD-CSS.php</div>
          <pre><?=htmlspecialchars(read_head($head, 18))?></pre>
        </div>
        <div class="col-6">
          <div class="small">Foot-js.php</div>
          <pre><?=htmlspecialchars(read_head($foot, 18))?></pre>
        </div>
      </div>
    </div>

    <!-- HTTP flow opcional -->
    <div class="card col-12">
      <h3>Pruebas HTTP con sesión (opcional)</h3>
      <div class="small">Ejecuta: <code>?http=1&amp;email=tu@correo&amp;pass=tu_clave</code></div>
      <?php if ($summary['http_flow']): ?>
        <ol class="small" style="margin-top:8px">
          <?php foreach ($summary['http_flow'] as $r): ?>
            <li><?=badge($r['ok']??false)?> <?=htmlspecialchars($r['msg'] ?? '')?></li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <div class="small">Sin pruebas ejecutadas.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
    <script>
    (function(){
      // Reloj UTC en vivo
      function pad(n){ return String(n).padStart(2,'0'); }
      function tick(){
        const d = new Date();
        const hh = pad(d.getUTCHours()), mm=pad(d.getUTCMinutes()), ss=pad(d.getUTCSeconds());
        document.getElementById('utcClock').textContent = 'UTC ' + hh+':'+mm+':'+ss;
      }
      tick(); setInterval(tick, 1000);
    
      // Botones
      const q = (s)=>document.querySelector(s);
      q('#btnJson') .onclick = ()=>{ const u=new URL(location.href); u.searchParams.set('json','1'); location.href=u.toString(); };
      q('#btnWrite').onclick = ()=>{ const u=new URL(location.href); u.searchParams.set('write_test','1'); history.replaceState(null,'',u.toString()); location.reload(); };
      q('#btnProbe').onclick = ()=>{ window.open('probe_control.php','_blank','noopener'); };
    
      // whoami inline
      q('#btnWhoami').onclick = async ()=>{
        const pre  = document.getElementById('whoamiPre');
        const badge= document.getElementById('whoamiBadge');
        pre.textContent = 'Consultando whoami…';
        try{
          const res = await fetch('whoami.php', {credentials:'same-origin'});
          const txt = await res.text();
          badge.innerHTML = res.ok ? '<span class="b ok">OK</span>' : '<span class="b fail">FAIL</span>';
          // muestra texto tal cual (si es JSON, el usuario lo verá con formato de cadena)
          pre.textContent = txt;
        }catch(e){
          badge.innerHTML = '<span class="b fail">FAIL</span>';
          pre.textContent = 'Error solicitando whoami: ' + e.message;
        }
      };
    })();
    </script>

</body>
</html>