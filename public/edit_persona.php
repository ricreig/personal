<?php
declare(strict_types=1);
require_once dirname(__DIR__,1) . '/lib/bootstrap_public.php';
require_once dirname(__DIR__,1) . '/lib/paths.php';
require_once dirname(__DIR__,1) . '/lib/perm.php';   // <-- NUEVO
require_once dirname(__DIR__,1) . '/lib/db.php';     // por si bootstrap_public no lo trae
cr_define_php_paths();

$u   = auth_user();
$pdo = db();

$ctrl = $_GET['ctrl'] ?? $_POST['ctrl'] ?? '';
if (!$u || !$ctrl || !cr_can_manage_control($pdo, $u, $ctrl)) {
  http_response_code(403);
  echo "<h1>Acceso no autorizado</h1>";
  exit;
}


// --- helpers locales ---
function norm_ctrl(?string $v): string {
  $v = (string)$v;
  // quita espacios y NBSP
  $v = preg_replace('/^[\h\x{00A0}]+|[\h\x{00A0}]+$/u', '', $v);
  return $v;
}

$pdo = db();

// --- recuperar control desde ?ctrl= ---
$ctrl = trim((string)($_GET['ctrl'] ?? ''));
if ($ctrl === '') {
  http_response_code(400);
  exit('Falta parámetro ?ctrl=');
}

// Cargar empleado
$st = $pdo->prepare("SELECT * FROM empleados WHERE control = ? LIMIT 1");
$st->execute([$ctrl]);
$emp = $st->fetch();

if (!$emp) {
  http_response_code(404);
  exit("No existe el control {$ctrl}");
}

// Map numérico -> OACI
function oaci_from_estacion(?int $n): string {
  return match($n) {
    1=>'MMSD',2=>'MMLP',3=>'MMSL',4=>'MMLT',5=>'MMTJ',6=>'MMML',7=>'MMPE',8=>'MMHO',9=>'MMGM', default=>''
  };
}

// $emp ya cargado con SELECT * FROM empleados WHERE control=?
if (!$emp) { http_response_code(404); exit("No existe el control {$ctrl}"); }

// --- Seguridad por estación ---
if (!is_admin()) {
  $oaci = oaci_from_estacion((int)($emp['estacion'] ?? 0));
  if ($oaci === '') { http_response_code(403); exit('Empleado sin estación asignada'); }

  // Matriz de estaciones permitidas para este usuario (ej: ['MMTJ'=>true, 'MMHO'=>true])
  $matrix = user_station_matrix(db(), (int)$u['id']);
  if (empty($matrix[$oaci])) {
    http_response_code(403);
    exit('Sin permiso para editar personal de ' . $oaci);
  }
}
// (si es admin, pasa siempre)
// Para selects
$ESPECS = ['MANDOS'=>'MANDOS','ATCO'=>'ATCO','OSIV'=>'OSIV','ADMIN'=>'APOYO ADMON','IDS'=>'IDS'];
$ESTACIONES = [
1=>'MMSD', 2=>'MMLP', 3=>'MMSL', 4=>'MMLT', 5=>'MMTJ', 6=>'MMML', 7=>'MMPE', 8=>'MMHO', 9=>'MMGM'
];
$LIC_OPC = ['CTA III'=>'CTA III','OOA'=>'OOA','MET I'=>'MET I','TEC MTTO'=>'TEC MTTO','CAM'=>'Conducción GAP (CAM)'];
$LCAR_OPC = ['A'=>'Tipo A','B'=>'Tipo B','C'=>'Tipo C','DL'=>'DL'];
$CLASE_OPC = ['GPO-3'=>'GPO-3','GPO-4'=>'GPO-4','CLASE-3'=>'CLASE-3'];

