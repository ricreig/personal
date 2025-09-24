<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/guard.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/db.php';
$u = auth_user();

$ok = null; $err = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $current = $_POST['current'] ?? '';
  $new1 = $_POST['new1'] ?? '';
  $new2 = $_POST['new2'] ?? '';

  if ($new1 === '' || $new2 === '') {
    $err = 'Debes escribir la nueva contraseña dos veces.';
  } elseif ($new1 !== $new2) {
    $err = 'Las contraseñas no coinciden.';
  } else {
    // Verificar la actual
    $st=$pdo->prepare("SELECT pass_hash FROM app_users WHERE id=?");
    $st->execute([$u['id']]);
    $row=$st->fetch();
    if (!$row || !password_verify($current, $row['pass_hash'])) {
      $err = 'La contraseña actual no es correcta.';
    } else {
      // Reglas básicas de fortaleza (8+ y mezcla)
      if (strlen($new1) < 8) { $err = 'La nueva contraseña debe tener al menos 8 caracteres.'; }
      if (!$err) {
        $hash = password_hash($new1, PASSWORD_DEFAULT);
        $st=$pdo->prepare("UPDATE app_users SET pass_hash=? WHERE id=?");
        $st->execute([$hash, $u['id']]);
        $ok = 'Contraseña actualizada.';
      }
    }
  }
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d1117" />
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Control Regional de Personal">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Mi cuenta</title>
<?php require __DIR__.'/includes/HEAD-CSS.php'; ?>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Control Regional</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <?=htmlspecialchars($u['nombre'])?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item active" href="account.php">Mi cuenta</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="logout.php">Cerrar sesión</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="container py-4" style="max-width:720px">
  <h1 class="h4 mb-3">Mi cuenta</h1>
  <?php if ($ok): ?><div class="alert alert-success"><?=$ok?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?=$err?></div><?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <h2 class="h6">Detalles</h2>
      <dl class="row mb-0">
        <dt class="col-sm-3">Nombre</dt><dd class="col-sm-9"><?=htmlspecialchars($u['nombre'])?></dd>
        <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?=htmlspecialchars($u['email'])?></dd>
        <dt class="col-sm-3">Rol</dt><dd class="col-sm-9"><?=htmlspecialchars($u['role'])?></dd>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h2 class="h6">Cambiar contraseña</h2>
      <form method="post">
        <div class="mb-2"><label class="form-label">Contraseña actual</label><input type="password" autocomplete="current-password" name="current" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Nueva contraseña</label><input type="password" autocomplete="new-password" name="new1" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Repetir nueva contraseña</label><input type="password" autocomplete="new-password" name="new2" class="form-control" required></div>
        <button class="btn btn-primary">Actualizar</button>
      </form>
      <p class="text-muted small mb-0 mt-2">Mínimo 8 caracteres. Te recomendamos combinar mayúsculas, minúsculas, números y símbolos.</p>
    </div>
  </div>
</div>

<?php require __DIR__.'/includes/Foot-js.php'; ?>
</body>
</html>
