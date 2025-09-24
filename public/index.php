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
?>
<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
      <div class="nav-right d-flex align-items-center justify-content-end gap-2">
        <?php if ($role !== 'viewer'): ?>
          <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#userCreateModal">
            Crear usuario
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
    <!-- Modal: Crear usuario -->
    <div class="modal fade" id="userCreateModal" tabindex="-1" aria-hidden="true" data-user-role="<?= htmlspecialchars($role) ?>">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title">Crear usuario</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <form id="userCreateForm" class="row g-3">
              <div class="col-12 col-sm-6">
                <label class="form-label" for="userCreateControl">Número de control</label>
                <input id="userCreateControl" name="control" type="number" class="form-control" min="0" step="1" inputmode="numeric" required>
              </div>
              <div class="col-12 col-sm-6">
                <label class="form-label" for="userCreateEmail">Correo electrónico</label>
                <input id="userCreateEmail" name="email" type="email" class="form-control" required>
              </div>
              <div class="col-12">
                <label class="form-label" for="userCreateName">Nombre completo</label>
                <input id="userCreateName" name="nombre" type="text" class="form-control" required>
              </div>
              <div class="col-12 col-sm-6">
                <label class="form-label" for="userCreateRole">Rol</label>
                <select id="userCreateRole" name="role" class="form-select">
                  <option value="admin">SuperUser (acceso total)</option>
                  <option value="regional">Regional</option>
                  <option value="estacion">Estación</option>
                  <option value="viewer" selected>Solo visualización</option>
                </select>
              </div>
              <div class="col-12 col-sm-6">
                <label class="form-label" for="userCreatePass">Contraseña inicial</label>
                <input id="userCreatePass" name="pass" type="text" class="form-control" required>
              </div>
              <div class="col-12">
                <label class="form-label" for="userCreateStationsList">Estaciones asignadas</label>
                <div id="userCreateStationsWrap" class="p-3 border rounded-3" style="background:rgba(20,26,38,.65);">
                  <div id="userCreateStationsStatus" class="small text-secondary">Cargando estaciones disponibles…</div>
                  <div id="userCreateStationsList" class="vstack gap-2 mt-2"></div>
                </div>
                <div class="form-text">Selecciona las estaciones que podrá consultar este usuario.</div>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="userCreateActive" name="is_active" checked>
                  <label class="form-check-label" for="userCreateActive">Activo</label>
                </div>
              </div>
            </form>
            <div id="userCreateMsg" class="small text-secondary mt-2"></div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" form="userCreateForm" class="btn btn-primary" id="userCreateSubmit">Guardar</button>
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
              <iframe id="docPreviewPdf" class="w-100 d-none"
                      style="height:60vh; border:1px solid var(--border)"></iframe>
              <div id="docPreviewMsg" class="small text-warning mt-2"></div>
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
</body>
</html>