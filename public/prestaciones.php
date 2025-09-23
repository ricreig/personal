<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap_public.php';
require_once dirname(__DIR__) . '/lib/paths.php';
cr_define_php_paths(); // BASE_URL, API_BASE

$u = auth_user();
if (!$u) {
  header('Location: ' . rtrim(BASE_URL, '/') . '/login.php?err=timeout');
  exit;
}
?><!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Prestaciones — Control Regional</title>
  <?php require __DIR__ . '/includes/HEAD-CSS.php'; ?>
  <?php cr_print_js_globals(); ?>
  <style>
    .toolbar-gap { row-gap: .5rem; }
    .oaci-chip {
      display:inline-flex; align-items:center; justify-content:center;
      padding:.35rem .6rem; border:1px solid rgba(255,255,255,.15);
      border-radius:.5rem; background:#141a26; font-weight:600; letter-spacing:.5px;
    }
    .tool-wrap{position:relative}
    .tool-controls{position:sticky;top:0;z-index:20;background:var(--bs-body-bg);border-bottom:1px solid var(--bs-border-color);padding:.75rem .75rem .5rem .75rem;margin:-.75rem -.75rem .75rem -.75rem}
    .muted{color:var(--bs-secondary-color)}
    .w-min{width:1%}
    .nowrap{white-space:nowrap}
    .card-table { min-height:240px; }
    .table td, .table th{vertical-align:middle}
    .table thead th{white-space:nowrap}
    .sticky-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    /* Sticky primeras DOS columnas en vistas por año */
    .tbl-sticky { border-collapse: separate; border-spacing: 0; }
    .tbl-sticky th, .tbl-sticky td { white-space: nowrap; }
    .tbl-sticky th:nth-child(1), .tbl-sticky td:nth-child(1) {
      position: sticky; left: 0; z-index: 2; background: var(--bs-body-bg);
      box-shadow: 1px 0 0 rgba(255,255,255,.08);
    }
    .tbl-sticky th:nth-child(2), .tbl-sticky td:nth-child(2) {
      position: sticky; left: 10rem; z-index: 2; background: var(--bs-body-bg);
      box-shadow: 1px 0 0 rgba(255,255,255,.08);
    }
    @media (max-width: 576px){
      .tbl-sticky th:nth-child(2), .tbl-sticky td:nth-child(2) { left: 8rem; }
    }

/* Switches nativos: compactos + borde cuando están apagados */
.form-switch .form-check-input {
  width: 1.7rem;
  height: 0.9rem;
  cursor: pointer;
  border: 1px solid rgba(255,255,255,.25);
}
.form-switch .form-check-input:checked {
  background-color: #1e7e34; /* verde Bootstrap-ish */
  border-color: #1e7e34;
}
.form-switch .form-check-input .all:checked {
  background-color: #ffc107; /* amarillo Bootstrap-ish */
  border-color: #0dcaf0;
}
.form-switch .form-check-input:focus { box-shadow: none; }

