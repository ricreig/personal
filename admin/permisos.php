<?php
declare(strict_types=1);
// Arranque común (sesión, DB, helpers, rutas y assets)
require_once dirname(__DIR__) . '/lib/bootstrap_public.php'; // session_boot(), db(), auth, paths

// Seguridad: solo admin
if (!is_admin()) { http_response_code(403); exit('Solo admin'); }

$pdo = db();
$msg = $_GET['msg'] ?? null;

// -------- helpers --------
function fetch_users(PDO $pdo): array {
  return $pdo->query("SELECT id, email, nombre, role, is_active FROM app_users ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
}
function fetch_stations(PDO $pdo): array {
  // estaciones(oaci, nombre, region) según tu schema
  $tbl = $pdo->query("SHOW TABLES LIKE 'estaciones'")->fetchColumn();
  if (!$tbl) return [];
  $sql = "SELECT COALESCE(oaci,'') AS oaci, COALESCE(nombre,'') AS nombre, COALESCE(region,'') AS region
          FROM estaciones
          WHERE oaci IS NOT NULL AND oaci <> ''
          ORDER BY region DESC";
  return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
function fetch_user_perms(PDO $pdo, int $uid): array {
  $tbl = $pdo->query("SHOW TABLES LIKE 'user_station_perms'")->fetchColumn();
  if (!$tbl) return [];
  $st = $pdo->prepare("SELECT oaci, can_view, can_edit FROM user_station_perms WHERE user_id=?");
  $st->execute([$uid]);
  $m = [];
  foreach ($st as $r) {
    $o = strtoupper(trim((string)$r['oaci']));
    $m[$o] = ['can_view'=> (int)$r['can_view']===1, 'can_edit'=> (int)$r['can_edit']===1];
  }
  return $m;
}

// -------- datos base --------
$users = fetch_users($pdo);
if (!$users) {
  http_response_code(500);
  exit('No hay usuarios en app_users. Crea al menos uno desde admin/usuarios.php');
}
$selected_id = (int)($_GET['user_id'] ?? ($users[0]['id'] ?? 0));

// Encontrar el usuario seleccionado y su rol
$selected_user = null;
foreach ($users as $uu) {
  if ((int)$uu['id'] === (int)$selected_id) { $selected_user = $uu; break; }
}
$is_super = ($selected_user && ($selected_user['role'] ?? '') === 'admin');

// -------- POST guardar --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $selected_id = (int)($_POST['user_id'] ?? 0);

  // Verificar rol del usuario al que se le intentan editar permisos
  $stRole = $pdo->prepare("SELECT role FROM app_users WHERE id=?");
  $stRole->execute([$selected_id]);
  $roleRow = $stRole->fetch(PDO::FETCH_ASSOC);
  $roleSel = $roleRow['role'] ?? 'viewer';

  if ($roleSel === 'admin') {
    // No aplicar ningún cambio a admins: siempre tienen acceso total
    header('Location: permisos.php?user_id=' . $selected_id . '&msg=' . rawurlencode('El superusuario tiene acceso total. No se guardan permisos.'));
    exit;
  }

  // Controles vienen como arrays asociativos por oaci
  $perm_view = array_keys($_POST['perm_view'] ?? []); // ['MMTJ'=> '1', ...] -> claves
  $perm_edit = array_keys($_POST['perm_edit'] ?? []);

  // Normalizar a sets
  $set_view = [];
  foreach ($perm_view as $o) { $o = strtoupper(trim((string)$o)); if ($o!=='') $set_view[$o]=true; }
  $set_edit = [];
  foreach ($perm_edit as $o) { $o = strtoupper(trim((string)$o)); if ($o!=='') $set_edit[$o]=true; }

  // Guardamos como: limpiar existentes e insertar seleccionados
  $pdo->beginTransaction();
  try {
    $del = $pdo->prepare("DELETE FROM user_station_perms WHERE user_id=?");
    $del->execute([$selected_id]);

    if (!empty($set_view) || !empty($set_edit)) {
      $ins = $pdo->prepare("INSERT INTO user_station_perms (user_id, oaci, can_view, can_edit) VALUES (?,?,?,?)");
      // consolidar oaci presentes en cualquiera de los dos sets
      $all_oaci = array_unique(array_merge(array_keys($set_view), array_keys($set_edit)));
      sort($all_oaci);
      foreach ($all_oaci as $oaci) {
        $v = !empty($set_view[$oaci]) ? 1 : 0;
        $e = !empty($set_edit[$oaci]) ? 1 : 0;
        // Regla: si puede editar, también debe poder ver
        if ($e && !$v) $v = 1;
        $ins->execute([$selected_id, $oaci, $v, $e]);
      }
    }

    $pdo->commit();
    header('Location: permisos.php?user_id=' . $selected_id . '&msg=' . rawurlencode('Permisos guardados'));
    exit;
  } catch (Throwable $e) {
    $pdo->rollBack();
    header('Location: permisos.php?user_id=' . $selected_id . '&msg=' . rawurlencode('Error al guardar: ' . $e->getMessage()));
    exit;
  }
}

// -------- GET vista --------
$stations = fetch_stations($pdo);
$perms    = $selected_id ? fetch_user_perms($pdo, $selected_id) : [];

?>
<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Permisos — Admin</title>
  <?php require dirname(__DIR__) . '/public/includes/HEAD-CSS.php'; ?>

</head>
<body>
  <nav class="navbar navbar-expand-lg border-bottom">
    <div class="container-fluid">
      <a class="navbar-brand fw-semibold" href="../index.php">Control Regional</a>
      <div class="ms-auto d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="usuarios.php">Usuarios</a>
        <a class="btn btn-outline-secondary btn-sm" href="map_estaciones.php">Mapear estaciones</a>
        <a class="btn btn-outline-secondary btn-sm" href="../index.php">Volver</a>
      </div>
    </div>
  </nav>

  <div class="container-fluid py-4 admin-wrap">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 m-0">Permisos por estación</h1>
      <div class="legend small text-muted">
        <i class="fa-regular fa-chess-king text-danger"></i> admin
        <i class="fa-regular fa-id-card text-primary ms-2"></i> regional
        <i class="fa-regular fa-circle-user text-info ms-2"></i> estación
        <i class="fa-regular fa-eye text-secondary ms-2"></i> viewer
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Seleccionar usuario</label>
            <select name="user_id" class="form-select form-select-sm form-select-smaller" onchange="this.form.submit()">
              <?php foreach ($users as $u): 
                $icon = match($u['role']) {
                  'admin'    => '<i class="fa-regular fa-chess-king text-danger"></i>',
                  'regional' => '<i class="fa-regular fa-id-card text-primary"></i>',
                  'estacion' => '<i class="fa-regular fa-circle-user text-info"></i>',
                  default    => '<i class="fa-regular fa-eye text-secondary"></i>',
                };
                $active = (int)$u['is_active']===1 ? '<i class="fa-solid fa-circle-check text-success ms-1" title="Activo"></i>' : '<i class="fa-solid fa-circle-xmark text-danger ms-1" title="Inactivo"></i>';
                $label = $icon . ' ' . htmlspecialchars($u['nombre']) . ' — <span class="muted">'.htmlspecialchars($u['email']).'</span>' . $active;
                ?>
                <option value="<?=$u['id']?>" <?= $u['id']===$selected_id?'selected':'' ?>>
                  <?= strip_tags($label) /* el select no acepta HTML visible */ ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Cambia de usuario para editar sus permisos.</div>
          </div>
          <div class="col-md-6 text-md-end">
            <a class="btn btn-outline-primary btn-sm" href="usuarios.php?edit=<?=$selected_id?>">Editar usuario</a>
          </div>
        </form>
      </div>
    </div>
    <?php if ($is_super): ?>
  <div class="alert alert-warning mb-3">
    <i class="fa-regular fa-chess-king me-1"></i>
    Este usuario es <strong>superusuario</strong> (admin): tiene acceso total a todas las estaciones.
    La matriz de permisos no aplica y no se puede modificar.
  </div>
<?php endif; ?>

    <form method="post" class="card">
      <input type="hidden" name="user_id" value="<?=$selected_id?>">
      <div class="card-body p-0">
        <div class="table-wrap">
          <table class="table table-sm align-middle table-hover">
            <thead class="thead-small">
              <tr>
                <th class="w-oaci">OACI</th>
                <th>Estación</th>
                <th class="w-region">Región</th>
                <th class="text-center w-actions">Ver</th>
                <th class="text-center w-actions">Editar</th>
              </tr>
            </thead>
            <tbody>
                <?php foreach ($stations as $st):
                  $oaci = strtoupper(trim($st['oaci'] ?? ''));
                  if ($oaci==='') continue;
                
                  // Si es superusuario, muéstralo como todo activo y deshabilitado
                  if ($is_super) {
                    $p = ['can_view' => true, 'can_edit' => true];
                    $disabledAttr = ' disabled';
                  } else {
                    $p = $perms[$oaci] ?? ['can_view'=>false,'can_edit'=>false];
                    $disabledAttr = '';
                  }
                ?>
                  <tr>
                    <td><span class="fw-semibold"><?=$oaci?></span></td>
                    <td><?= htmlspecialchars($st['nombre'] ?: '-') ?></td>
                    <td><span class="badge bg-light text-dark border"><?= $st['region'] ?: '-' ?></span></td>
                    <td class="text-center">
                      <input type="checkbox" class="form-check-input checkbox-lg"
                             name="perm_view[<?=$oaci?>]" value="1"
                             <?= $p['can_view'] ? 'checked' : '' ?><?= $disabledAttr ?>>
                    </td>
                    <td class="text-center">
                      <input type="checkbox" class="form-check-input checkbox-lg"
                             name="perm_edit[<?=$oaci?>]" value="1"
                             <?= $p['can_edit'] ? 'checked' : '' ?><?= $disabledAttr ?>>
                    </td>
                  </tr>
                <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
        <div class="sticky-actions">
          <button class="btn btn-primary" <?= $is_super ? 'disabled' : '' ?>>
            <i class="fa-regular fa-floppy-disk me-1"></i> Guardar cambios
          </button>
          <a class="btn btn-outline-secondary" href="permisos.php?user_id=<?=$selected_id?>">Cancelar</a>
        </div>
    </form>

    <div class="mt-3 d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCheckAllView" <?= $is_super ? 'disabled' : '' ?>>Marcar todo Ver</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnUncheckAllView" <?= $is_super ? 'disabled' : '' ?>>Quitar todo Ver</button>
      <span class="vr"></span>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCheckAllEdit" <?= $is_super ? 'disabled' : '' ?>>Marcar todo Editar</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnUncheckAllEdit" <?= $is_super ? 'disabled' : '' ?>>Quitar todo Editar</button>
    </div>

  <?php require dirname(__DIR__) . '/public/includes/Foot-js.php'; ?>
  <script>
    (function(){
      function setAll(selector, checked){
        document.querySelectorAll(selector).forEach(function(cb){ cb.checked = !!checked; });
      }
      var q = function(s){ return document.getElementById(s); };
      var viewSel = 'input[name^="perm_view["]';
      var editSel = 'input[name^="perm_edit["]';

      q('btnCheckAllView')?.addEventListener('click', function(){ setAll(viewSel, true); });
      q('btnUncheckAllView')?.addEventListener('click', function(){ setAll(viewSel, false); });
      q('btnCheckAllEdit')?.addEventListener('click', function(){ setAll(editSel, true); setAll(viewSel, true); /* si edit, también ver */ });
      q('btnUncheckAllEdit')?.addEventListener('click', function(){ setAll(editSel, false); });
    })();
  </script>
</body>
</html>
