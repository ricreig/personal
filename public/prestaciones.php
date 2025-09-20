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

    /* Switches nativos: slightly larger + border when off */
    .form-switch .form-check-input {
      width: 3rem; height: 1.6rem;
      cursor: pointer;
      border: 2px solid rgba(255,255,255,.25);
    }
    .form-switch .form-check-input:checked {
      background-color: #1e7e34; /* verde Bootstrap-ish */
      border-color: #1e7e34;
    }
    .form-switch .form-check-input:focus { box-shadow: none; }

    .switch-label {
      font-weight: 600; letter-spacing:.3px;
      display:inline-block; min-width:4.5rem; text-align:center;
    }

    /* Tabs sobrios */
    .nav-tabs .nav-link { color: var(--bs-body-color); border-color: transparent; }
    .nav-tabs .nav-link.active {
      background: #141a26; border-color: rgba(255,255,255,.1);
    }
  </style>
</head>
<body>
<nav class="navbar border-bottom">
  <div class="container-fluid nav-3up">
    <div class="nav-left"><a class="navbar-brand" href="index.php">Control Regional</a></div>
    <div class="nav-center"><span class="navbar-text">Prestaciones</span></div>
    <div class="nav-right d-flex align-items-center justify-content-end gap-2">
      <?php if (function_exists('is_admin') && is_admin()): ?>
        <a class="btn btn-outline-primary btn-sm" href="../admin/usuarios.php">Admin</a>
        <a class="btn btn-outline-primary btn-sm" href="/public/diagnose.php">Diagnóstico</a>
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

