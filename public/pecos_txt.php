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
  <title>PECOs / TXT — Control Regional</title>
  <?php require __DIR__ . '/includes/HEAD-CSS.php'; ?>
  <?php cr_print_js_globals(); ?>
  <style>
    .tool-wrap{position:relative}
    .tool-controls{position:sticky;top:0;z-index:20;background:var(--bs-body-bg);border-bottom:1px solid var(--bs-border-color);padding:.75rem .75rem .5rem .75rem;margin:-.75rem -.75rem .75rem -.75rem}
    .station-badges .form-check{margin-right:.5rem;margin-bottom:.5rem}
    .table thead th{white-space:nowrap}
    .table td, .table th{vertical-align:middle}
    .muted{color:var(--bs-secondary-color)}
    .w-min{width:1%}
    .nowrap{white-space:nowrap}
    /* Sticky for year view: Estación + Trabajador */
    .yr-sticky { position: sticky; left: 0; z-index: 2; background: var(--bs-body-bg); }
    .yr-sticky-2 { position: sticky; left: 10rem; z-index: 2; background: var(--bs-body-bg); }
    /* Switches / checkboxes better touch */
    .form-check-input[type="checkbox"]{ width: 2.2em; height: 1.2em; }
    .form-check-input:checked { background-color:#28a745; border-color:#28a745; }
    .form-check-input{ border:2px solid rgba(255,255,255,.25); }
    .oaci-label{ font-weight:600; letter-spacing:.5px; }
    .tab-pane .table-responsive{ max-height: 60vh; }
  </style>
</head>
<body>
<nav class="navbar border-bottom">
  <div class="container-fluid nav-3up">
    <div class="nav-left"><a class="navbar-brand" href="index.php">Control Regional</a></div>
    <div class="nav-center"><span class="navbar-text">PECOs / TXT</span></div>
    <div class="nav-right d-flex align-items-center gap-2">
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

<div class="container py-3 tool-wrap">
  <div class="card card-hero">
    <div class="card-body">
      <div class="tool-controls">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
          <div class="me-2"><strong>Estaciones</strong> <span class="text-secondary small">— filtrado por permisos del usuario</span></div>
          <div id="stationsWrap" class="station-badges d-flex flex-wrap align-items-center"></div>
        </div>
        <div class="d-grid controls-grid" style="grid-template-columns: auto auto 1fr; gap:.5rem">
          <div class="btn-group btn-group-sm" role="group" aria-label="Vista" id="modeSwitch">
            <button type="button" class="btn btn-primary" data-mode="persona">Persona</button>
            <button type="button" class="btn btn-outline-secondary" data-mode="anio">Año</button>
          </div>
          <div id="personaSelectWrap" class="d-flex align-items-center gap-2">
            <label for="selPersona" class="form-label mb-0 small muted">Persona</label>
            <select id="selPersona" class="form-select form-select-sm" style="min-width:260px"></select>
          </div>
          <div id="anioSelectWrap" class="d-flex align-items-center gap-2 d-none">
            <label for="selAnio" class="form-label mb-0 small muted">Año</label>
            <select id="selAnio" class="form-select form-select-sm" style="min-width:160px"></select>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <ul class="nav nav-tabs mt-3" id="mainTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-pecos" data-bs-toggle="tab" data-bs-target="#pane-pecos" type="button" role="tab">PECOs</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-txt" data-bs-toggle="tab" data-bs-target="#pane-txt" type="button" role="tab">TXT</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-vac" data-bs-toggle="tab" data-bs-target="#pane-vac" type="button" role="tab">Vacaciones</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-inc" data-bs-toggle="tab" data-bs-target="#pane-inc" type="button" role="tab">Incapacidades</button>
        </li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-pecos" role="tabpanel" aria-labelledby="tab-pecos">
          <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div><strong>PECOs</strong> <span id="personaHeadPecos" class="muted"></span></div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0" id="tblPecosPersona">
                  <thead><tr><th class="w-min">Año</th><th>PECO1</th><th>PECO2</th><th>PECO3</th><th>PECO4</th><th>PECO5</th><th>PECO6</th><th>PECO7</th><th>PECO8</th><th>PECO9</th><th>PECO10</th><th>PECO11</th><th>PECO12</th><th class="w-min">Editar</th></tr></thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div><strong>Por Año</strong> <span id="anioHeadP" class="muted"></span></div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0" id="tblPecosAnio">
                  <thead>
                    <tr>
                      <th class="w-min">Estación</th>
                      <th class="w-min">Trabajador</th>
                      <th>PECO1</th><th>PECO2</th><th>PECO3</th><th>PECO4</th><th>PECO5</th><th>PECO6</th><th>PECO7</th><th>PECO8</th><th>PECO9</th><th>PECO10</th><th>PECO11</th><th>PECO12</th>
                      <th class="w-min">Editar</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="pane-txt" role="tabpanel" aria-labelledby="tab-txt">
          <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div><strong>TXT (Persona)</strong> <span id="personaHeadTxt" class="muted"></span></div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0" id="tblTxtPersona">
                  <thead><tr><th class="w-min">Año</th><th>Jue. Santo</th><th>Vie. Santo</th><th>Día Madres</th><th>Día SENEAM/ATC</th><th>Día Muertos</th><th>Onomástico</th><th>Fecha Nac.</th><th class="w-min">Editar</th></tr></thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div><strong>TXT (Año)</strong> <span id="anioHeadT" class="muted"></span></div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0" id="tblTxtAnio">
                  <thead>
                    <tr>
                      <th class="w-min">Estación</th>
                      <th class="w-min">Trabajador</th>
                      <th>Jue. Santo</th><th>Vie. Santo</th><th>Día Madres</th><th>Día SENEAM/ATC</th><th>Día Muertos</th><th>Onomástico</th><th>Fecha Nac.</th>
                      <th class="w-min">Editar</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="pane-vac" role="tabpanel" aria-labelledby="tab-vac">
          <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div><strong>Vacaciones (Persona)</strong></div>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-12 col-lg-6">
                  <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0" id="tblVacPersona">
                      <thead><tr><th>Año</th><th>Tipo</th><th>Período</th><th>Inicia</th><th>Reanuda</th><th>Días</th><th>Resta</th></tr></thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
                <div class="col-12 col-lg-6">
                  <div class="p-3 border rounded">
                    <div class="fw-semibold mb-2">Año actual</div>
                    <div id="vacResumen"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div><strong>Vacaciones (Año)</strong> <span id="anioHeadV" class="muted"></span></div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0" id="tblVacAnio">
                  <thead>
                    <tr>
                      <th class="w-min">Estación</th>
                      <th class="w-min">Trabajador</th>
                      <th>VAC P1</th><th>VAC P2</th><th>Antig.</th><th>PR</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="pane-inc" role="tabpanel" aria-labelledby="tab-inc">
          <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div><strong>Incapacidades</strong></div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-striped mb-0" id="tblInc">
                  <thead><tr><th>Estación</th><th>Trabajador</th><th>Inicia</th><th>Termina</th><th>Días</th><th>UMF</th><th>Diagnóstico</th><th>Folio</th></tr></thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /tab-content -->

    </div>
  </div>
</div>

<!-- Modal edición rápida PECOS/TXT por año/persona -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title">Editar registro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="editForm" class="row g-2">
          <input type="hidden" id="edControl">
          <input type="hidden" id="edYear">
          <div class="col-12"><div class="fw-semibold">PECOs</div></div>
          <?php for ($i=1;$i<=12;$i++): ?>
          <div class="col-6 col-sm-4 col-md-3">
            <label class="form-label small">PECO<?=$i?></label>
            <input type="text" class="form-control form-control-sm" id="edP<?=$i?>" maxlength="8">
          </div>
          <?php endfor; ?>
          <div class="col-12 mt-2"><div class="fw-semibold">TXT</div></div>
          <div class="col-6 col-sm-4"><label class="form-label small">Jue. Santo</label><input type="text" class="form-control form-control-sm" id="edJS" maxlength="8"></div>
          <div class="col-6 col-sm-4"><label class="form-label small">Vie. Santo</label><input type="text" class="form-control form-control-sm" id="edVS" maxlength="8"></div>
          <div class="col-6 col-sm-4"><label class="form-label small">Día Madres</label><input type="text" class="form-control form-control-sm" id="edDM" maxlength="8"></div>
          <div class="col-6 col-sm-4"><label class="form-label small">Día SENEAM/ATC</label><input type="text" class="form-control form-control-sm" id="edDS" maxlength="8"></div>
          <div class="col-6 col-sm-4"><label class="form-label small">Día Muertos</label><input type="text" class="form-control form-control-sm" id="edMU" maxlength="8"></div>
          <div class="col-6 col-sm-4"><label class="form-label small">Onomástico</label><input type="text" class="form-control form-control-sm" id="edON" maxlength="8"></div>
        </form>
        <div class="small text-secondary mt-2">Formato sugerido DD/MM (ej. 29/03). Se guardan tal cual.</div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnSave" class="btn btn-primary">Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/Foot-js.php'; ?>
<script src="<?= htmlspecialchars(asset_version('/public/assets/vendor/jquery.checkbox.js')) ?>"></script>
<script src="<?= htmlspecialchars(asset_version('/public/assets/pecos_txt.js')) ?>"></script>
</body>
</html>
