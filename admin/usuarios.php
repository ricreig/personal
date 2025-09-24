<?php
declare(strict_types=1);
// Arranque común (sesión, DB, helpers, rutas y assets)
require_once dirname(__DIR__) . '/lib/bootstrap_public.php'; // carga session_boot(), db(), auth helpers y paths

// Seguridad: solo admin
if (!is_admin()) { http_response_code(403); exit('Solo admin'); }

$pdo = db();
$msg = $_GET['msg'] ?? null;

// Crear / Editar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id      = (int)($_POST['id'] ?? 0);
  $email   = trim((string)($_POST['email'] ?? ''));
  $nombre  = trim((string)($_POST['nombre'] ?? ''));
  $role    = (string)($_POST['role'] ?? 'viewer');
  $control = trim((string)($_POST['control'] ?? '')) ?: null;
  $pass    = (string)($_POST['pass'] ?? '');
  $active  = !empty($_POST['is_active']) ? 1 : 0;

  if ($email === '' || $nombre === '') {
    header('Location: usuarios.php?msg=' . rawurlencode('Faltan datos'));
    exit;
  }

  if ($id > 0) {
    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $st = $pdo->prepare("UPDATE app_users SET email=?, nombre=?, role=?, control=?, is_active=?, pass_hash=? WHERE id=?");
      $st->execute([$email,$nombre,$role,$control,$active,$hash,$id]);
    } else {
      $st = $pdo->prepare("UPDATE app_users SET email=?, nombre=?, role=?, control=?, is_active=? WHERE id=?");
      $st->execute([$email,$nombre,$role,$control,$active,$id]);
    }
  } else {
    if ($pass === '') {
      header('Location: usuarios.php?msg=' . rawurlencode('Contraseña requerida'));
      exit;
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO app_users (email,nombre,pass_hash,role,control,is_active) VALUES (?,?,?,?,?,?)");
    $st->execute([$email,$nombre,$hash,$role,$control,$active]);
    $id = (int)$pdo->lastInsertId();
  }

  header('Location: usuarios.php?msg=Guardado');
  exit;
}

// Listado + edición
$edit_id   = (int)($_GET['edit'] ?? 0);
$edit_user = null;
if ($edit_id > 0) {
  $st=$pdo->prepare("SELECT * FROM app_users WHERE id=?");
  $st->execute([$edit_id]);
  $edit_user = $st->fetch();
}
$users = $pdo->query("SELECT id,email,nombre,role,is_active FROM app_users ORDER BY role,nombre")->fetchAll();
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
  <title>Usuarios — Admin</title>
  <?php require dirname(__DIR__) . '/public/includes/HEAD-CSS.php'; ?>
  <style>
 table {
  table-layout: wrap;
  width: 100%;
  border-collapse: collapse;
}

th, td {
  border: 1px solid black;
  padding: 8px;
  text-align: center;
}

/* For a table with 3 columns, you can explicitly set width if desired */
/* th:nth-child(1), td:nth-child(1) { width: 33.33%; } */
/* th:nth-child(2), td:nth-child(2) { width: 33.33%; } */
/* th:nth-child(3), td:nth-child(3) { width: 33.33%; } */
.admin-wrap { 
  max-width: 1100px; 
  margin: 0 auto; 
  font-size: .9rem;
}

.card { 
  margin-bottom: 1rem; 
  border-radius: 12px; 
  box-shadow: 0 2px 6px rgba(0,0,0,.08); 
}

.table-wrap { 
  width:100%; 
  overflow:auto; 
  -webkit-overflow-scrolling:touch; 
  border-radius:12px; 
  border:1px solid var(--border,rgba(0,0,0,.08)); 
}

.table { 
  margin-bottom:0; 
  font-size: .9rem; 
}

.table td, .table th { 
  vertical-align: middle; 
  white-space: nowrap; 
}

.thead-small th { 
  font-size: .75rem; 
  letter-spacing: .02em; 
  text-transform: uppercase; 
  color:#6c757d; 
}

