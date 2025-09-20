<?php
require_once dirname(__DIR__) . '/lib/guard.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/db.php';
if (!is_admin()) { http_response_code(403); exit('Solo admin'); }
$pdo = db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id_est = (int)($_POST['id_est'] ?? 0);
  $oaci = strtoupper(substr($_POST['oaci'] ?? '', 0, 4));
  $nombre = trim($_POST['nombre'] ?? '');
  $region = $_POST['region'] ?? null;
  if ($id_est>0 && $oaci) {
    $st=$pdo->prepare("INSERT INTO estaciones (id_estacion,oaci,nombre,region) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE oaci=VALUES(oaci),nombre=VALUES(nombre),region=VALUES(region)");
    $st->execute([$id_est,$oaci,$nombre,$region]);
  }
  header('Location: map_estaciones.php'); exit;
}

$rows = $pdo->query("SELECT DISTINCT estacion FROM empleados")->fetchAll();
$map = $pdo->query("SELECT * FROM estaciones")->fetchAll();
$mapped = []; foreach ($map as $m) $mapped[$m['id_estacion']] = $m;
?>
<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mapeo de Estaciones</title>
  <?php require dirname(__DIR__) . '/public/includes/HEAD-CSS.php'; ?>

</head>
<body>
  <nav class="navbar navbar-expand-lg border-bottom">
    <div class="container-fluid">
      <a class="navbar-brand fw-semibold" href="../index.php">Mapeo de Estaciones</a>
      <div class="ms-auto d-flex align-items-center gap-2 page-actions">
        <a class="btn btn-outline-secondary btn-sm" href="usuarios.php">Volver</a>
      </div>
    </div>
  </nav>
      <div class="col-lg-7">
        <div class="card">
          <div class="card-body">
            <h2 class="h6 mb-3">Lista</h2>
            <div class="table-wrap">
                <table class="table is-triped is-narrow">
                    <thead>
                        <tr>
                            <th style="width: 10%;">ID</th>
                            <th style="width: 20%;">OACI</th> 
                            <th style="width: 40%;">Nombre</th>
                            <th style="width: 20%;">Región</th>
                            <th style="width: 10%;">Guardar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): 
    $id = (int)$r['estacion']; 
    if ($id === 0) continue;
    $m = $mapped[$id] ?? null;?>
                        <tr><form method="post">
                          <td><input type="number" class="input is-small" name="id_est" value="<?=$id?>" readonly></td>
                          <td><input type="text" class="input is-small" name="oaci" value="<?=htmlspecialchars($m['oaci'] ?? '')?>" maxlength="4" placeholder="MMTJ"></td>
                          <td><input type="text" class="input is-small" name="nombre" value="<?=htmlspecialchars($m['nombre'] ?? '')?>" placeholder="Tijuana"></td>
                          <td><select name="region" class="input is-small"><?php $reg=$m['region'] ?? ''; ?>
                              <option value="">(selecciona)</option>
                              <option value="JRTIJ" <?=$reg==='JRTIJ'?'selected':''?>>JRTIJ</option>
                              <option value="JRSJD" <?=$reg==='JRSJD'?'selected':''?>>JRSJD</option>
                          </select></td>
                          <td><button class="btn btn-primary btn-sm">Guardar</button></td>
                        </form></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
          </div>
        </div>
      </div>

  <?php require dirname(__DIR__) . '/public/includes/Foot-js.php'; ?>

</body></html>