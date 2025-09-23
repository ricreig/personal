const API=(typeof window!=='undefined'&&window.API_BASE)?window.API_BASE:'/api';
/* global $, DataTable, bootstrap */
(() => {
  // ================================
  // Base helpers
  // ================================
  const onReady = (fn) => {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn, { once: true });
  };
  const debounce = (fn, ms = 220) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  const API_BASE = (window.API_BASE || '/api/').replace(/\/+$/, '/');

  const fetchJSON = async (url, opts = {}) => {
    const r = await fetch(url, { credentials: 'include', ...opts });
    const ct = (r.headers.get('content-type') || '').toLowerCase();
    const isJSON = ct.includes('application/json');
    const body = isJSON ? await r.json() : await r.text();
    if (!r.ok) {
      const msg = (isJSON && body && (body.msg || body.error)) ? (body.msg || body.error) : `HTTP ${r.status}`;
      const err = new Error(msg); err.status = r.status; err.body = body; throw err;
    }
    return body;
  };

function injectGrowCSS(){
  if (document.getElementById('dt-grow-css')) return;
  const s = document.createElement('style');
  s.id = 'dt-grow-css';
  s.textContent = `
    /* Solo en pantallas chicas/medianas dejamos que "Nombre" crezca
       y el resto se quede en una línea */
    @media (max-width: 991.98px) {
      table.dataTable th.dt-shrink,
      table.dataTable td.dt-shrink { width:1%; white-space:nowrap; }
      table.dataTable th.dt-grow,
      table.dataTable td.dt-grow   { white-space:normal; }
    }

    /* En pantallas grandes, layout normal (que cada columna use su espacio) */
    @media (min-width: 992px) {
      table.dataTable th.dt-shrink,
      table.dataTable td.dt-shrink,
      table.dataTable th.dt-grow,
      table.dataTable td.dt-grow { width:auto; white-space:nowrap; }
    }

    /* Evita saltos raros dentro de badges y controles */
    .is-nowrap { white-space:nowrap; }
  `;
  document.head.appendChild(s);
}

  // Estado de filtros (se mandan al server)
  const ACTIVE_FILTERS = {
    estacion: [],   // OACI
    espec:   [],    // áreas
    tipoNom: 'all', // 'all' | 'base' | 'confianza'
  };


// ——— Helpers para el renderer del detalle ———
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function isEmptyHTML(html){
  const txt = String(html||'').replace(/<[^>]*>/g,'').replace(/\s+/g,'').trim();
  return txt === '';
}
function rowLine(label, htmlValue){
  if (htmlValue == null) return '';
  const h = String(htmlValue);
  if (isEmptyHTML(h)) return '';
  return `<tr><th class="pe-3 text-secondary small fw-normal">${esc(label)}</th><td>${h}</td></tr>`;
}
function renderRTARI(rtari, rtari_vig){
  const raw = String(rtari_vig?.raw || rtari_vig?.display || '').trim();
  if (/^01\/01\/3000$/.test(raw)) {
    return `<span class="is-nowrap d-inline-flex align-items-center gap-2">
      <strong>${esc(rtari||'RTARI')}</strong>
      <span data-order="99991231" class="badge bg-success" title="PERMANENTE" data-bs-toggle="tooltip">PERMANENTE</span>
    </span>`;
  }
  return renderTipoVig(rtari, rtari_vig);
}
  // ================================
  // Fechas y colores
  // ================================
  function colorFor(days) {
    if (days == null) return 'bg-secondary';
    if (days < 0)     return 'bg-bad';
    if (days <= 30)   return 'bg-warn';
    return 'bg-ok';
  }
  function parseDMY(str) {
    const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(str || '').trim());
    if (!m) return null;
    const d = Number(m[1]), mm = Number(m[2]) - 1, y = Number(m[3]);
    const dt = new Date(y, mm, d);
    return Number.isFinite(dt.getTime()) ? dt : null;
  }
  function diffYearsMonths(from, to = new Date()) {
    if (!from) return null;
    let y = to.getFullYear() - from.getFullYear();
    let m = to.getMonth() - from.getMonth();
    if (to.getDate() < from.getDate()) m -= 1;
    if (m < 0) { y -= 1; m += 12; }
    return { years: y, months: m };
  }
  function daysUntil(dateStrDMY) {
    const d = parseDMY(dateStrDMY);
    if (!d) return null;
    const ms = d.setHours(0,0,0,0) - new Date().setHours(0,0,0,0);
    return Math.round(ms / 86400000);
  }

  // ================================
  // Estación numérica → OACI
  // ================================
  const OACI_MAP = { 1:'MMSD', 2:'MMLP', 3:'MMSL', 4:'MMLT', 5:'MMTJ', 6:'MMML', 7:'MMPE', 8:'MMHO', 9:'MMGM' };
  function toOACI(v){
    if (v == null) return '';
    const s = String(v).trim().toUpperCase();
    if (/^[A-Z]{4}$/.test(s)) return s;
    const n = Number(s);
    return OACI_MAP[n] || s;
  }

  // ================================
  // Modal de Documentos
  // ================================
  window.openDocumentsModal = async function (control, nombre) {
    const m = document.getElementById('docModal');
    if (!m) { alert('Modal de documentos no está en esta página.'); return; }

    const title = m.querySelector('#docModalTitle') || m.querySelector('.modal-title');
    if (title) title.textContent = `Documentos — ${nombre || ''} · #${control}`;

    const tiles   = m.querySelector('#docTiles');
    const prev    = m.querySelector('#docPreview');
    const prevImg = m.querySelector('#docPreviewImg');
    const prevPdf = m.querySelector('#docPreviewPdf');
    const prevInfo= m.querySelector('#docPreviewInfo');
    const prevMsg = m.querySelector('#docPreviewMsg');

    // limpia preview
    if (prev) prev.classList.add('d-none');
    if (prevImg) { prevImg.src=''; prevImg.classList.add('d-none'); }
    if (prevPdf) { prevPdf.src=''; prevPdf.classList.add('d-none'); }
    if (prevInfo) prevInfo.textContent = '';
    if (prevMsg)  prevMsg.textContent  = '';

    // carga estado
    let data = {};
    try {
      const res = await fetchJSON(`${API_BASE}doc_exists.php?control=${encodeURIComponent(control)}`);
      data = (res && res.data) ? res.data : {};
    } catch(e) {
      data = {};
      const msg = (e && e.status === 401) ? 'Sesión no autorizada para ver documentos.' :
                  (e && e.status === 403) ? 'No tiene permisos para ver documentos de este trabajador.' :
                  `Error al cargar documentos (${e.message||'desconocido'})`;
      if (prevMsg) { prevMsg.textContent = msg; }
    }

    const define = [
      { key:'lic1',  label:'Licencia 1' },
      { key:'lic2',  label:'Licencia 2' },
      { key:'med',   label:'Cert. Médico' },
      { key:'rtari', label:'RTARI' },
      { key:'misc',  label:'Varios' }
    ];

    if (tiles) {
      tiles.setAttribute('data-control', control);
      tiles.innerHTML = define.map(d=>{
        const exists = !!data[d.key];
        return `
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="p-3 rounded-3 h-100" style="background:#141a26;border:1px solid rgba(255,255,255,.08)">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <strong>${d.label}</strong>
              <span class="badge ${exists?'bg-success':'bg-secondary'}">${exists?'Disponible':'No existe'}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-primary" data-doc-act="view" data-doc-type="${d.key}" ${exists?'':'disabled'}>Ver</button>
              <button class="btn btn-sm btn-outline-light" data-doc-act="update" data-doc-type="${d.key}">Actualizar</button>
              <button class="btn btn-sm btn-outline-danger" data-doc-act="delete" data-doc-type="${d.key}" ${exists?'':'disabled'}>Eliminar</button>
            </div>
          </div>
        </div>`;
      }).join('');

      tiles.querySelectorAll('[data-doc-act]').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
          const act  = btn.getAttribute('data-doc-act');
          const type = btn.getAttribute('data-doc-type');

          if (act === 'view') {
            const info = data[type];
            if (!info || !info.url) return;

            if (prev) prev.classList.remove('d-none');
            if (prevInfo) prevInfo.textContent = info.filename || info.url;

            if ((info.mime||'').includes('pdf') || /\.pdf$/i.test(info.url)) {
              if (prevPdf) { prevPdf.src = info.url; prevPdf.classList.remove('d-none'); }
            } else if ((info.mime||'').startsWith('image/') || /\.(png|jpe?g|webp|gif)$/i.test(info.url)) {
              if (prevImg) { prevImg.src = info.url; prevImg.classList.remove('d-none'); }
            } else {
              window.open(info.url, '_blank', 'noopener');
            }
            return;
          }

          if (act === 'update') {
            const inp = document.getElementById('docFileInput');
            if (!inp) return;
            inp.value = '';
            inp.onchange = async ()=>{
              if (!inp.files || !inp.files[0]) return;
              const fd = new FormData();
              fd.append('control', control);
              fd.append('type', type);
              fd.append('file', inp.files[0]);
              try {
                const up = await fetchJSON(`${API_BASE}doc_upload.php`, { method:'POST', body:fd });
                if (!up || up.ok!==true) throw new Error((up && up.msg) ? up.msg : 'Error al subir');
                const res2 = await fetchJSON(`${API_BASE}doc_exists.php?control=${encodeURIComponent(control)}`);
                data = (res2 && res2.data) ? res2.data : {};
                openDocumentsModal(control, nombre);
              } catch(e){
                let msg = e.message || 'Error al subir';
                if (e.status === 401) msg = 'Sesión expirada o no autorizada para subir.';
                if (e.status === 403) msg = 'No tiene permisos para subir documentos a este trabajador.';
                alert(msg);
              }
            };
            inp.click();
            return;
          }

          if (act === 'delete') {
            if (!confirm('¿Eliminar documento?')) return;
            const fd = new FormData();
            fd.append('control', control);
            fd.append('type', type);
            try {
              const del = await fetchJSON(`${API_BASE}doc_delete.php`, { method:'POST', body:fd });
              if (!del || del.ok!==true) throw new Error((del && del.msg) ? del.msg : 'No se pudo eliminar');
              const res3 = await fetchJSON(`${API_BASE}doc_exists.php?control=${encodeURIComponent(control)}`);
              data = (res3 && res3.data) ? res3.data : {};
              openDocumentsModal(control, nombre);
            } catch(e){
              let msg = e.message || 'No se pudo eliminar';
              if (e.status === 401) msg = 'Sesión expirada o no autorizada para eliminar.';
              if (e.status === 403) msg = 'No tiene permisos para eliminar documentos de este trabajador.';
              alert(msg);
            }
          }
        });
      });
    }

    bootstrap.Modal.getOrCreateInstance(m).show();
  };

  // ================================
  // Renders / riesgo / concatenados
  // ================================
  function renderVigenciaBadge(v){
    if (!v) return '';
    const txt  = v.display || '';
    const ord  = v.order   || '';
    const days = (typeof v.days === 'number') ? v.days : daysUntil(txt);
    const cls  = colorFor(days);
    const tip  = (typeof days === 'number')
      ? (days >= 0 ? `${days} días restantes` : `${-days} días vencidos`)
      : '';
    return `<span data-order="${ord}" class="badge ${cls}" title="${tip}" data-bs-toggle="tooltip">${txt}</span>`;
  }
  function renderTipoVig(tipo, vig){
    const t = (tipo || '').toString().trim();
    const b = renderVigenciaBadge(vig);
    if (!t && !b) return '';
    return `
      <span class="is-nowrap d-inline-flex align-items-center gap-2">
        ${t ? `<strong>${t}</strong>` : ''}
        ${b || ''}
      </span>`;
  }
  function minRiskDays(row){
    const pool = [];
    const push = (obj) => {
      if (!obj) return;
      if (typeof obj.days === 'number') pool.push(obj.days);
      else if (obj.display) {
        const dd = daysUntil(obj.display);
        if (typeof dd === 'number') pool.push(dd);
      }
    };
    push(row.vigencia1);
    push(row.vigencia2);
    push(row.examen_vig1);
    push(row.examen_vig2);
    push(row.rtari_vig);
    return pool.length ? Math.min(...pool) : 999999;
  }
  function renderFechaConLinea(v, etiquetaSmall){
    const empty = '<small class="text-secondary fst-italic">Sin fecha ingresada</small>';
    if (!v) return empty;
    const raw = (typeof v === 'string') ? v : (v.display || '');
    if (!raw) return empty;
    const d = parseDMY(raw);
    if (!d) return empty;
    const yy = diffYearsMonths(d)?.years ?? null;
    const nice = new Intl.DateTimeFormat('es-MX', { day:'2-digit', month:'2-digit', year:'numeric' }).format(d);
    const leyenda = (yy!=null) ? `${yy} ${etiquetaSmall}` : '';
    return `${nice}${leyenda ? `<br><small class="text-secondary">${leyenda}</small>` : ''}`;
  }
  const renderFechaEdad  = (v)=> renderFechaConLinea(v, 'años de edad');
  const renderFechaAntig = (v)=> renderFechaConLinea(v, 'antigüedad');


