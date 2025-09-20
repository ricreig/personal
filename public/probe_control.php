<?php
declare(strict_types=1);

// Arranque común
require_once dirname(__DIR__,1) . '/lib/bootstrap_public.php';
require_once dirname(__DIR__,1) . '/lib/paths.php';
cr_define_php_paths();

$u = auth_user();
if (!$u) { http_response_code(401); exit('Login requerido'); }

$ctrl_raw = isset($_GET['ctrl']) ? (string)$_GET['ctrl'] : '';
$ctrl_raw = trim($ctrl_raw);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<meta charset="utf-8">
<title>Probe control</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#0e0f14;color:#e6eef7;margin:0;padding:24px}
  .wrap{max-width:980px;margin:auto}
  h1{font-size:1.2rem;margin:0 0 12px}
  .card{background:#151a23;border:1px solid #20283a;border-radius:12px;padding:16px;margin-bottom:12px}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #223045;padding:8px 10px;text-align:left;vertical-align:top;font-size:.95rem}
  code{color:#bfefff}
  .muted{color:#9db2ce}
  input[type=search]{width:260px;border-radius:10px;border:1px solid #223045;background:#0f141d;color:#e6eef7;padding:8px 10px}
  .btn{display:inline-block;background:#2b7f86;color:#fff;padding:.45rem .7rem;border-radius:.6rem;text-decoration:none;margin-left:8px}
  pre{white-space:pre-wrap;background:#0b0e14;border:1px solid #22293a;padding:10px;border-radius:8px;max-height:260px;overflow:auto;font-size:.9rem}
  .ok{color:#1db954;font-weight:600}
  .fail{color:#ff4d4f;font-weight:600}
</style>
<div class="wrap">
  <h1>Probe de búsqueda por <code>control</code></h1>
  <form method="get" class="card">
    <label>Control:&nbsp;</label>
    <input type="search" name="ctrl" value="<?=htmlspecialchars($ctrl_raw)?>" placeholder="p.ej. 2939">
    <button class="btn">Probar</button>
    <span class="muted">Usuario: <?=htmlspecialchars($u['email']??'')?></span>
  </form>

<?php
if ($ctrl_raw === '') {
  echo '<div class="card muted">Ingresa un <code>ctrl</code> y vuelve a probar.</div>';
  exit;
}

$pdo = db();
$rows = [];

// 1) Igualdad exacta
$st = $pdo->prepare("SELECT control, HEX(control) AS hex, LENGTH(control) AS len, nombres, estacion FROM empleados WHERE control = ? LIMIT 5");
$st->execute([$ctrl_raw]); $rows['control = ?'] = $st->fetchAll();

// 2) Igualdad exacta con TRIM
$st = $pdo->prepare("SELECT control, HEX(control) AS hex, LENGTH(control) AS len, nombres, estacion FROM empleados WHERE TRIM(control) = ? LIMIT 5");
$st->execute([$ctrl_raw]); $rows['TRIM(control) = ?'] = $st->fetchAll();

// 3) Casting numérico
$st = $pdo->prepare("SELECT control, HEX(control) AS hex, LENGTH(control) AS len, nombres, estacion FROM empleados WHERE CAST(control AS UNSIGNED) = ? LIMIT 5");
$st->execute([(int)$ctrl_raw]); $rows['CAST(control AS UNSIGNED) = ?'] = $st->fetchAll();

// 4) Quitando espacios internos (por si hay NBSP u otros)
$st = $pdo->prepare("SELECT control, HEX(control) AS hex, LENGTH(control) AS len, nombres, estacion 
                     FROM empleados 
                     WHERE REPLACE(REPLACE(REPLACE(control,' ',''),CHAR(9),''),CHAR(160),'') = REPLACE(REPLACE(REPLACE(?,' ',''),CHAR(9),''),CHAR(160),'')
                     LIMIT 5");
$st->execute([$ctrl_raw]); $rows['REPLACE espacios = ?'] = $st->fetchAll();

// 5) LIKE prefijo
$st = $pdo->prepare("SELECT control, HEX(control) AS hex, LENGTH(control) AS len, nombres, estacion FROM empleados WHERE control LIKE ? LIMIT 5");
$st->execute([$ctrl_raw.'%']); $rows['control LIKE ?'] = $st->fetchAll();

// Render
foreach ($rows as $label=>$data) {
  $ok = count($data) ? 'ok' : 'fail';
  echo '<div class="card"><div><strong>'.$label.'</strong> → <span class="'.$ok.'">'.($ok==='ok'?'MATCH':'NO MATCH').'</span></div>';
  if ($data) {
    echo '<table><thead><tr><th>control</th><th>HEX</th><th>LENGTH</th><th>nombres</th><th>estacion</th></tr></thead><tbody>';
    foreach ($data as $r) {
      echo '<tr><td>'.htmlspecialchars($r['control']).'</td><td><code>'.$r['hex'].'</code></td><td>'.$r['len'].'</td><td>'.htmlspecialchars($r['nombres']).'</td><td>'.htmlspecialchars((string)$r['estacion']).'</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</div>';
}
?>
  <div class="card">
    <div class="muted">Sugerencia: si solo hay MATCH en casting o en “REPLACE espacios”, el valor en BD trae caracteres invisibles. Conviene normalizarlo con un <code>UPDATE</code> que haga <code>TRIM</code> y elimine NBSP.</div>
  </div>
</div>