// Guardar
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitizar/recoger (todas son varchar en tu schema)
  $data = [
    'control'          => trim((string)($_POST['control'] ?? $emp['control'])),
    'siglas'           => strtoupper(trim((string)($_POST['siglas'] ?? $emp['siglas']))),
    'nombres'          => ucwords(strtolower(trim((string)($_POST['nombres'] ?? $emp['nombres'])))),
    'email'            => trim((string)($_POST['email'] ?? $emp['email'])),
    'rfc'              => strtoupper(trim((string)($_POST['rfc'] ?? $emp['rfc']))),
    'curp'             => strtoupper(trim((string)($_POST['curp'] ?? $emp['curp']))),
    'fecha_nacimiento' => trim((string)($_POST['fecha_nacimiento'] ?? $emp['fecha_nacimiento'])),
    'ant'              => trim((string)($_POST['ant'] ?? $emp['ant'])),
    'direccion'        => strtoupper(trim((string)($_POST['direccion'] ?? $emp['direccion']))),
    'plaza'            => strtoupper(trim((string)($_POST['plaza'] ?? $emp['plaza']))),
    'espec'            => trim((string)($_POST['espec'] ?? $emp['espec'])),
    'estacion'         => trim((string)($_POST['estacion'] ?? (string)$emp['estacion'])),
    'nivel'            => trim((string)($_POST['nivel'] ?? $emp['nivel'])),
    'nss'              => trim((string)($_POST['nss'] ?? $emp['nss'])),
    'puesto'           => strtoupper(trim((string)($_POST['puesto'] ?? $emp['puesto']))),
    'tipo1'            => trim((string)($_POST['tipo1'] ?? $emp['tipo1'])),
    'licencia1'        => trim((string)($_POST['licencia1'] ?? $emp['licencia1'])),
    'vigencia1'        => trim((string)($_POST['vigencia1'] ?? $emp['vigencia1'])),
    'tipo2'            => trim((string)($_POST['tipo2'] ?? $emp['tipo2'])),
    'licencia2'        => trim((string)($_POST['licencia2'] ?? $emp['licencia2'])),
    'vigencia2'        => trim((string)($_POST['vigencia2'] ?? $emp['vigencia2'])),
    'examen1'          => trim((string)($_POST['examen1'] ?? $emp['examen1'])),
    'examen_vig1'      => trim((string)($_POST['examen_vig1'] ?? $emp['examen_vig1'])),
    'examen2'          => trim((string)($_POST['examen2'] ?? $emp['examen2'])),
    'examen_vig2'      => trim((string)($_POST['examen_vig2'] ?? $emp['examen_vig2'])),
    'rtari'            => trim((string)($_POST['rtari'] ?? $emp['rtari'])),
    'rtari_vig'        => trim((string)($_POST['rtari_vig'] ?? $emp['rtari_vig'])),
    'exp_med'          => trim((string)($_POST['exp_med'] ?? $emp['exp_med'])),
  ];

  if ($data['control'] === '') {
    $err = 'El No. de control es obligatorio.';
  }

  if (!$err) {
    // Nota: “control” es PK en empleados. Si lo cambias, actualizamos por el ORIGINAL ($ctrl).
    $sql = "UPDATE empleados SET 
              control=?, siglas=?, nombres=?, email=?, rfc=?, curp=?, fecha_nacimiento=?, ant=?, direccion=?, 
              plaza=?, espec=?, estacion=?, nivel=?, nss=?, puesto=?, 
              tipo1=?, licencia1=?, vigencia1=?, 
              tipo2=?, licencia2=?, vigencia2=?,
              examen1=?, examen_vig1=?, examen2=?, examen_vig2=?, 
              rtari=?, rtari_vig=?, exp_med=?
            WHERE control=?";
    $ok = $pdo->prepare($sql)->execute([
      $data['control'], $data['siglas'], $data['nombres'], $data['email'], $data['rfc'], $data['curp'], $data['fecha_nacimiento'],
      $data['ant'], $data['direccion'], $data['plaza'], $data['espec'], $data['estacion'], $data['nivel'], $data['nss'],
      $data['puesto'], $data['tipo1'], $data['licencia1'], $data['vigencia1'], $data['tipo2'], $data['licencia2'],
      $data['vigencia2'], $data['examen1'], $data['examen_vig1'], $data['examen2'], $data['examen_vig2'],
      $data['rtari'], $data['rtari_vig'], $data['exp_med'],
      $ctrl
    ]);
    if ($ok) {
      $msg = 'Datos guardados';
      // si cambió control, recarga con el nuevo
      if ($data['control'] !== $ctrl) {
        header('Location: '.BASE_URL.'edit_persona.php?ctrl='.rawurlencode($data['control']).'&msg='.rawurlencode($msg));
        exit;
      }
      // refrescar $emp
      $emp = $data;
    } else {
      $err = 'No se pudo guardar.';
    }
  }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d1117" />
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Control Regional de Personal">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Editar — <?=h($emp['nombres'] ?: $emp['control'])?></title>
  <?php require __DIR__ . '/includes/HEAD-CSS.php'; ?>
  <style>
    .wrap{max-width:1100px;margin:0 auto}
    .card-hero{background: radial-gradient(1200px 400px at 0% 0%, #1c2133 0%, #0e0f14 60%);
               border:1px solid rgba(255,255,255,.06);border-radius:1rem}
    .form-control, .form-select{border-radius:.7rem}
    .grid{display:grid;gap:12px}
    @media(min-width:768px){
      .grid-2{grid-template-columns:1fr 1fr}
      .grid-3{grid-template-columns:repeat(3,1fr)}
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="index.php">Control Regional</a>
    <div class="ms-auto">
      <a class="btn btn-outline-secondary btn-sm" href="index.php">Volver</a>
      <a class="btn btn-outline-info btn-sm" href="<?=h(BASE_URL)?>?q=<?=urlencode($emp['control'])?>">Ver en tabla</a>
    </div>
  </div>
</nav>

<div class="container-fluid py-4 wrap">
  <div class="card card-hero mb-3">
    <div class="card-body d-flex align-items-center justify-content-between">
      <h1 class="h5 m-0">Editar — <?=h($emp['nombres'] ?: 'Sin nombre')?> · <span class="text-muted">#<?=h($emp['control'])?></span></h1>
      <?php if ($msg): ?><span class="badge text-bg-success"><?=h($msg)?></span><?php endif; ?>
      <?php if ($err): ?><span class="badge text-bg-danger"><?=h($err)?></span><?php endif; ?>
    </div>
  </div>

  <form method="post" class="card">
    <div class="card-body grid grid-2">
      <div>
        <label class="form-label">No. Control</label>
        <input name="control" class="form-control" value="<?=h($emp['control'])?>">
      </div>
      <div>
        <label class="form-label">Siglas</label>
        <input name="siglas" class="form-control" value="<?=h($emp['siglas'])?>" maxlength="3">
      </div>
      <div class="grid-2" style="grid-column:1/-1">
        <div>
          <label class="form-label">Nombre</label>
          <input name="nombres" class="form-control" value="<?=h($emp['nombres'])?>">
        </div>
        <div>
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?=h($emp['email'])?>">
        </div>
      </div>
      <div>
        <label class="form-label">RFC</label>
        <input name="rfc" class="form-control" value="<?=h($emp['rfc'])?>">
      </div>
      <div>
        <label class="form-label">CURP</label>
        <input name="curp" class="form-control" value="<?=h($emp['curp'])?>">
      </div>
      <div>
        <label class="form-label">Nacimiento (dd/mm/aaaa)</label>
        <input name="fecha_nacimiento" class="form-control" placeholder="dd/mm/aaaa" value="<?=h($emp['fecha_nacimiento'])?>" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
      </div>
      <div>
        <label class="form-label">Ingreso/Antigüedad</label>
        <input name="ant" class="form-control" placeholder="dd/mm/aaaa" value="<?=h($emp['ant'])?>" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
      </div>
      <div style="grid-column:1/-1">
        <label class="form-label">Dirección</label>
        <textarea name="direccion" class="form-control" rows="2"><?=h($emp['direccion'])?></textarea>
      </div>
      <div>
        <label class="form-label">Código Plaza</label>
        <input name="plaza" class="form-control" value="<?=h($emp['plaza'])?>">
      </div>
      <div>
        <label class="form-label">Especialidad</label>
        <select name="espec" class="form-select">
          <option value="">—</option>
          <?php foreach($ESPECS as $k=>$label): ?>
            <option value="<?=h($k)?>" <?=$emp['espec']===$k?'selected':''?>><?=h($label)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Estación</label>
        <select name="estacion" class="form-select">
          <option value="">—</option>
          <?php foreach($ESTACIONES as $id=>$oaci): ?>
            <option value="<?=h((string)$id)?>" <?=((string)$emp['estacion']===(string)$id)?'selected':''?>><?=h($oaci)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Nivel</label>
        <input name="nivel" class="form-control" value="<?=h($emp['nivel'])?>">
      </div>
      <div>
        <label class="form-label">NSS</label>
        <input name="nss" class="form-control" value="<?=h($emp['nss'])?>">
      </div>
      <div style="grid-column:1/-1">
        <label class="form-label">Nombramiento / Puesto</label>
        <input name="puesto" class="form-control" value="<?=h($emp['puesto'])?>">
      </div>

      <!-- Licencias / exámenes -->
      <div class="grid grid-3" style="grid-column:1/-1">
        <div>
          <label class="form-label">Tipo Licencia 1</label>
          <select name="tipo1" class="form-select">
            <option value="">—</option>
            <?php foreach($LIC_OPC as $k=>$label): ?>
              <option value="<?=h($k)?>" <?=$emp['tipo1']===$k?'selected':''?>><?=h($label)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">No. Licencia 1</label>
          <input name="licencia1" class="form-control" value="<?=h($emp['licencia1'])?>">
        </div>
        <div>
          <label class="form-label">Vigencia 1</label>
          <input name="vigencia1" class="form-control" value="<?=h($emp['vigencia1'])?>" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
        </div>
      </div>

      <div class="grid grid-3" style="grid-column:1/-1">
        <div>
          <label class="form-label">Tipo Licencia 2</label>
          <select name="tipo2" class="form-select">
            <option value="">—</option>
            <?php foreach($LIC_OPC as $k=>$label): ?>
              <option value="<?=h($k)?>" <?=$emp['tipo2']===$k?'selected':''?>><?=h($label)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">No. Licencia 2</label>
          <input name="licencia2" class="form-control" value="<?=h($emp['licencia2'])?>">
        </div>
        <div>
          <label class="form-label">Vigencia 2</label>
          <input name="vigencia2" class="form-control" value="<?=h($emp['vigencia2'])?>" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
        </div>
      </div>

      <div class="grid grid-3" style="grid-column:1/-1">
        <div>
          <label class="form-label">LCAR / DL</label>
          <select name="examen1" class="form-select">
            <option value="">—</option>
            <?php foreach($LCAR_OPC as $k=>$label): ?>
              <option value="<?=h($k)?>" <?=$emp['examen1']===$k?'selected':''?>><?=h($label)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Vigencia LCAR/DL</label>
          <input name="examen_vig1" class="form-control" value="<?=h($emp['examen_vig1'])?>" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
        </div>
        <div>
          <label class="form-label">RTARI</label>
          <input name="rtari" class="form-control" value="<?=h($emp['rtari'])?>">
        </div>
      </div>

      <div class="grid grid-2" style="grid-column:1/-1">
        <div>
          <label class="form-label">Vigencia RTARI</label>
          <input name="rtari_vig" class="form-control" value="<?=h($emp['rtari_vig'])?>" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
        </div>
      </div>

      <div class="grid grid-3" style="grid-column:1/-1">
        <div>
          <label class="form-label">Clase Examen Médico</label>
          <select name="examen2" class="form-select">
            <option value="">—</option>
            <?php foreach($CLASE_OPC as $k=>$label): ?>
              <option value="<?=h($k)?>" <?=$emp['examen2']===$k?'selected':''?>><?=h($label)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Expediente Médico</label>
          <input name="exp_med" class="form-control" value="<?=h($emp['exp_med'])?>">
        </div>
        <div>
          <label class="form-label">Vigencia Examen Médico</label>
          <input name="examen_vig2" class="form-control" value="<?=h($emp['examen_vig2'])?>" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
        </div>
      </div>
    </div>

    <div class="card-footer d-flex justify-content-between">
      <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
      <button class="btn btn-primary">Guardar cambios</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/Foot-js.php'; ?>
</body>
</html>