function detailsRendererFactory(view){
  return function ( api, rowIdx /*, columns */ ) {
    const row = api.row(rowIdx).data() || {};
    const ctrl = String(row.control || '').trim();
    const nom  = String(row.nombres || '').trim();

    // Bloques con solo lo que tenga contenido
    const basicos = [
      ['No Ctrl', esc(row.control)],
      ['Estación', esc(toOACI(row.estacion))],
      ['Nivel', esc(row.nivel)],
      ['Área', esc(row.espec)],
      ['Nombramiento', esc(row.plaza)],
      ['Puesto', esc(row.puesto)],
      ['CURP', esc(row.curp)],
      ['Email', esc(row.email)],
    ];

    // Vencimientos / licencias
const lic1  = renderTipoVig(row.tipo1, row.vigencia1);
const lic2  = renderTipoVig(row.tipo2, row.vigencia2);

// ✅ Psicofísico = examen2
const psi   = renderTipoVig(row.examen2, row.examen_vig2);

// ✅ Anexo = examen1
const anexo = renderTipoVig(row.examen1, row.examen_vig1);

const rtari = renderRTARI(row.rtari, row.rtari_vig);

    // Armar tabla solo con filas NO vacías
    let html = '<table class="table table-sm table-borderless mb-2">';
    basicos.forEach(([k,v]) => { html += rowLine(k, v); });
    html += rowLine('Licencia 1', lic1);
    html += rowLine('Licencia 2', lic2);
    html += rowLine('Psicofísico', psi);
    html += rowLine('Anexo', anexo);
    html += rowLine('RTARI', rtari);
    html += '</table>';

    // Acciones (Docs / Editar)
    const ctrlEsc = esc(ctrl), nomEsc = esc(nom);
    const v = encodeURIComponent(view || 'lic');
    html += `
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-sm btn-outline-light" onclick="openDocumentsModal('${ctrlEsc}','${nomEsc}')">Docs</button>
        <a class="btn btn-sm btn-primary" href="edit_persona.php?view=${v}&ctrl=${encodeURIComponent(ctrl)}">Editar</a>
      </div>`;

    // Si TODO quedó vacío, devuelve false para que DataTables no muestre el child
    return isEmptyHTML(html.replace(/<table[^>]*>.*?<\/table>/s,'')) ? false : html;
  };
}


  // ================================
  // Columnas por vista (con prioridades y grow)
  // ================================
  function columnsFor(view){
    if (view === 'lic') {
      return [
        // 0) riesgo (oculta)
        { data: null, title: 'Riesgo', visible: false, searchable: false,
          render: (_, __, row) => minRiskDays(row)
        },
        // 1) Control (shrink)
        { data: 'control', title: 'No Ctrl', className:'dt-shrink text-start is-nowrap', orderable:false, responsivePriority: 4 },
        // 2) Estación (shrink)
        { data: 'estacion', title: 'Estación', render: toOACI, className:'dt-shrink text-start is-nowrap', responsivePriority: 3 },
        // 3) Nivel (shrink)
        { data: 'nivel', title: 'Nivel', className:'dt-shrink text-start is-nowrap', orderable:false, responsivePriority: 9 },
        // 4) Área (shrink)
        { data: 'espec', title: 'Área', className:'dt-shrink text-start is-nowrap', responsivePriority: 10 },
        // 5) Nombre (GROW)
        { data: 'nombres', title: 'Nombre del Trabajador', className:'dt-grow text-start', responsivePriority: 1 },
        // 6) Licencia 1
        { data: null, title: 'Licencia 1', className:'dt-shrink text-start is-nowrap', orderable:false, responsivePriority: 20,
          render: (_, __, row) => renderTipoVig(row.tipo1, row.vigencia1)
        },
        // 7) Licencia 2
        { data: null, title: 'Licencia 2', className:'dt-shrink text-start is-nowrap', orderable:false, responsivePriority: 21,
          render: (_, __, row) => renderTipoVig(row.tipo2, row.vigencia2)
        },
        // 8) Psicofísico  ← examen2
{ data: null, title: 'Psicofísico', className:'dt-shrink text-start is-nowrap', orderable:false, responsivePriority: 22,
  render: (_, __, row) => renderTipoVig(row.examen2, row.examen_vig2)
},

// 9) Anexo  ← examen1
{ data: null, title: 'Anexo', className:'dt-shrink text-start is-nowrap', orderable:false, responsivePriority: 23,
  render: (_, __, row) => renderTipoVig(row.examen1, row.examen_vig1)
},
        // 10) RTARI
        { data: null, title: 'RTARI', className:'dt-shrink text-start is-nowrap', orderable:false, responsivePriority: 24,
          render: (_, __, row) => {
            const raw = String(row?.rtari_vig?.raw || row?.rtari_vig?.display || '').trim();
            if (/^01\/01\/3000$/.test(raw)) {
              return `<span class="is-nowrap d-inline-flex align-items-center gap-2">
                <strong>${row.rtari || 'RTARI'}</strong>
                <span data-order="99991231" class="badge bg-success" title="PERMANENTE" data-bs-toggle="tooltip">PERMANENTE</span>
              </span>`;
            }
            return renderTipoVig(row.rtari, row.rtari_vig);
          }
        },
        // 11) Acciones (quiero que quede visible si se puede)
        { data: null, title: 'Acciones', orderable:false, searchable:false,
          className:'dt-shrink text-start is-nowrap', responsivePriority: 2,
          render: (_, __, row) => {
            const ctrl = String(row.control || '').trim();
            const nom  = String(row.nombres || '').trim();
            return `
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-light" onclick="openDocumentsModal('${ctrl.replace(/"/g,'&quot;')}', '${nom.replace(/"/g,'&quot;')}')">Docs</button>
                <a class="btn btn-primary" href="edit_persona.php?ctrl=${encodeURIComponent(ctrl)}">Editar</a>
              </div>`;
          }
        }
      ];
    }

    // ----- Vista DATOS
    return [
      { data: 'control',  title: 'No Ctrl',     className:'dt-shrink text-start is-nowrap', orderable:false, responsivePriority: 4 },
      { data: 'estacion', title: 'Estación',    render: toOACI, className:'dt-shrink text-start is-nowrap', responsivePriority: 3 },
      { data: 'nombres',  title: 'Nombre',      className:'dt-grow text-start', responsivePriority: 1 },
      { data: 'nivel',    title: 'Nivel',       className:'dt-shrink text-start is-nowrap', responsivePriority: 9 },
      { data: 'espec',    title: 'Área',        className:'dt-shrink text-start is-nowrap', responsivePriority: 10 },
      { data: 'curp',     title: 'CURP',        className:'dt-shrink text-start is-nowrap', responsivePriority: 50 },
      { data: 'plaza',    title: 'Nombramiento',className:'dt-shrink text-start is-nowrap', responsivePriority: 20 },
      { data: 'puesto',   title: 'Puesto',      className:'dt-shrink text-start is-nowrap small', responsivePriority: 30 },
      { data: 'fecha_nacimiento', title: 'Nacimiento', className:'dt-shrink text-start is-nowrap', responsivePriority: 60,
        render: v => renderFechaEdad(v) },
      { data: 'ant',      title: 'Ingreso',     className:'dt-shrink text-start is-nowrap js-ant', responsivePriority: 70,
        render: v => renderFechaAntig(v),
        createdCell: (td, v) => { td.dataset.order = (v && v.order) || ''; } },
      { data: 'email',    title: 'Email',       className:'dt-shrink text-start is-nowrap', responsivePriority: 40 },
      { data: null,       title: 'Acciones',    orderable:false, searchable:false,
        className:'dt-shrink text-start is-nowrap', responsivePriority: 2,
        render: (_, __, row) => {
          const ctrl = String(row.control || '').trim();
          const nom  = String(row.nombres || '').trim();
          return `
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-light" onclick="openDocumentsModal('${ctrl.replace(/"/g,'&quot;')}', '${nom.replace(/"/g,'&quot;')}')">Docs</button>
              <a class="btn btn-primary" href="edit_persona.php?ctrl=${encodeURIComponent(ctrl)}">Editar</a>
            </div>`;
        } }
    ];
  }

  // ================================
  // THEAD builder (evita desfases)
  // ================================
  function ensureTableHeader($table, cols) {
    let $thead = $table.find('thead');
    if (!$thead.length) $thead = $('<thead/>').appendTo($table);
    const $tr = $('<tr/>');
    cols.forEach(c => $tr.append($('<th/>').text(c.title || '')));
    $thead.empty().append($tr);
    if (!$table.find('tbody').length) $table.append('<tbody/>');
  }

  // ================================
  // Distincts (para filtros)
  // ================================
  async function fetchDistinct(field) {
    try {
      const res = await fetchJSON(`${API_BASE}trabajadores_distinct.php?field=${encodeURIComponent(field)}`);
      const arr = Array.isArray(res?.data) ? res.data : [];
      return arr.map(s => String(s||'').trim()).filter(Boolean);
    } catch {
      return [];
    }
  }
  async function fetchDistinctOACI() {
    try {
      const res = await fetchJSON(`${API_BASE}trabajadores_distinct.php?field=estacion`);
      const raw = Array.isArray(res?.data) ? res.data : [];
      const norm = raw.map(v => toOACI(v)).filter(s => /^[A-Z]{4}$/.test(s));
      return Array.from(new Set(norm)).sort((a,b)=>a.localeCompare(b));
    } catch {
      return [];
    }
  }

  // ================================
  // Toolbar (switch + filtros)
  // ================================
  function wireToolbar(view){
    // Switch de vistas
    document.querySelectorAll('#viewToggle [data-view]').forEach(btn => {
      btn.classList.toggle('btn-primary', btn.dataset.view === view);
      btn.classList.toggle('btn-outline-secondary', btn.dataset.view !== view);
      btn.onclick = (e) => {
        e.preventDefault();
        buildTable(btn.dataset.view);
      };
    });

    // Filtros
    const be = document.getElementById('btnFiltrarEst');
    const ba = document.getElementById('btnFiltrarArea');
    const bn = document.getElementById('btnFiltrarNom');
    if (be) be.onclick = () => openFilterDialog('estacion');
    if (ba) ba.onclick = () => openFilterDialog('espec');
    if (bn) bn.onclick = () => openFilterDialog('plaza');
  }

  // ================================
  // Modal de filtros
  // ================================
  async function openFilterDialog(type){
    const box     = document.getElementById('filterContainer');
    const titleEl = document.getElementById('filterModalLabel');
    const modalEl = document.getElementById('filterModal');
    if (!box || !titleEl || !modalEl) return;

    if (type === 'estacion') {
      titleEl.textContent = 'Filtrar por Estación';
      const items = await fetchDistinctOACI();
      box.innerHTML = items.length
        ? `<div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-2">
            ${items.map(o => `
              <div class="col">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="chkEst_${o}" value="${o}"
                         ${ACTIVE_FILTERS.estacion.includes(o) ? 'checked' : ''}>
                  <label class="form-check-label" for="chkEst_${o}">${o}</label>
                </div>
              </div>`).join('')}
           </div>`
        : '<div class="text-secondary">No hay estaciones disponibles.</div>';

      document.getElementById('filterClearBtn').onclick = () => {
        ACTIVE_FILTERS.estacion = [];
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        const t = $('#tabla').DataTable(); if (t) t.ajax.reload();
      };
      document.getElementById('filterApplyBtn').onclick = () => {
        const sel = Array.from(box.querySelectorAll('input:checked')).map(i => i.value);
        ACTIVE_FILTERS.estacion = sel;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        const t = $('#tabla').DataTable(); if (t) t.ajax.reload();
      };

      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }

    if (type === 'espec') {
      titleEl.textContent = 'Filtrar por Área';
      const items = await fetchDistinct('espec');
      box.innerHTML = items.length
        ? `<div class="row row-cols-2 row-cols-sm-3 g-2">
            ${items.map(o=>`
              <div class="col">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="chkEsp_${o}" value="${o}"
                         ${ACTIVE_FILTERS.espec.includes(o)?'checked':''}>
                  <label class="form-check-label" for="chkEsp_${o}">${o}</label>
                </div>
              </div>`).join('')}
           </div>`
        : '<div class="text-secondary">No hay valores para listar ahora.</div>';

      document.getElementById('filterClearBtn').onclick = () => {
        ACTIVE_FILTERS.espec = [];
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        const t = $('#tabla').DataTable(); if (t) t.ajax.reload();
      };
      document.getElementById('filterApplyBtn').onclick = () => {
        const sel = Array.from(box.querySelectorAll('input:checked')).map(i=>i.value);
        ACTIVE_FILTERS.espec = sel;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        const t = $('#tabla').DataTable(); if (t) t.ajax.reload();
      };

      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }

    if (type === 'plaza') {
      titleEl.textContent = 'Filtrar Nombramiento (Base / Confianza)';
      box.innerHTML = `
        <div class="d-flex flex-column gap-2">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="tipoNom" id="tnAll" value="all" ${ACTIVE_FILTERS.tipoNom==='all'?'checked':''}>
            <label class="form-check-label" for="tnAll">Todos</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="tipoNom" id="tnBase" value="base" ${ACTIVE_FILTERS.tipoNom==='base'?'checked':''}>
            <label class="form-check-label" for="tnBase">Personal de Base</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="tipoNom" id="tnConf" value="confianza" ${ACTIVE_FILTERS.tipoNom==='confianza'?'checked':''}>
            <label class="form-check-label" for="tnConf">Personal de Confianza</label>
          </div>
        </div>`;

      document.getElementById('filterClearBtn').onclick = () => {
        ACTIVE_FILTERS.tipoNom = 'all';
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        const t = $('#tabla').DataTable(); if (t) t.ajax.reload();
      };
      document.getElementById('filterApplyBtn').onclick = () => {
        const val = box.querySelector('input[name="tipoNom"]:checked')?.value || 'all';
        ACTIVE_FILTERS.tipoNom = val;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        const t = $('#tabla').DataTable(); if (t) t.ajax.reload();
      };

      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  // ================================
  // Tooltips
  // ================================
  function initTooltips(scope = document) {
    const els = [].slice.call(scope.querySelectorAll('[data-bs-toggle="tooltip"]'));
    els.forEach(el => bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body' }));
  }

  // ================================
  // Renderer: DETALLE (child-row)
  //  - Incluye botones Docs/Editar SOLO si la col. Acciones está oculta
  // ================================
  function renderDetails(row, columns){
    const isAccHidden = Array.isArray(columns)
      ? columns.some(c => (c.title || '').trim() === 'Acciones' && c.hidden)
      : true;

    const oaci = toOACI(row.estacion);
    const lic1 = renderTipoVig(row.tipo1, row.vigencia1);
    const lic2 = renderTipoVig(row.tipo2, row.vigencia2);
    const psi  = renderTipoVig(row.examen1, row.examen_vig1);
    const anex = renderTipoVig(row.examen2, row.examen_vig2);
    let rtari  = '';
    const rawRtari = String(row?.rtari_vig?.raw || row?.rtari_vig?.display || '').trim();
    if (/^01\/01\/3000$/.test(rawRtari)) {
      rtari = `<span class="is-nowrap d-inline-flex align-items-center gap-2">
                 <strong>${row.rtari || 'RTARI'}</strong>
                 <span data-order="99991231" class="badge bg-success" title="PERMANENTE" data-bs-toggle="tooltip">PERMANENTE</span>
               </span>`;
    } else {
      rtari = renderTipoVig(row.rtari, row.rtari_vig);
    }

    const fechaNac = renderFechaEdad(row.fecha_nacimiento);
    const antig    = renderFechaAntig(row.ant);

    const acciones = `
      <div class="d-flex flex-wrap gap-2 mt-2">
        <button class="btn btn-sm btn-outline-light" onclick="openDocumentsModal('${String(row.control||'').replace(/"/g,'&quot;')}', '${String(row.nombres||'').replace(/"/g,'&quot;')}')">Docs</button>
        <a class="btn btn-sm btn-primary" href="edit_persona.php?ctrl=${encodeURIComponent(String(row.control||''))}">Editar</a>
      </div>`;

    return `
      <div class="dt-details p-2 text-start">
        <div class="row g-2">
          <div class="col-sm-6 col-md-4"><small class="text-secondary">Control:</small><br><strong>${row.control||''}</strong></div>
          <div class="col-sm-6 col-md-4"><small class="text-secondary">Nombre:</small><br>${row.nombres||''}</div>
          <div class="col-sm-6 col-md-4"><small class="text-secondary">Estación:</small><br>${oaci||''}</div>

          <div class="col-sm-6 col-md-4"><small class="text-secondary">Área:</small><br>${row.espec||''}</div>
          <div class="col-sm-6 col-md-4"><small class="text-secondary">Nivel:</small><br>${row.nivel||''}</div>
          <div class="col-sm-6 col-md-4"><small class="text-secondary">Nombramiento:</small><br>${row.plaza||''}</div>

          <div class="col-sm-6 col-md-4"><small class="text-secondary">Nacimiento:</small><br>${fechaNac}</div>
          <div class="col-sm-6 col-md-4"><small class="text-secondary">Ingreso:</small><br>${antig}</div>
          <div class="col-sm-6 col-md-4"><small class="text-secondary">Email:</small><br>${row.email||''}</div>

          <div class="col-12"><hr class="my-2"></div>

          <div class="col-sm-6 col-md-4"><small class="text-secondary">Licencia 1:</small><br>${lic1||''}</div>
          <div class="col-sm-6 col-md-4"><small class="text-secondary">Licencia 2:</small><br>${lic2||''}</div>
          <div class="col-sm-6 col-md-4"><small class="text-secondary">RTARI:</small><br>${rtari||''}</div>

          ${!isEmptyHTML(psi)   ? `<div class="col-sm-6 col-md-4"><small class="text-secondary">Psicofísico:</small><br>${psi}</div>`   : ''}

			${!isEmptyHTML(anexo) ? `<div class="col-sm-6 col-md-4"><small class="text-secondary">Anexo:</small><br>${anexo}</div>` : ''}

          ${isAccHidden ? `<div class="col-12">${acciones}</div>` : ''}
        </div>
      </div>`;
  }

  // ================================
  // Core: construir DataTable
  // ================================
  function buildTable(view) {
    injectGrowCSS();

    const $table = $('#tabla');
    const cols   = columnsFor(view);

    wireToolbar(view);
    ensureTableHeader($table, cols);

    if ($.fn.dataTable.isDataTable($table)) {
      $table.DataTable().destroy();
      $table.empty();
      ensureTableHeader($table, cols);
    }

    const dt = $table.DataTable({
  processing: true,
  serverSide: true,
  ajax: {
    url: `${API_BASE}trabajadores_list.php`,
    type: 'POST',
    data: d => {
      d.view = view;
      if (view === 'lic') d.onlyWithLic = 1;
      d.filters = ACTIVE_FILTERS;
    }
  },
  columns: cols,
  order: (view === 'lic') ? [[0, 'asc']] : [[2, 'asc']], // en "datos" ordenar por Nombre
  pageLength: 50,
  lengthMenu: [10, 25, 50, 100],
  autoWidth: true,

  // ✅ Responsive con renderer que oculta vacíos
  responsive: {
    details: {
      type: 'inline',
      target: 'tr',
      renderer: detailsRendererFactory(view)
    }
  },

  // ✅ estas opciones estaban fuera del objeto por un "},"
  scrollX: true,
  scrollCollapse: true,

  layout: {
    topStart: null,
    topEnd: { pageLength: { menu: [10, 25, 50, 100] } },
    bottomStart: 'info',
    bottomEnd: 'paging'
  },
  pagingType: 'full_numbers',
  language: {
    emptyTable: 'Sin datos',
    zeroRecords: 'Sin resultados',
    info: 'Mostrando _START_–_END_ de _TOTAL_',
    infoEmpty: 'Mostrando 0–0 de 0',
    lengthMenu: '_MENU_ por página',
    paginate: { first: '«', previous: '‹', next: '›', last: '»' }
  },

  // (Opcional) Si ya filtras en backend con onlyWithLic, puedes eliminar este
  // rowCallback para que no “esconda” nada extra.
  rowCallback: function (row, data) {
    if (view === 'lic') {
      const hasL1 = (String(data.licencia1 || '').trim() !== '');
      const hasL2 = (String(data.licencia2 || '').trim() !== '');
      if (!hasL1 && !hasL2) row.style.display = 'none';
    }
  },

  initComplete: function () {
    initTooltips($table[0]);
  }
});

    // Buttons (Columnas/Excel/PDF)
    const btns = new DataTable.Buttons(dt, {
      buttons: [
        { extend: 'colvis',     text: 'Columnas' },
        { extend: 'excelHtml5', text: 'Excel', title: 'Personal', exportOptions: { columns: ':visible' } },
        { extend: 'pdfHtml5',   text: 'PDF',   title: 'Personal', orientation: 'landscape', pageSize: 'A4',
          exportOptions: { columns: ':visible' } }
      ]
    });
    const slot = document.getElementById('dtButtonsSlot');
    if (slot) {
      slot.innerHTML = '';
      $(slot).append(btns.container());
    }

    // Enlaces rápidos a botones
    $('#accion-columnas').off('click').on('click', (e)=>{ e.preventDefault(); dt.button(0).trigger(); });
    $('#accion-excel').off('click').on('click',    (e)=>{ e.preventDefault(); dt.button(1).trigger(); });
    $('#accion-pdf').off('click').on('click',      (e)=>{ e.preventDefault(); dt.button(2).trigger(); });

    // Buscador superior
    const $q = $('#busquedaGlobal');
    $q.off('.dt').on('input.dt', debounce(() => dt.search($q.val()).draw(), 200));

    // Ajustes/Tooltips al dibujar
    dt.on('init.dt draw.dt', () => {
      dt.columns.adjust();
      initTooltips($table[0]);
    });
    $(window).on('resize.dt', debounce(() => dt.columns.adjust(), 150));

    return dt;
  }

  // ================================
  // Arranque
  // ================================
  onReady(() => {
    buildTable('lic'); // vista default
  });
})();