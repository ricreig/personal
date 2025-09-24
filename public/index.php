<?php
declare(strict_types=1);
require_once dirname(__DIR__,1) . '/lib/bootstrap_public.php';
require_once dirname(__DIR__,1) . '/lib/paths.php';
cr_define_php_paths(); // define constantes PHP si no existen

// Obliga sesión; si no hay, redirige a login
$u = auth_user();
if (!$u) {
  header('Location: ' . rtrim(BASE_URL, '/') . '/login.php?err=timeout');
  exit;
}
$role = (string)($u['role'] ?? 'viewer');
$pdo  = db();

$ESPECS = ['MANDOS'=>'MANDOS','ATCO'=>'ATCO','OSIV'=>'OSIV','ADMIN'=>'APOYO ADMON','IDS'=>'IDS'];
$LIC_OPC = ['CTA III'=>'CTA III','OOA'=>'OOA','MET I'=>'MET I','TEC MTTO'=>'TEC MTTO','CAM'=>'Conducción GAP (CAM)'];
$LCAR_OPC = ['A'=>'Tipo A','B'=>'Tipo B','C'=>'Tipo C','DL'=>'DL'];
$CLASE_OPC = ['GPO-3'=>'GPO-3','GPO-4'=>'GPO-4','CLASE-3'=>'CLASE-3'];

$stationOptions = [];
try {
  $st = $pdo->query('SELECT id_estacion, oaci FROM estaciones ORDER BY oaci');
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $id = (int)($row['id_estacion'] ?? 0);
    $oaci = strtoupper(trim((string)($row['oaci'] ?? '')));
    if ($id > 0 && $oaci !== '') {
      $stationOptions[] = ['id' => $id, 'oaci' => $oaci];
    }
  }
} catch (Throwable $e) {
  $stationOptions = [];
}

if (!is_admin()) {
  $matrix = function_exists('user_station_matrix') ? user_station_matrix($pdo, (int)($u['id'] ?? 0)) : [];
  $stationOptions = array_values(array_filter($stationOptions, static function (array $opt) use ($matrix): bool {
    $oaci = $opt['oaci'] ?? '';
    return $oaci !== '' && !empty($matrix[$oaci]);
  }));
}
$hasStationAccess = is_admin() || count($stationOptions) > 0;
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
  <title>Control Regional — Personal</title>
  <?php require __DIR__ . '/includes/HEAD-CSS.php'; ?>

</head>
<body>
<nav class="navbar border-bottom">
  <div class="container-fluid nav-3up">

    <!-- IZQUIERDA: LOGO -->
    <div class="nav-left">
      <a href="index.php" class="d-inline-block">
        <img
          src="assets/SENEAM_Logo_H.webp"
          srcset="assets/SENEAM_Logo_H.webp 260w, assets/SENEAM_Logo_H@2x.webp 520w"
          sizes="(max-width: 768px) 180px, 260px"
          alt="SENEAM" width="260" height="76"
          fetchpriority="high" loading="eager" decoding="async"
          style="height:auto;max-height:80px">
      </a>
    </div>

    <!-- CENTRO: TÍTULO -->
    <div class="nav-center">
      <a class="navbar-brand fw-semibold m-0 text-center" href="index.php">
        Control Regional de Personal
      </a>
    </div>

    <!-- DERECHA: BOTONERA / MENÚ USUARIO -->
      <div class="nav-right d-flex flex-wrap align-items-center justify-content-end gap-2 text-end">
        <?php if ($role !== 'viewer'): ?>
          <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#recordCreateModal">
            Agregar registro
          </button>
        <?php endif; ?>
        <?php if (function_exists('is_admin') && is_admin()): ?>
          <a class="btn btn-outline-primary btn-sm" href="../admin/usuarios.php">Admin</a>
          <a class="btn btn-outline-primary btn-sm" href="/public/diagnose.php">Diagnóstico</a>
        <?php endif; ?>
        <a class="btn btn-outline-primary btn-sm" href="/public/prestaciones.php">Prestaciones</a>
      <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <?= htmlspecialchars($u['nombre'] ?? $u['email'] ?? 'Usuario') ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="account.php">Mi cuenta</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="logout.php">Cerrar sesión</a></li>
        </ul>
      </div>
    </div>

  </div>
</nav>

    <div class="container-fluid py-4">
    