<div class="container py-4 tool-wrap">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 toolbar-gap">
    <div>
      <h1 class="h4 mb-1">Prestaciones · Personal</h1>
      <div class="text-secondary small">Control de prestaciones disponibles y asignadas al personal adscrito.</div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="card card-hero mb-3">
    <div class="card-body">
      <div class="tool-controls">
        <!-- Estaciones -->
        <div class="mb-3">
          <label class="form-label fw-semibold mb-2 d-flex align-items-center gap-2">
            <span>Estaciones</span>
            <span class="text-secondary small">— filtrado por permisos del usuario</span>
          </label>

          <div id="stationsSingle" class="d-none">
            <span class="oaci-chip" id="singleOaci">—</span>
          </div>

          <div id="stationsMulti" class="d-none">
            <div class="d-flex flex-wrap align-items-center gap-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="oaciAll" checked>
                <label class="form-check-label" for="oaciAll">Seleccionar todo</label>
              </div>
              <div id="oaciList" class="d-flex flex-wrap gap-3"></div>
            </div>
            <div class="text-secondary small mt-2">Activa/desactiva estaciones. Por defecto todas quedan activas.</div>
          </div>
        </div>

        <hr class="my-3">

        <!-- Switch Persona / Año + Selectores -->
        <div class="row gy-2 align-items-center">
          <div class="col-12 col-lg-auto">
            <div class="btn-group" role="group" aria-label="Modo de vista" id="modeSwitch">
              <button type="button" class="btn btn-primary" data-mode="persona">Persona</button>
              <button type="button" class="btn btn-outline-secondary" data-mode="anio">Año</button>
            </div>
          </div>

          <div class="col-12 col-lg d-flex align-items-center gap-2" id="personaSelectWrap">
            <label for="personaSelect" class="form-label m-0">Trabajador</label>
            <select id="personaSelect" class="form-select" style="min-width:260px">
              <option value="">Seleccione…</option>
            </select>
          </div>

          <div class="col-12 col-lg d-flex align-items-center gap-2 d-none" id="anioSelectWrap">
            <label for="anioSelect" class="form-label m-0">Año</label>
            <select id="anioSelect" class="form-select" style="min-width:200px"></select>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <ul class="nav nav-tabs mt-3" id="prestTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-pecos" data-bs-toggle="tab" data-bs-target="#pane-pecos" type="button" role="tab" aria-controls="pane-pecos" aria-selected="true">PECOs</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-txt" data-bs-toggle="tab" data-bs-target="#pane-txt" type="button" role="tab" aria-controls="pane-txt" aria-selected="false">TXT</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-vac" data-bs-toggle="tab" data-bs-target="#pane-vac" type="button" role="tab" aria-controls="pane-vac" aria-selected="false">Vacaciones</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-inc" data-bs-toggle="tab" data-bs-target="#pane-inc" type="button" role="tab" aria-controls="pane-inc" aria-selected="false">Incapacidades</button>
        </li>
      </ul>
      <div class="tab-content" id="prestTabsContent">
        <!-- PECOs -->
        <div class="tab-pane fade show active" id="pane-pecos" role="tabpanel" aria-labelledby="tab-pecos">
          <div id="pecosPersona">
            <div class="card card-table mt-3">
              <div class="card-header fw-semibold">PECOs — Vista por Persona</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblPecosPersona">
                  <thead><tr><th>Año</th><th>PECO1</th><th>PECO2</th><th>PECO3</th><th>PECO4</th><th>PECO5</th><th>PECO6</th><th>PECO7</th><th>PECO8</th><th>PECO9</th><th>PECO10</th><th>PECO11</th><th>PECO12</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td colspan="15" class="text-secondary">Seleccione un trabajador…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div id="pecosAnio" class="d-none">
            <div class="card card-table mt-3">
              <div class="card-header fw-semibold">PECOs — Vista por Año</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle tbl-sticky" id="tblPecosYear">
                  <thead><tr><th>Estación</th><th>Trabajador</th><th>PECO1</th><th>PECO2</th><th>PECO3</th><th>PECO4</th><th>PECO5</th><th>PECO6</th><th>PECO7</th><th>PECO8</th><th>PECO9</th><th>PECO10</th><th>PECO11</th><th>PECO12</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td class="text-secondary">Seleccione año…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- TXT -->
        <div class="tab-pane fade" id="pane-txt" role="tabpanel" aria-labelledby="tab-txt">
          <div id="txtPersona">
            <div class="card card-table mt-3">
              <div class="card-header fw-semibold">TXT — Vista por Persona</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblTxtPersona">
                  <thead><tr><th>Año</th><th>Jue. Santo</th><th>Vie. Santo</th><th>Madres</th><th>SENEAM/ATC</th><th>Muertos</th><th>Onomástico</th><th>Fecha Nac.</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td colspan="10" class="text-secondary">Seleccione un trabajador…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div id="txtAnio" class="d-none">
            <div class="card card-table mt-3">
              <div class="card-header fw-semibold">TXT — Vista por Año</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle tbl-sticky" id="tblTxtYear">
                  <thead><tr><th>Estación</th><th>Trabajador</th><th>Jue. Santo</th><th>Vie. Santo</th><th>Madres</th><th>SENEAM/ATC</th><th>Muertos</th><th>Onomástico</th><th>Fecha Nac.</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td class="text-secondary">Seleccione año…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Vacaciones -->
        <div class="tab-pane fade" id="pane-vac" role="tabpanel" aria-labelledby="tab-vac">
          <div id="vacPersona">
            <div class="card card-table mt-3">
              <div class="card-header fw-semibold">Vacaciones — Vista por Persona</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle" id="tblVacPersona">
                  <thead><tr><th>Año</th><th>Antigüedad (días)</th><th>PR</th><th>VAC 1</th><th>VAC 2</th><th>ANT usados</th><th>PR usados</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td colspan="9" class="text-secondary">Seleccione un trabajador…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
          <div id="vacAnio" class="d-none">
            <div class="card card-table mt-3">
              <div class="card-header fw-semibold">Vacaciones — Vista por Año</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle tbl-sticky" id="tblVacYear">
                  <thead><tr><th>Estación</th><th>Trabajador</th><th>Antigüedad (días)</th><th>PR</th><th>VAC 1</th><th>VAC 2</th><th>ANT usados</th><th>PR usados</th><th>Editar</th><th>Eliminar</th></tr></thead>
                  <tbody><tr><td class="text-secondary">Seleccione año…</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Incapacidades -->
        <div class="tab-pane fade" id="pane-inc" role="tabpanel" aria-labelledby="tab-inc">
          <div id="incPersona">
            <div class="card card-table mt-3">
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
            <div class="card card-table mt-3">
              <div class="card-header fw-semibold">Incapacidades — Vista por Año</div>
              <div class="card-body sticky-wrap">
                <table class="table table-sm table-striped table-hover align-middle tbl-sticky" id="tblIncYear">
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