.muted { 
  color: var(--muted,#6c757d); 
  font-size: .8rem; 
}

.badge-role { 
  font-weight:600; 
}

.legend i { 
  margin-right:.35rem; 
}

  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg border-bottom">
    <div class="container-fluid">
      <a class="navbar-brand fw-semibold" href="../index.php">Control Regional</a>
      <div class="ms-auto d-flex align-items-center gap-2 page-actions">
        <a class="btn btn-outline-secondary btn-sm" href="../index.php">Volver</a>
      </div>
    </div>
  </nav>
  <div class="container-fluid py-4 admin-wrap">
        <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 m-0">Alta y Modificación de Credenciales</h1>
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

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card">
          <div class="card-body">
            <h2 class="h6 mb-3"><?= $edit_user ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
            <form method="post" class="vstack gap-2">
              <input type="hidden" name="id" value="<?= (int)($edit_user['id'] ?? 0) ?>">
              <div>
              <div>
                <label class="form-label">Numero de Control</label>
                <input name="control" type="number" class="form-control" required value="<?= htmlspecialchars($edit_user['control'] ?? '') ?>">
              </div>
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" required value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>">
              </div>
              <div>
                <label class="form-label">Nombre</label>
                <input name="nombre" type="text" class="form-control" required value="<?= htmlspecialchars($edit_user['nombre'] ?? '') ?>">
              </div>
              <div>
                <label class="form-label">Rol</label>
                <?php $role = $edit_user['role'] ?? 'viewer'; ?>
                <select name="role" class="form-select">
                  <option value="admin"    <?= $role==='admin'?'selected':'' ?>>SuperUser (acceso total)</option>
                  <option value="regional" <?= $role==='regional'?'selected':'' ?>>Regional</option>
                  <option value="estacion" <?= $role==='estacion'?'selected':'' ?>>Estación</option>
                  <option value="viewer"   <?= $role==='viewer'?'selected':'' ?>>Solo Visualización</option>
                </select>
              </div>
              <div>
                <label class="form-label">Contraseña <?= $edit_user ? '(dejar vacío para mantener)' : '' ?></label>
                <input name="pass" type="text" class="form-control" placeholder="<?= $edit_user ? '•••••• (sin cambio)' : 'Contraseña inicial' ?>">
              </div>
              <div class="form-check">
                <?php $ia = !empty($edit_user) ? (int)$edit_user['is_active'] : 1; ?>
                <input class="form-check-input" type="checkbox" name="is_active" id="ia" <?= $ia ? 'checked' : '' ?>>
                <label class="form-check-label" for="ia">Activo</label>
              </div>
              <div>
                <button class="btn btn-primary">Guardar</button>
                <?php if ($edit_user): ?>
                  <a class="btn btn-outline-secondary" href="usuarios.php">Cancelar</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card">
          <div class="card-body">
            <h2 class="h6 mb-3">Lista</h2>
            <div class="table-wrap">
                <table class="table is-striped is-narrow">
                  <thead>
                    <tr>
                      <th style="width: 25%;">Nombre</th>
                      <th style="width: 35%;">Email</th>
                      <th style="width: 8%;">Rol</th>
                      <th style="width: 8%;">Activo</th>
                      <th style="width: 15%;">Permisos</th>
                      <th style="width: 10%;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($users as $us): ?>
                      <tr>
                        <td><?=htmlspecialchars($us['nombre'])?></td>
                        <td class="text-small"><?=htmlspecialchars($us['email'])?></td>
                        <td class="text-center">
                          <?php
                            switch ($us['role']) {
                              case 'admin':    echo '<i class="fa-regular fa-chess-king text-danger" title="Admin"></i>'; break;
                              case 'regional': echo '<i class="fa-regular fa-id-card text-primary" title="Regional"></i>'; break;
                              case 'estacion': echo '<i class="fa-regular fa-circle-user text-info" title="Estación"></i>'; break;
                              default:         echo '<i class="fa-regular fa-eye text-secondary" title="Viewer"></i>';
                            }
                          ?>
                        </td>
                        <td class="text-center">
                          <?php if ($us['is_active']): ?>
                            <i class="fa-solid fa-circle-check text-success" title="Activo"></i>
                          <?php else: ?>
                            <i class="fa-solid fa-circle-xmark text-danger" title="Inactivo"></i>
                          <?php endif; ?>
                        </td>
                        <td class="text-center">
                          <a class="btn btn-outline-secondary btn-sm" href="permisos.php?user_id=<?=$us['id']?>">Permisos</a>
                        </td>
                        <td class="text-center">
                          <a class="btn btn-sm btn-primary" href="usuarios.php?edit=<?=$us['id']?>">Editar</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
            </div>
            <hr class="my-3">
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-primary btn-sm" href="map_estaciones.php">Mapear estaciones</a>
              <a class="btn btn-outline-primary btn-sm" href="permisos.php">Permisos por estación</a>
            </div>
          </div>
        </div>
      </div>
    </div>
</div>

  <?php require dirname(__DIR__) . '/public/includes/Foot-js.php'; ?>
</body>
</html>
