<?php
// public/includes/user_modal.php
declare(strict_types=1);
?>
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0">
        <div>
          <h5 class="modal-title" id="um-title">Ficha del trabajador</h5>
          <div class="small text-secondary" id="um-subtitle"></div>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
          <a id="um-edit-link" class="btn btn-sm btn-primary" href="#" target="_blank" rel="noopener">Editar</a>
          <button id="um-soft-delete" type="button" class="btn btn-sm btn-outline-danger">Eliminar (mover a cambios)</button>
          <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
      </div>

      <div class="modal-body pt-0">
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="umTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="um-gen-tab" data-bs-toggle="tab" data-bs-target="#um-gen" type="button" role="tab">Generales & Licencias</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="um-pecos-tab" data-bs-toggle="tab" data-bs-target="#um-pecos" type="button" role="tab">PECOs (histórico)</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="um-txt-tab" data-bs-toggle="tab" data-bs-target="#um-txt" type="button" role="tab">TXT (histórico)</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="um-vac-tab" data-bs-toggle="tab" data-bs-target="#um-vac" type="button" role="tab">Vacaciones</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="um-inc-tab" data-bs-toggle="tab" data-bs-target="#um-inc" type="button" role="tab">Incapacidades</button>
          </li>
        </ul>

        <div class="tab-content" id="umTabsContent">

          <!-- Generales -->
          <div class="tab-pane fade show active" id="um-gen" role="tabpanel" aria-labelledby="um-gen-tab">
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <div class="card">
                  <div class="card-header fw-semibold">Generales</div>
                  <div class="card-body">
                    <dl class="row mb-0">
                      <dt class="col-sm-4">Control</dt><dd class="col-sm-8" id="um-control">—</dd>
                      <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8" id="um-nombre">—</dd>
                      <dt class="col-sm-4">Estación</dt><dd class="col-sm-8" id="um-oaci">—</dd>
                      <dt class="col-sm-4">Área</dt><dd class="col-sm-8" id="um-espec">—</dd>
                      <dt class="col-sm-4">Nivel</dt><dd class="col-sm-8" id="um-nivel">—</dd>
                      <dt class="col-sm-4">Nombramiento</dt><dd class="col-sm-8" id="um-plaza">—</dd>
                      <dt class="col-sm-4">Puesto</dt><dd class="col-sm-8" id="um-puesto">—</dd>
                      <dt class="col-sm-4">CURP</dt><dd class="col-sm-8" id="um-curp">—</dd>
                      <dt class="col-sm-4">Nacimiento</dt><dd class="col-sm-8" id="um-nac">—</dd>
                      <dt class="col-sm-4">Antigüedad</dt><dd class="col-sm-8" id="um-ant">—</dd>
                      <dt class="col-sm-4">Email</dt><dd class="col-sm-8" id="um-email">—</dd>
                    </dl>
                  </div>
                </div>
              </div>
              <div class="col-12 col-lg-6">
                <div class="card">
                  <div class="card-header fw-semibold">Licencias</div>
                  <div class="card-body">
                    <div class="row g-2">
                      <div class="col-12"><strong id="um-lic-rtari-label">RTARI</strong> <span id="um-lic-rtari-vig" class="badge bg-secondary"></span></div>
                      <div class="col-12"><strong>Licencia 1:</strong> <span id="um-lic1-tipo"></span> <span id="um-lic1-vig" class="badge bg-secondary"></span></div>
                      <div class="col-12"><strong>Licencia 2:</strong> <span id="um-lic2-tipo"></span> <span id="um-lic2-vig" class="badge bg-secondary"></span></div>
                      <div class="col-12"><strong>Anexo:</strong> <span id="um-anexo" class="badge bg-secondary"></span></div>
                      <div class="col-12"><strong>Psicofísico:</strong> <span id="um-psico" class="badge bg-secondary"></span></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- PECOs -->
          <div class="tab-pane fade" id="um-pecos" role="tabpanel" aria-labelledby="um-pecos-tab">
            <div class="card">
              <div class="card-header fw-semibold">PECOs — Histórico</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-striped mb-0" id="um-pecos-table">
                    <thead>
                      <tr>
                        <th class="w-min">Año</th>
                        <?php for($i=1;$i<=12;$i++): ?>
                          <th>PECO<?=$i?></th>
                        <?php endfor; ?>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- TXT -->
          <div class="tab-pane fade" id="um-txt" role="tabpanel" aria-labelledby="um-txt-tab">
            <div class="card">
              <div class="card-header fw-semibold">TXT — Histórico</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-striped mb-0" id="um-txt-table">
                    <thead>
                      <tr>
                        <th class="w-min">Año</th>
                        <th>Jueves Santo</th><th>Viernes Santo</th><th>Día Madres</th><th>Día SENEAM/ATC</th><th>Día Muertos</th><th>Onomástico</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- Vacaciones -->
          <div class="tab-pane fade" id="um-vac" role="tabpanel" aria-labelledby="um-vac-tab">
            <div class="row g-3">
              <div class="col-12 col-xl-7">
                <div class="card h-100">
                  <div class="card-header fw-semibold">Histórico de Vacaciones</div>
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-sm table-striped mb-0" id="um-vac-table">
                        <thead>
                          <tr>
                            <th>Año</th><th>Tipo</th><th>Periodo</th><th>Inicia</th><th>Reanuda</th><th>Días</th><th>Obs</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12 col-xl-5">
                <div class="card h-100">
                  <div class="card-header fw-semibold">Resumen año actual</div>
                  <div class="card-body">
                    <div class="row g-2" id="um-vac-cards">
                      <!-- JS llenará: VAC1, VAC2, ANT, PR -->
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Incapacidades -->
          <div class="tab-pane fade" id="um-inc" role="tabpanel" aria-labelledby="um-inc-tab">
            <div class="card">
              <div class="card-header fw-semibold">Incapacidades</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-striped mb-0" id="um-inc-table">
                    <thead>
                      <tr>
                        <th>Inicia</th><th>Termina</th><th>Días</th><th>UMF</th><th>Diagnóstico</th><th>Folio</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

        </div> <!-- /tab-content -->
      </div>
    </div>
  </div>
</div>