.switch-label {
  font-weight: 600;
  letter-spacing: .3px;
  display: inline-block;
  min-width: 4.5rem;
  text-align: center;
  margin-left: 2px;
}
.form-select {
    font-size: 0.7rem;
}
/* Distribución en varias columnas para no cascada */
    #oaciList {
      display: flex;
      flex-wrap: wrap;
      gap: .2rem .4rem;
    }
    #oaciList .form-check {
      flex: 1 1 calc(50% - .4rem);
    }
    #oaciList .form-check-label {
      font-size: .85rem;
    }
    .nav-tabs {
      border-bottom: 0;
      margin-bottom: 0;
    }
    .nav-tabs .nav-link {
      border: 1px solid var(--bs-border-color);
      border-bottom: none;
      border-radius: .75rem .75rem 0 0;
      margin-right: .25rem;
      background: var(--bs-body-bg);
      color: var(--bs-secondary-color);
    }
    .nav-tabs .nav-link.active {
      background: var(--bs-card-bg);
      color: var(--bs-body-color);
      font-weight: 600;
    }
    .tab-content {
      border: 1px solid var(--bs-border-color);
      border-top: none;
      border-radius: 0 0 .75rem .75rem;
      padding: 1rem;
      background: var(--bs-card-bg);
    }
    .tab-content .card {
      border: none;
      border-radius: 0;
      box-shadow: none;
    }
    .tab-content .card-header {
      border-radius: 0;
      border-bottom: 1px solid var(--bs-border-color);
      padding-bottom: .5rem;
    }
  </style>
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
      <?php if (function_exists('is_admin') && is_admin()): ?>
        <a class="btn btn-outline-primary btn-sm" href="../admin/usuarios.php">Admin</a>
        <a class="btn btn-outline-primary btn-sm" href="/public/diagnose.php">Test Panel</a>
      <?php endif; ?>
      <a class="btn btn-outline-primary btn-sm" href="/public/index.php">Inicio</a>
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
  <div class="card card-hero mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div>
          <h1 class="h4 mb-1">Personal</h1>
          <div class="text-secondary small">Sistema para Gestión de Capital Humano</div>
        </div>
      </div>
    <div class="d-flex align-items-center gap-2 flex-wrap toolbar-gap">
        <div class="btn-group me-2" role="group" aria-label="Vista" id="modeSwitch">
          <button type="button" class="btn btn-sm btn-primary" data-mode="persona">Persona</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-mode="anio">Año</button>
        </div>

        <div id="personaSelectWrap" class="me-2" style="min-width:320px">
          <select id="personaSelect" class="form-select" aria-label="Trabajador"></select>
        </div>
        <div id="anioPersonaWrap" class="me-2" style="min-width:160px">
          <select id="anioPersonaSelect" class="form-select" aria-label="Año (persona)"></select>
        </div>
        <div id="personaanioWrap" class="me-2 d-none" style="min-width:320px">
          <select id="personaanioSelect" class="form-select" aria-label="Trabajador (anio)" disabled></select>
        </div>
        <div id="anioSelectWrap" class="me-2 d-none" style="min-width:160px">
          <select id="anioSelect" class="form-select" aria-label="Año"></select>
        </div>
        <div class="dropdown me-2">
          <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="oaciDropdown" data-bs-toggle="dropdown" aria-expanded="false">Filtrar Estaciones</button>
             <div class="dropdown-menu p-3 dropdown-menu-end" aria-labelledby="oaciDropdown" style="min-width:280px;max-height:260px;overflow:auto">
                        <div class="form-check form-switch mb-2">
                          <input class="form-check-input" type="checkbox" role="switch" id="oaciAll">
                          <label class="form-check-label" for="oaciAll"><strong>&nbsp;Seleccionar todas</strong></label>
                          <div class="mt-2" id="oaciList"></div>
                        </div>
             </div>
        </div>
              <div id="oaciList"></div>
    </div>
   </div>
  </div>



      <!-- Tabs -->
      <ul class="nav nav-tabs mt-2" id="prestTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-pecos" data-bs-toggle="tab" data-bs-target="#pane-pecos" type="button" role="tab" aria-controls="pane-pecos" aria-selected="true">Días Economicos (PECO)</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-txt" data-bs-toggle="tab" data-bs-target="#pane-txt" type="button" role="tab" aria-controls="pane-txt" aria-selected="false">Días Acumulables (Tiempo x Tiempo)</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-vac" data-bs-toggle="tab" data-bs-target="#pane-vac" type="button" role="tab" aria-controls="pane-vac" aria-selected="false">Vacaciones, P. Recuperacion y Antigüedad</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-inc" data-bs-toggle="tab" data-bs-target="#pane-inc" type="button" role="tab" aria-controls="pane-inc" aria-selected="false">Licencias Medicas (Incapacidades)</button>
        </li>
      </ul>
      <div class="tab-content" id="prestTabsContent">
        <!-- PECOs -->
        <div class="tab-pane fade show active" id="pane-pecos" role="tabpanel" aria-labelledby="tab-pecos">
          <div id="pecosPersona">
            <div class="card card-table">
              <div class="card-header fw-semibold">Días Economicos (PECO) — Vista por Persona</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblPecosPersona">
                  <thead><tr><th>Año</th><th>D-01</th><th>D-02</th><th>D-03</th><th>D-04</th><th>D-05</th><th>D-06</th><th>D-07</th><th>D-08</th><th>D-09</th><th>D-10</th><th>D-11</th><th>D-12</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td colspan="15" class="text-secondary">Seleccione un trabajador…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div id="pecosAnio" class="d-none">
            <div class="card card-table">
              <div class="card-header fw-semibold">Días Economicos (PECO) — Vista por Año</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblPecosYear">
                  <thead><tr><th>Estación</th><th>Trabajador</th><th>D-01</th><th>D-02</th><th>D-03</th><th>D-04</th><th>D-05</th><th>D-06</th><th>D-07</th><th>D-08</th><th>D-09</th><th>D-10</th><th>D-11</th><th>D-12</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td class="text-secondary">Seleccione año…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- TXT -->
        <div class="tab-pane fade" id="pane-txt" role="tabpanel" aria-labelledby="tab-txt">
          <div id="txtPersona">
            <div class="card card-table">
              <div class="card-header fw-semibold">Días Acumulables — Vista por Persona</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblTxtPersona">
                  <thead><tr><th>Año</th><th>Jue. Santo</th><th>Vie. Santo</th><th>D. Madres</th><th>SENEAM/ATC</th><th>D. Muertos</th><th>Onomástico</th><th>Fecha Nac.</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td colspan="10" class="text-secondary">Seleccione un trabajador…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div id="txtAnio" class="d-none">
            <div class="card card-table">
              <div class="card-header fw-semibold">Días Acumulables — Vista por Año</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblTxtYear">
                  <thead><tr><th>Estación</th><th>Trabajador</th><th>Jue. Santo</th><th>Vie. Santo</th><th>D. Madres</th><th>SENEAM/ATC</th><th>D. Muertos</th><th>Onomástico</th><th>Fecha Nac.</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td class="text-secondary">Seleccione año…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Vacaciones -->
        <div class="tab-pane fade" id="pane-vac" role="tabpanel" aria-labelledby="tab-vac">
          <div id="vacPersona">
            <div class="card card-table">
              <div class="card-header fw-semibold">Vacaciones — Vista por Persona</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblVacPersona">
                  <thead><tr><th>Año</th><th>Antigüedad (derecho)</th><th>PR (derecho)</th><th>VAC 1 (restantes)</th><th>VAC 2 (restantes)</th><th>ANT usados</th><th>PR usados</th><th>Acciones</th></tr></thead>
                  <tbody><tr><td colspan="8" class="text-secondary">Seleccione un trabajador…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div id="vacAnio" class="d-none">
            <div class="card card-table">
              <div class="card-header fw-semibold">Vacaciones — Vista por Año</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblVacYear">
                  <thead><tr><th>Estación</th><th>No. Control</th><th>Nombre</th><th>Antigüedad (derecho)</th><th>PR (derecho)</th><th>VAC 1 (restantes)</th><th>VAC 2 (restantes)</th><th>ANT usados</th><th>PR usados</th><th>Acciones</th></tr></thead>
                  <tbody><tr><td colspan="10" class="text-secondary">Seleccione año…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Incapacidades -->
        <div class="tab-pane fade" id="pane-inc" role="tabpanel" aria-labelledby="tab-inc">
          <div id="incPersona">
            <div class="card card-table">
              <div class="card-header fw-semibold">Incapacidades — Vista por Persona</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblIncPersona">
                  <thead><tr><th>Año</th><th>Folio</th><th>Inicia</th><th>Termina</th><th>Días</th><th>UMF</th><th>Diag.</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td colspan="9" class="text-secondary">Seleccione un trabajador…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div id="incAnio" class="d-none">
            <div class="card card-table">
              <div class="card-header fw-semibold">Incapacidades — Vista por Año</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblIncYear">
                  <thead><tr><th>Estación</th><th>Trabajador</th><th>Folio</th><th>Inicia</th><th>Termina</th><th>Días</th><th>UMF</th><th>Diag.</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td class="text-secondary">Seleccione año…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /tab-content -->
                </div>
    </div>
  </div>
</div>

<!-- Modal edición genérica -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="editModalTitle">Editar registro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="editForm" class="row g-3"></form>
        <div id="editMsg" class="small text-secondary mt-2"></div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnSaveEdit">Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/Foot-js.php'; ?>
<script src="<?= htmlspecialchars(asset_version('assets/vendor/jquery.checkbox.js')) ?>"></script>
<script src="<?= htmlspecialchars(asset_version('assets/prestaciones.js')) ?>"></script>
</body>
</html>