<!-- Header -->
<div class="card card-hero mb-3">
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <h1 class="h4 mb-1">Personal</h1>
      <div class="text-secondary small">Sistema para Gestión de Capital Humano</div>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
      <!-- Buscador -->
      <input type="search" id="busquedaGlobal" class="form-control" placeholder="Buscar…" style="min-width:260px">
      <!-- Botonera dinámica (la llena mountToolbar(view) desde app.js) -->
<div id="botoneraDT" class="d-flex flex-wrap gap-2">
  <div id="viewToggle" class="btn-group" role="group" aria-label="Vista">
    <button type="button" class="btn btn-sm btn-primary" data-view="lic">Licencias</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-view="datos">Datos</button>
  </div>

  <div class="btn-group" role="group" aria-label="Filtros">
    <button id="btnFiltrarEst"  type="button" class="btn btn-sm btn-outline-secondary">Estación…</button>
    <button id="btnFiltrarArea" type="button" class="btn btn-sm btn-outline-secondary">Área…</button>
    <button id="btnFiltrarNom"  type="button" class="btn btn-sm btn-outline-secondary">Nombramiento…</button>
</div>
	<div id="dtButtonsSlot"></div>

  </div>
</div>
    </div>
  </div>

    
      <!-- Tabla -->
      <div class="card card-hero">
        <div class="card-body">
          <div class="table-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
            <table id="tabla" class="table is-striped is-narrow" class="pagination is-rounded"></table>
          </div>
        </div>
      </div>
    
    </div>


    <?php if ($role !== 'viewer'): ?>
    <!-- Modal: Agregar registro -->
    <div class="modal fade" id="recordCreateModal" tabindex="-1" aria-hidden="true" data-has-stations="<?= $hasStationAccess ? '1' : '0' ?>">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title">Agregar nuevo registro</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <?php if (!$hasStationAccess): ?>
              <div class="alert alert-warning">No cuentas con estaciones asignadas. Solicita al administrador mapearte al menos una para poder crear registros nuevos.</div>
            <?php endif; ?>
            <form id="recordCreateForm">
              <fieldset class="row g-3" <?= $hasStationAccess ? '' : 'disabled' ?>>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newControl">No. de control</label>
                  <input id="newControl" name="control" type="text" class="form-control" inputmode="numeric" pattern="\d+" required>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newSiglas">Siglas</label>
                  <input id="newSiglas" name="siglas" type="text" class="form-control" maxlength="3" autocomplete="off">
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="newNombre">Nombre completo</label>
                  <input id="newNombre" name="nombres" type="text" class="form-control" required>
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label" for="newEmail">Correo electrónico</label>
                  <input id="newEmail" name="email" type="email" class="form-control" autocomplete="off">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newRFC">RFC</label>
                  <input id="newRFC" name="rfc" type="text" class="form-control" autocomplete="off">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newCURP">CURP</label>
                  <input id="newCURP" name="curp" type="text" class="form-control" autocomplete="off">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newNacimiento">Nacimiento</label>
                  <input id="newNacimiento" name="fecha_nacimiento" type="text" class="form-control" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newAnt">Antigüedad</label>
                  <input id="newAnt" name="ant" type="text" class="form-control" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
                </div>
                <div class="col-12">
                  <label class="form-label" for="newDireccion">Dirección</label>
                  <textarea id="newDireccion" name="direccion" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newPlaza">Código Plaza</label>
                  <input id="newPlaza" name="plaza" type="text" class="form-control" autocomplete="off">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newEspec">Área</label>
                  <select id="newEspec" name="espec" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($ESPECS as $k => $label): ?>
                      <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newEstacion">Estación</label>
                  <select id="newEstacion" name="estacion" class="form-select" required>
                    <option value="">—</option>
                    <?php foreach ($stationOptions as $opt): ?>
                      <option value="<?= (int)$opt['id'] ?>"><?= htmlspecialchars($opt['oaci']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (!is_admin()): ?>
                    <div class="form-text">Solo podrás elegir entre tus estaciones asignadas.</div>
                  <?php endif; ?>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newNivel">Nivel</label>
                  <input id="newNivel" name="nivel" type="text" class="form-control" autocomplete="off">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label" for="newNSS">NSS</label>
                  <input id="newNSS" name="nss" type="text" class="form-control" autocomplete="off">
                </div>
                <div class="col-12">
                  <label class="form-label" for="newPuesto">Nombramiento / Puesto</label>
                  <input id="newPuesto" name="puesto" type="text" class="form-control" autocomplete="off">
                </div>
                <div class="col-12">
                  <div class="row g-3">
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newTipo1">Tipo licencia 1</label>
                      <select id="newTipo1" name="tipo1" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($LIC_OPC as $k => $label): ?>
                          <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newLic1">No. licencia 1</label>
                      <input id="newLic1" name="licencia1" type="text" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newVig1">Vigencia 1</label>
                      <input id="newVig1" name="vigencia1" type="text" class="form-control" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="row g-3">
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newTipo2">Tipo licencia 2</label>
                      <select id="newTipo2" name="tipo2" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($LIC_OPC as $k => $label): ?>
                          <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newLic2">No. licencia 2</label>
                      <input id="newLic2" name="licencia2" type="text" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newVig2">Vigencia 2</label>
                      <input id="newVig2" name="vigencia2" type="text" class="form-control" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="row g-3">
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newExamen1">LCAR / DL</label>
                      <select id="newExamen1" name="examen1" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($LCAR_OPC as $k => $label): ?>
                          <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newExamenVig1">Vigencia LCAR/DL</label>
                      <input id="newExamenVig1" name="examen_vig1" type="text" class="form-control" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newRTARI">RTARI</label>
                      <input id="newRTARI" name="rtari" type="text" class="form-control" autocomplete="off">
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="row g-3">
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newRTARIVig">Vigencia RTARI</label>
                      <input id="newRTARIVig" name="rtari_vig" type="text" class="form-control" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
                    </div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="row g-3">
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newExamen2">Clase examen médico</label>
                      <select id="newExamen2" name="examen2" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($CLASE_OPC as $k => $label): ?>
                          <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newExpediente">Expediente médico</label>
                      <input id="newExpediente" name="exp_med" type="text" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label" for="newExamenVig2">Vigencia examen médico</label>
                      <input id="newExamenVig2" name="examen_vig2" type="text" class="form-control" placeholder="dd/mm/aaaa" data-mask="date" inputmode="numeric" maxlength="10" autocomplete="off">
                    </div>
                  </div>
                </div>
              </fieldset>
            </form>
            <div id="recordCreateMsg" class="small text-secondary mt-2"></div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" form="recordCreateForm" class="btn btn-primary" id="recordCreateSubmit" <?= $hasStationAccess ? '' : 'disabled' ?>>Guardar</button>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Documentos -->
    <div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 id="docModalTitle" class="modal-title">Documentos</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
    
          <div class="modal-body">
            <!-- Vista previa -->
            <div id="docPreview" class="mb-3 d-none">
              <div id="docPreviewInfo" class="small text-secondary mb-2"></div>
              <img id="docPreviewImg" alt="Vista previa" class="img-fluid d-none">
              <iframe id="docPreviewPdf" class="w-100 d-none" style="height:60vh; border:1px solid var(--border)"></iframe>
              <div id="docPreviewMsg" class="small text-warning mt-2"></div>
            </div>

            <div id="docDropZone" class="doc-dropzone mb-3" tabindex="0">
              <div class="doc-drop-inner">
                <div class="doc-drop-icon mb-2">📄</div>
                <p class="mb-2">Arrastra y suelta un documento aquí</p>
                <div class="doc-drop-controls d-flex flex-wrap align-items-center justify-content-center gap-2">
                  <label class="form-label mb-0 small text-secondary" for="docDropType">Guardar como</label>
                  <select id="docDropType" class="form-select form-select-sm" style="min-width:160px"></select>
                  <button type="button" class="btn btn-sm btn-outline-light" id="docBrowseBtn">Seleccionar archivo</button>
                </div>
                <div class="small text-secondary mt-2">Formatos permitidos: PDF, JPG, PNG, WEBP (máx 25 MB)</div>
              </div>
            </div>

            <!-- Tarjetas de documentos -->
            <div id="docTiles" class="row g-3" data-control=""></div>

            <!-- Input oculto para subir -->
            <input type="file" id="docFileInput" class="d-none" accept="image/*,application/pdf">
          </div>
    
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal: Filtros -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 id="filterModalLabel" class="modal-title">Filtrar</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
    
          <div class="modal-body">
            <div id="filterContainer">Cargando…</div>
          </div>
    
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="button" class="btn btn-outline-secondary" id="filterClearBtn">Limpiar</button>
            <button type="button" class="btn btn-primary" id="filterApplyBtn">Aplicar</button>
          </div>
        </div>
      </div>
    </div>

<?php require __DIR__ . '/includes/Foot-js.php'; ?>
<!-- Toda la lógica de la tabla/documents vive en /public/assets/app.js -->
</body>
</html>
