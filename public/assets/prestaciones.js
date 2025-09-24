/* global bootstrap */
const API_BASE = (window.API_BASE || '/api/').replace(/\/+$/, '') + '/';

(function () {
  const storageKey = 'prestacionesMode';
  const state = {
    init: null,
    mode: localStorage.getItem(storageKey) || 'persona',
    personaControl: '',
    personaYear: new Date().getFullYear(),
    year: new Date().getFullYear(),
  };

  const VAC_TYPE_LABELS = {
    VAC: 'Vacaciones',
    PR: 'Recuperación (PR)',
    ANT: 'Antigüedad',
  };

  const el = {
    error: document.getElementById('prestError'),
    modeBtns: Array.from(document.querySelectorAll('#modeSwitch [data-mode]')),
    personaWrap: document.getElementById('personaSelectWrap'),
    personaSelect: document.getElementById('personaSelect'),
    personaSearch: document.getElementById('personaSearch'),
    personaOptions: document.getElementById('personaOptions'),
    personaClear: document.querySelector('#personaSelectWrap .persona-clear'),
    personaYearWrap: document.getElementById('anioPersonaWrap'),
    personaYearSelect: document.getElementById('anioPersonaSelect'),
    yearWrap: document.getElementById('anioSelectWrap'),
    yearpersonaWrap: document.getElementById('personaanioWrap'),
    yearpersonaSelect: document.getElementById('personaanioSelect'),
    yearSelect: document.getElementById('anioSelect'),
    oaciAll: document.getElementById('oaciAll'),
    oaciList: document.getElementById('oaciList'),
    pecosPersona: document.getElementById('pecosPersona'),
    pecosYear: document.getElementById('pecosAnio'),
    txtPersona: document.getElementById('txtPersona'),
    txtYear: document.getElementById('txtAnio'),
    vacPersona: document.getElementById('vacPersona'),
    vacYear: document.getElementById('vacAnio'),
    incPersona: document.getElementById('incPersona'),
    incYear: document.getElementById('incAnio'),
    vacSummaryWrap: document.getElementById('vacPersonaSummaryWrap'),
    tables: {
      pecosPersona: document.getElementById('tblPecosPersona'),
      pecosYear: document.getElementById('tblPecosYear'),
      txtPersona: document.getElementById('tblTxtPersona'),
      txtYear: document.getElementById('tblTxtYear'),
      vacPersona: document.getElementById('tblVacPersona'),
      vacYear: document.getElementById('tblVacYear'),
      incPersona: document.getElementById('tblIncPersona'),
      incYear: document.getElementById('tblIncYear'),
    },
    vacPersonaSummaryMeta: document.getElementById('vacPersonaSummaryMeta'),
    editModal: document.getElementById('editModal'),
    editModalTitle: document.getElementById('editModalTitle'),
    editForm: document.getElementById('editForm'),
    editMsg: document.getElementById('editMsg'),
    editSaveBtn: document.getElementById('btnSaveEdit'),
  };

  const editState = {
    detail: null,
    vac: {
      detail: null,
      records: [],
      activeTab: 'form',
      selectedId: null,
      elements: null,
    },
  };

  function formatControl(value) {
    const str = String(value || '');
    return str.padStart(4, '0');
  }

  function setError(msg) {
    if (!el.error) return;
    if (!msg) {
      el.error.classList.add('d-none');
      el.error.textContent = '';
      return;
    }
    el.error.textContent = msg;
    el.error.classList.remove('d-none');
  }

  function selectedStations() {
    if (!state.init || !Array.isArray(state.init.stations)) return [];
    const switches = Array.from(document.querySelectorAll('.oaci-switch'));
    if (!switches.length) return [];
    if (el.oaciAll && el.oaciAll.checked) {
      return state.init.stations.slice();
    }
    const active = switches.filter((sw) => sw.checked).map((sw) => sw.getAttribute('data-oaci'));
    return active.length ? active : state.init.stations.slice();
  }

  function personaLabel(persona) {
    if (!persona) return '';
    const control = formatControl(persona.control);
    const oaci = String(persona.oaci || '').trim().toUpperCase();
    const name = persona.nombres || '';
    return `${oaci ? `${oaci} · ` : ''}${control}${name ? ` · ${name}` : ''}`;
  }

  function findPersona(control) {
    if (!state.init || !Array.isArray(state.init.personas)) return null;
    const ctrl = String(control || '');
    return state.init.personas.find((p) => String(p.control) === ctrl) || null;
  }

  function updatePersonaSearchInput(control) {
    if (!el.personaSearch) return;
    const wrap = el.personaWrap ? el.personaWrap.querySelector('.persona-search-input') : null;
    const persona = findPersona(control);
    if (persona) {
      el.personaSearch.value = personaLabel(persona);
      if (wrap) wrap.classList.add('has-value');
    } else {
      el.personaSearch.value = '';
      if (wrap) wrap.classList.remove('has-value');
    }
  }

  function setPersonaControl(control, opts = {}) {
    const { updateSearch = true, silent = false } = opts;
    const normalized = control ? String(control) : '';
    const prev = state.personaControl;
    state.personaControl = normalized;
    if (el.personaSelect) {
      el.personaSelect.value = normalized;
    }
    if (updateSearch) {
      updatePersonaSearchInput(normalized);
    }
    if (!silent && normalized !== prev) {
      refreshActive();
    }
  }

  function resolvePersonaControlFromInput(rawValue) {
    const value = String(rawValue || '').trim();
    if (!value) return '';
    if (el.personaOptions) {
      const options = Array.from(el.personaOptions.options || []);
      const direct = options.find((opt) => opt.value === value);
      if (direct) {
        return String(direct.dataset.control || direct.value || '');
      }
    }
    const normalized = value.replace(/\s+/g, '').toUpperCase();
    const personas = (state.init && Array.isArray(state.init.personas)) ? state.init.personas : [];
    const match = personas.find((persona) => {
      const label = personaLabel(persona).replace(/\s+/g, '').toUpperCase();
      const ctrl = formatControl(persona.control);
      return label === normalized || ctrl === normalized || String(persona.control) === value;
    });
    return match ? String(match.control) : '';
  }

  function refreshPersonaOptions({ preserveSelection = true } = {}) {
    if (!el.personaOptions) return;
    const stations = selectedStations().map((s) => String(s || '').trim().toUpperCase());
    const personas = (state.init && Array.isArray(state.init.personas)) ? state.init.personas : [];
    const filtered = stations.length
      ? personas.filter((p) => stations.includes(String(p.oaci || '').trim().toUpperCase()))
      : personas;
    el.personaOptions.innerHTML = filtered
      .map((p) => `<option value="${personaLabel(p)}" data-control="${p.control}"></option>`)
      .join('');
    if (!preserveSelection || !filtered.some((p) => String(p.control) === state.personaControl)) {
      setPersonaControl('', { updateSearch: true, silent: true });
    } else if (state.personaControl) {
      updatePersonaSearchInput(state.personaControl);
    }
  }

  function toggleModeElements() {
    const personaMode = state.mode === 'persona';

    if (el.personaWrap) {
      el.personaWrap.classList.toggle('d-none', !personaMode);
    }
    if (el.personaSelect) {
      el.personaSelect.toggleAttribute('disabled', !personaMode);
    }
    if (el.personaSearch) {
      el.personaSearch.toggleAttribute('disabled', !personaMode);
    }
    if (el.personaClear) {
      el.personaClear.toggleAttribute('disabled', !personaMode);
    }

    if (el.personaYearWrap) {
      el.personaYearWrap.classList.add('d-none');
    }
    if (el.personaYearSelect) {
      el.personaYearSelect.setAttribute('disabled', 'disabled');
    }

    if (el.yearWrap) el.yearWrap.classList.toggle('d-none', personaMode);
    if (el.yearpersonaWrap) el.yearpersonaWrap.classList.add('d-none');
    if (el.yearpersonaSelect) el.yearpersonaSelect.setAttribute('disabled', 'disabled');

    if (el.pecosPersona) el.pecosPersona.classList.toggle('d-none', !personaMode);
    if (el.pecosYear) el.pecosYear.classList.toggle('d-none', personaMode);
    if (el.txtPersona) el.txtPersona.classList.toggle('d-none', !personaMode);
    if (el.txtYear) el.txtYear.classList.toggle('d-none', personaMode);
    if (el.vacPersona) el.vacPersona.classList.toggle('d-none', !personaMode);
    if (el.vacYear) el.vacYear.classList.toggle('d-none', personaMode);
    if (el.vacSummaryWrap) el.vacSummaryWrap.classList.toggle('d-none', !personaMode);
    if (el.incPersona) el.incPersona.classList.toggle('d-none', !personaMode);
    if (el.incYear) el.incYear.classList.toggle('d-none', personaMode);

    el.modeBtns.forEach((btn) => {
      const mode = btn.getAttribute('data-mode');
      btn.classList.toggle('btn-primary', mode === state.mode);
      btn.classList.toggle('btn-outline-secondary', mode !== state.mode);
    });
  }


  function setMode(mode) {
    if (mode !== 'persona' && mode !== 'anio') return;
    state.mode = mode;
    localStorage.setItem(storageKey, mode);
    toggleModeElements();
    refreshActive();
  }

  function activeTab() {
    const active = document.querySelector('#prestTabs .nav-link.active');
    return active ? active.id.replace('tab-', '') : 'pecos';
  }

  function stationsParam() {
    const stations = selectedStations();
    return stations.length ? stations.join(',') : '';
  }

  async function apiFetch(endpoint, params) {
    const url = new URL(API_BASE + endpoint, window.location.origin);
    Object.entries(params || {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, value);
      }
    });
    const resp = await fetch(url.toString(), {
      credentials: 'same-origin',
      cache: 'no-store',
    });
    if (!resp.ok) {
      throw new Error(`HTTP ${resp.status}`);
    }
    const data = await resp.json();
    if (data && data.ok === false) {
      throw new Error(data.error || 'Solicitud rechazada');
    }
    return data;
  }

  function clearTable(table, cols, message) {
    if (!table || !table.tBodies.length) return;
    const row = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = cols;
    td.className = 'text-secondary';
    td.textContent = message;
    row.appendChild(td);
    table.tBodies[0].innerHTML = '';
    table.tBodies[0].appendChild(row);
  }

  function renderPecosPersona(data) {
    const table = el.tables.pecosPersona;
    if (!table) return;
    const rows = data.rows || [];
    const tbody = table.tBodies[0];
    tbody.innerHTML = '';
    if (!rows.length) {
      clearTable(table, 15, 'Sin datos…');
      return;
    }
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${row.year}</td>
        ${Array.from({ length: 12 }, (_, i) => `<td class="text-center">${row['dia' + (i + 1)] ?? '0'}</td>`).join('')}
        <td class="nowrap"><button class="btn btn-sm btn-outline-primary" data-prest-action="edit" data-prest-type="pecos" data-prest-scope="persona" data-prest-control="${state.personaControl}" data-prest-year="${row.year}">Editar</button></td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-danger" data-prest-action="delete" data-prest-type="pecos" data-prest-scope="persona" data-prest-control="${state.personaControl}" data-prest-year="${row.year}">Eliminar</button></td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderPecosYear(data) {
    const table = el.tables.pecosYear;
    if (!table) return;
    const rows = data.rows || [];
    const tbody = table.tBodies[0];
    tbody.innerHTML = '';
    if (!rows.length) {
      clearTable(table, 15, 'Sin datos…');
      return;
    }
    const targetYear = data.year ?? state.year;
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="nowrap">${row.oaci || ''}</td>
        <td class="nowrap">${formatControl(row.control)} · ${row.nombres || ''}</td>
        ${Array.from({ length: 12 }, (_, i) => `<td class="text-center">${row['dia' + (i + 1)] ?? '0'}</td>`).join('')}
        <td class="nowrap"><button class="btn btn-sm btn-outline-primary" data-prest-action="edit" data-prest-type="pecos" data-prest-scope="anio" data-prest-control="${row.control}" data-prest-year="${targetYear}">Editar</button></td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-danger" data-prest-action="delete" data-prest-type="pecos" data-prest-scope="anio" data-prest-control="${row.control}" data-prest-year="${targetYear}">Eliminar</button></td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderTxtPersona(data) {
    const table = el.tables.txtPersona;
    if (!table) return;
    const rows = data.rows || [];
    const tbody = table.tBodies[0];
    tbody.innerHTML = '';
    if (!rows.length) {
      clearTable(table, 10, 'Sin datos…');
      return;
    }
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${row.year}</td>
        <td class="text-center">${row.js ?? '0'}</td>
        <td class="text-center">${row.vs ?? '0'}</td>
        <td class="text-center">${row.dm ?? '0'}</td>
        <td class="text-center">${row.ds ?? '0'}</td>
        <td class="text-center">${row.muert ?? '0'}</td>
        <td class="text-center">${row.ono ?? '0'}</td>
        <td>${row.fnac || ''}</td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-primary" data-prest-action="edit" data-prest-type="txt" data-prest-scope="persona" data-prest-control="${state.personaControl}" data-prest-year="${row.year}">Editar</button></td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-danger" data-prest-action="delete" data-prest-type="txt" data-prest-scope="persona" data-prest-control="${state.personaControl}" data-prest-year="${row.year}">Eliminar</button></td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderTxtYear(data) {
    const table = el.tables.txtYear;
    if (!table) return;
    const rows = data.rows || [];
    const tbody = table.tBodies[0];
    tbody.innerHTML = '';
    if (!rows.length) {
      clearTable(table, 11, 'Sin datos…');
      return;
    }
    const targetYear = data.year ?? state.year;
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="nowrap">${row.oaci || ''}</td>
        <td class="nowrap">${formatControl(row.control)} · ${row.nombres || ''}</td>
        <td class="text-center">${row.js ?? '0'}</td>
        <td class="text-center">${row.vs ?? '0'}</td>
        <td class="text-center">${row.dm ?? '0'}</td>
        <td class="text-center">${row.ds ?? '0'}</td>
        <td class="text-center">${row.muert ?? '0'}</td>
        <td class="text-center">${row.ono ?? '0'}</td>
        <td>${row.fnac || ''}</td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-primary" data-prest-action="edit" data-prest-type="txt" data-prest-scope="anio" data-prest-control="${row.control}" data-prest-year="${targetYear}">Editar</button></td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-danger" data-prest-action="delete" data-prest-type="txt" data-prest-scope="anio" data-prest-control="${row.control}" data-prest-year="${targetYear}">Eliminar</button></td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderFlags(flags) {
    if (!Array.isArray(flags) || !flags.length) return '';
    return flags
      .map((flag) => {
        const type = flag === 'danger' ? 'danger' : 'warning';
        const label = flag === 'danger' ? 'Riesgo' : 'Pendiente';
        return `<span class="badge bg-${type} text-dark me-1">${label}</span>`;
      })
      .join('');
  }

  function splitVacLeft(value) {
    const total = Number(value ?? 0);
    const safe = Number.isFinite(total) ? Math.max(0, total) : 0;
    const vac1 = Math.min(10, safe);
    const vac2 = Math.min(10, Math.max(0, safe - 10));
    return { vac1, vac2 };
  }

  function renderActionCell(scope, type, row) {
    const controlValue = row.control ?? row.control_fmt ?? '';
    const control = String(controlValue ?? '');
    const attrs = `data-prest-type="${type}" data-prest-scope="${scope}" data-prest-control="${control}"`;
    const yearValue = row.year ?? '';
    const yearAttr = yearValue !== '' ? ` data-prest-year="${yearValue}"` : '';
    const flags = renderFlags(row.flags);
    if (type === 'vac') {
      return `
        <div class="d-inline-flex align-items-center gap-2 flex-wrap">
          ${flags ? `<span class="text-nowrap">${flags}</span>` : ''}
          <div class="vac-action-pill">
            <button type="button" class="btn btn-add" data-prest-action="add" ${attrs}${yearAttr}>Agregar</button>
            <button type="button" class="btn btn-edit" data-prest-action="edit" ${attrs}${yearAttr}>Editar</button>
          </div>
        </div>
      `;
    }
    return `
      <div class="d-inline-flex align-items-center gap-2 flex-wrap">
        ${flags ? `<span class="text-nowrap">${flags}</span>` : ''}
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-primary" data-prest-action="edit" ${attrs}${yearAttr}>Editar</button>
          <button type="button" class="btn btn-outline-danger" data-prest-action="delete" ${attrs}${yearAttr}>Eliminar</button>
        </div>
      </div>
    `;
  }

  function renderVacPersona(data) {
    const table = el.tables.vacPersona;
    if (!table) return;
    const tbody = table.tBodies[0];
    tbody.innerHTML = '';

    const rows = Array.isArray(data?.summaries) && data.summaries.length
      ? data.summaries
      : (data && data.summary ? [data.summary] : []);
    if (!rows.length) {
      const msg = state.personaControl ? 'Sin datos…' : 'Seleccione un trabajador…';
      clearTable(table, 8, msg);
      return;
    }

    rows.forEach((row) => {
      const { vac1, vac2 } = splitVacLeft(row.dias_left);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="text-center">${row.year}</td>
        <td class="text-center">${row.ant_asig}</td>
        <td class="text-center">${row.pr_asig}</td>
        <td class="text-center">${vac1}</td>
        <td class="text-center">${vac2}</td>
        <td class="text-center">${row.ant_usados}</td>
        <td class="text-center">${row.pr_usados}</td>
        <td class="nowrap">${renderActionCell('persona', 'vac', row)}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderVacYear(data) {
    const table = el.tables.vacYear;
    if (!table) return;
    const rows = data.rows || [];
    const tbody = table.tBodies[0];
    tbody.innerHTML = '';
    if (!rows.length) {
      clearTable(table, 10, 'Sin datos…');
      return;
    }
    rows.forEach((row) => {
      const { vac1, vac2 } = splitVacLeft(row.dias_left);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="nowrap">${row.estacion || ''}</td>
        <td class="text-center">${row.control_fmt || formatControl(row.control)}</td>
        <td>${row.nombres || ''}</td>
        <td class="text-center">${row.ant_asig}</td>
        <td class="text-center">${row.pr_asig}</td>
        <td class="text-center">${vac1}</td>
        <td class="text-center">${vac2}</td>
        <td class="text-center">${row.ant_usados}</td>
        <td class="text-center">${row.pr_usados}</td>
        <td class="nowrap">${renderActionCell('anio', 'vac', row)}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function handleActionClick(event) {
    const btn = event.target.closest('[data-prest-action]');
    if (!btn) return;
    event.preventDefault();
    const detail = {
      action: btn.getAttribute('data-prest-action') || '',
      type: btn.getAttribute('data-prest-type') || '',
      scope: btn.getAttribute('data-prest-scope') || '',
      control: btn.getAttribute('data-prest-control') || '',
      year: btn.getAttribute('data-prest-year') || '',
      id: btn.getAttribute('data-prest-id') || '',
      button: btn,
      row: btn.closest('tr') || null,
    };
    document.dispatchEvent(new CustomEvent('prestaciones:action', { detail }));
    if (!btn.hasAttribute('data-prest-silent')) {
      console.info('Acción prestaciones', detail);
    }
  }

  function ensureModal() {
    if (!el.editModal || typeof bootstrap === 'undefined') return null;
    return bootstrap.Modal.getOrCreateInstance(el.editModal);
  }

  function setModalTitle(title) {
    if (el.editModalTitle) {
      el.editModalTitle.textContent = title || 'Editar registro';
    }
  }

  function setModalMessage(message, tone) {
    if (!el.editMsg) return;
    el.editMsg.textContent = message || '';
    el.editMsg.className = 'small mt-2';
    if (message) {
      const toneClass = tone === 'error' ? 'text-danger' : tone === 'success' ? 'text-success' : 'text-secondary';
      el.editMsg.classList.add(toneClass);
    } else {
      el.editMsg.classList.add('text-secondary');
    }
  }

  function resetEditForm() {
    if (el.editForm) {
      el.editForm.className = 'row g-3';
      el.editForm.innerHTML = '';
    }
    setModalMessage('');
    if (el.editSaveBtn) {
      el.editSaveBtn.disabled = true;
    }
    editState.vac = {
      detail: null,
      records: [],
      activeTab: 'form',
      selectedId: null,
      elements: null,
    };
  }

  function createInputField(field) {
    const {
      name,
      label,
      type = 'text',
      value = '',
      col = 'col-12',
      attrs = {},
      options = [],
    } = field || {};
    if (!name) return null;
    if (type === 'hidden') {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      Object.entries(attrs).forEach(([k, v]) => input.setAttribute(k, v));
      return input;
    }
    const wrap = document.createElement('div');
    wrap.className = col;
    const labelEl = document.createElement('label');
    labelEl.className = 'form-label';
    labelEl.textContent = label || name;
    let input;
    if (type === 'select') {
      input = document.createElement('select');
      input.className = 'form-select';
      input.name = name;
      options.forEach((opt) => {
        const option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.label;
        if (String(opt.value) === String(value)) {
          option.selected = true;
        }
        input.appendChild(option);
      });
    } else if (type === 'textarea') {
      input = document.createElement('textarea');
      input.className = 'form-control';
      input.name = name;
      input.value = value;
      input.rows = attrs.rows ? Number(attrs.rows) : 2;
    } else {
      input = document.createElement('input');
      input.className = 'form-control';
      input.type = type;
      input.name = name;
      input.value = value;
    }
    Object.entries(attrs).forEach(([k, v]) => {
      if (k === 'rows' && type === 'textarea') return;
      if (v !== undefined && v !== null && v !== '') {
        input.setAttribute(k, v);
      }
    });
    wrap.appendChild(labelEl);
    wrap.appendChild(input);
    return wrap;
  }

  function populateEditForm(fields, target) {
    if (!el.editForm) return;
    const container = target || el.editForm;
    if (!target) {
      el.editForm.innerHTML = '';
    } else {
      container.innerHTML = '';
    }
    fields.forEach((field) => {
      const node = createInputField(field);
      if (!node) return;
      if (node.tagName === 'INPUT' && node.type === 'hidden') {
        const existing = Array.from(el.editForm.querySelectorAll(`input[type="hidden"][name="${field.name}"]`));
        existing.forEach((inp) => inp.remove());
        el.editForm.appendChild(node);
      } else {
        container.appendChild(node);
      }
    });
    if (typeof window.applyDateMask === 'function') {
      window.applyDateMask(container);
    }
  }

  function getPersonaName(control) {
    if (!state.init || !Array.isArray(state.init.personas)) return '';
    const ctrl = String(control || '');
    const persona = state.init.personas.find((p) => String(p.control) === ctrl);
    return persona ? persona.nombres || '' : '';
  }

  function formatYearTitle(type, year) {
    if (!year) return type;
    return `${type} — ${year}`;
  }

  function toDateMask(value) {
    const str = String(value || '').trim();
    if (!str) return '';
    const isoMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str);
    if (isoMatch) {
      return `${isoMatch[3]}/${isoMatch[2]}/${isoMatch[1]}`;
    }
    if (/^(\d{2})\/(\d{2})\/(\d{4})$/.test(str)) {
      return str;
    }
    const digits = str.replace(/\D+/g, '').slice(0, 8);
    if (digits.length === 8) {
      return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
    }
    if (digits.length === 6) {
      return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/20${digits.slice(4)}`;
    }
    return str;
  }

  function toDMY(value) {
    const str = String(value || '').trim();
    if (!str) return '';
    const iso = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str);
    if (iso) {
      return `${iso[3]}/${iso[2]}/${iso[1]}`;
    }
    return str;
  }

  async function openPecosEdit(detail) {
    if (!detail.control || !detail.year) {
      setModalMessage('Faltan datos del registro.', 'error');
      ensureModal()?.show();
      return;
    }
    setModalTitle(formatYearTitle('Editar PECO', detail.year));
    setModalMessage('Cargando registro…', 'info');
    ensureModal()?.show();
    try {
      const data = await apiFetch('pecos_get.php', { control: detail.control, year: detail.year });
      const fields = [
        { type: 'hidden', name: 'control', value: detail.control },
        { type: 'hidden', name: 'year', value: detail.year },
      ];
      for (let i = 1; i <= 12; i += 1) {
        const key = `dia${i}`;
        fields.push({
          name: key,
          label: `D-${String(i).padStart(2, '0')}`,
          type: 'number',
          value: data ? data[key] ?? '' : '',
          col: 'col-6 col-sm-4 col-md-3 col-lg-2',
          attrs: { min: '0', step: '1', inputmode: 'numeric' },
        });
      }
      populateEditForm(fields);
      setModalMessage(`Control ${formatControl(detail.control)} · ${getPersonaName(detail.control)}`, 'info');
      if (el.editSaveBtn) el.editSaveBtn.disabled = false;
    } catch (err) {
      console.error(err);
      populateEditForm([]);
      setModalMessage(err.message || 'No se pudo cargar el registro.', 'error');
      if (el.editSaveBtn) el.editSaveBtn.disabled = true;
    }
  }

  async function openTxtEdit(detail) {
    if (!detail.control || !detail.year) {
      setModalMessage('Faltan datos del registro.', 'error');
      ensureModal()?.show();
      return;
    }
    setModalTitle(formatYearTitle('Editar Tiempo x Tiempo', detail.year));
    setModalMessage('Cargando registro…', 'info');
    ensureModal()?.show();
    try {
      const data = await apiFetch('txt_get.php', { control: detail.control, year: detail.year });
      const fields = [
        { type: 'hidden', name: 'control', value: detail.control },
        { type: 'hidden', name: 'year', value: detail.year },
        { name: 'js', label: 'Jueves Santo', type: 'number', value: data?.js ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
        { name: 'vs', label: 'Viernes Santo', type: 'number', value: data?.vs ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
        { name: 'dm', label: 'Día de las Madres', type: 'number', value: data?.dm ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
        { name: 'ds', label: 'SENEAM / ATC', type: 'number', value: data?.ds ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
        { name: 'muert', label: 'Día de Muertos', type: 'number', value: data?.muert ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
        { name: 'ono', label: 'Onomástico', type: 'number', value: data?.ono ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
      ];
      populateEditForm(fields);
      setModalMessage(`Control ${formatControl(detail.control)} · ${getPersonaName(detail.control)}`, 'info');
      if (el.editSaveBtn) el.editSaveBtn.disabled = false;
    } catch (err) {
      console.error(err);
      populateEditForm([]);
      setModalMessage(err.message || 'No se pudo cargar el registro.', 'error');
      if (el.editSaveBtn) el.editSaveBtn.disabled = true;
    }
  }

  function buildVacModalSkeleton(detail) {
    if (!el.editForm) return;
    el.editForm.className = 'd-flex flex-column gap-3';
    el.editForm.innerHTML = `
      <div>
        <ul class="nav nav-pills gap-2" id="vacEditTabs" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link" data-vac-tab="form" type="button">Registrar</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" data-vac-tab="records" type="button">Movimientos guardados</button></li>
        </ul>
      </div>
      <div class="tab-content" id="vacEditContent">
        <div class="tab-pane" id="vacEditPaneForm" role="tabpanel">
          <div class="row g-3" id="vacFormFields"></div>
        </div>
        <div class="tab-pane" id="vacEditPaneRecords" role="tabpanel"></div>
      </div>
    `;
    const tabs = {
      form: el.editForm.querySelector('[data-vac-tab="form"]'),
      records: el.editForm.querySelector('[data-vac-tab="records"]'),
    };
    const panes = {
      form: el.editForm.querySelector('#vacEditPaneForm'),
      records: el.editForm.querySelector('#vacEditPaneRecords'),
    };
    editState.vac.elements = {
      tabButtons: tabs,
      panes,
      fieldsContainer: el.editForm.querySelector('#vacFormFields'),
    };
    if (tabs.form) tabs.form.addEventListener('click', () => switchVacTab('form'));
    if (tabs.records) tabs.records.addEventListener('click', () => switchVacTab('records'));
  }

  function buildVacForm(detail, defaults = {}) {
    if (!editState.vac.elements || !editState.vac.elements.fieldsContainer) return;
    const now = new Date();
    const control = detail.control || state.personaControl || '';
    const defaultYear = defaults.year ?? detail.year ?? state.personaYear ?? now.getFullYear();
    const fields = [
      { type: 'hidden', name: 'id', value: defaults.id || '' },
      { type: 'hidden', name: 'control', value: control },
      { name: 'year', label: 'Año', type: 'number', value: defaultYear, col: 'col-6 col-md-4', attrs: { min: '2010', max: String(now.getFullYear() + 1) } },
      { name: 'tipo', label: 'Tipo', type: 'select', value: (defaults.tipo || 'VAC').toUpperCase(), col: 'col-6 col-md-4', options: [
        { value: 'VAC', label: VAC_TYPE_LABELS.VAC },
        { value: 'PR', label: VAC_TYPE_LABELS.PR },
        { value: 'ANT', label: VAC_TYPE_LABELS.ANT },
      ] },
      { name: 'periodo', label: 'Periodo', type: 'number', value: defaults.periodo ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
      { name: 'inicia', label: 'Inicia', type: 'text', value: toDateMask(defaults.inicia || ''), col: 'col-6 col-md-4', attrs: { placeholder: 'dd/mm/aaaa', 'data-mask': 'date', inputmode: 'numeric', maxlength: '10' } },
      { name: 'reanuda', label: 'Reanuda', type: 'text', value: toDateMask(defaults.reanuda || ''), col: 'col-6 col-md-4', attrs: { placeholder: 'dd/mm/aaaa', 'data-mask': 'date', inputmode: 'numeric', maxlength: '10' } },
      { name: 'dias', label: 'Días usados', type: 'number', value: defaults.dias ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
      { name: 'resta', label: 'Días restantes', type: 'number', value: defaults.resta ?? '', col: 'col-6 col-md-4', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
      { name: 'obs', label: 'Observaciones', type: 'textarea', value: defaults.obs ?? '', col: 'col-12', attrs: { maxlength: '120', rows: 2 } },
    ];
    populateEditForm(fields, editState.vac.elements.fieldsContainer);
  }

  function setVacFormValues(values) {
    if (!el.editForm) return;
    Object.entries(values || {}).forEach(([name, val]) => {
      const input = el.editForm.querySelector(`[name="${name}"]`);
      if (!input) return;
      input.value = val ?? '';
    });
    if (typeof window.applyDateMask === 'function') {
      window.applyDateMask(el.editForm);
    }
  }

  function renderVacRecords() {
    const pane = editState.vac.elements?.panes?.records;
    if (!pane) return;
    const records = Array.isArray(editState.vac.records) ? editState.vac.records : [];
    if (!records.length) {
      pane.innerHTML = '<div class="vac-records-empty">Sin movimientos guardados para este año.</div>';
      return;
    }
    const rows = records.map((record) => {
      const tipo = String(record.tipo || '').toUpperCase();
      const label = VAC_TYPE_LABELS[tipo] || tipo || '—';
      return `
        <tr>
          <td>${label}</td>
          <td class="text-center">${record.periodo ?? ''}</td>
          <td>${toDateMask(record.inicia) || ''}</td>
          <td>${toDateMask(record.reanuda) || ''}</td>
          <td class="text-center">${record.dias ?? 0}</td>
          <td class="text-center">${record.resta ?? 0}</td>
          <td>${record.obs || ''}</td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-outline-primary" data-vac-edit="${record.id || ''}">Editar</button>
              <button type="button" class="btn btn-outline-danger" data-vac-delete="${record.id || ''}">Eliminar</button>
            </div>
          </td>
        </tr>`;
    }).join('');
    pane.innerHTML = `
      <div class="table-responsive">
        <table class="vac-records-table">
          <thead>
            <tr><th>Tipo</th><th>Periodo</th><th>Inicia</th><th>Reanuda</th><th>Días</th><th>Restan</th><th>Observaciones</th><th class="text-end">Acciones</th></tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    `;
    pane.querySelectorAll('[data-vac-edit]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-vac-edit');
        const record = records.find((r) => String(r.id) === String(id));
        if (record) {
          setVacFormMode('edit', record);
          switchVacTab('form');
        }
      });
    });
    pane.querySelectorAll('[data-vac-delete]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-vac-delete');
        const record = records.find((r) => String(r.id) === String(id));
        if (record) {
          confirmVacDelete(record);
        }
      });
    });
  }

  function switchVacTab(tab) {
    if (!editState.vac.elements) return;
    const { tabButtons, panes } = editState.vac.elements;
    ['form', 'records'].forEach((key) => {
      if (tabButtons && tabButtons[key]) {
        tabButtons[key].classList.toggle('active', key === tab);
      }
      if (panes && panes[key]) {
        panes[key].classList.toggle('show', key === tab);
        panes[key].classList.toggle('active', key === tab);
      }
    });
    editState.vac.activeTab = tab;
    if (el.editSaveBtn) {
      el.editSaveBtn.disabled = tab !== 'form';
    }
  }

  function setVacFormMode(mode, record) {
    const detail = editState.vac.detail || {};
    const baseControl = detail.control || state.personaControl || '';
    const baseYear = detail.year || state.personaYear || new Date().getFullYear();
    if (mode === 'edit' && record) {
      const values = {
        id: record.id || '',
        control: baseControl,
        year: record.year || baseYear,
        tipo: (record.tipo || 'VAC').toUpperCase(),
        periodo: record.periodo ?? '',
        inicia: toDateMask(record.inicia || ''),
        reanuda: toDateMask(record.reanuda || ''),
        dias: record.dias ?? '',
        resta: record.resta ?? '',
        obs: record.obs ?? '',
      };
      setVacFormValues(values);
      const tipoLabel = VAC_TYPE_LABELS[(record.tipo || '').toUpperCase()] || record.tipo || 'Movimiento';
      setModalMessage(`Editando ${tipoLabel}${record.periodo ? ` · Periodo ${record.periodo}` : ''}`, 'info');
      editState.vac.selectedId = record.id || '';
    } else {
      const values = {
        id: '',
        control: baseControl,
        year: baseYear,
        tipo: 'VAC',
        periodo: '',
        inicia: '',
        reanuda: '',
        dias: '',
        resta: '',
        obs: '',
      };
      setVacFormValues(values);
      const persona = getPersonaName(baseControl);
      const ctrlFmt = baseControl ? formatControl(baseControl) : '';
      setModalMessage(persona ? `Control ${ctrlFmt} · ${persona}` : 'Complete los datos del movimiento que desea registrar.', 'info');
      editState.vac.selectedId = null;
    }
  }

  async function loadVacRecords(detail) {
    if (!detail || !detail.control) return [];
    const params = {
      mode: 'persona',
      control: detail.control,
      year: detail.year || state.personaYear,
    };
    const data = await apiFetch('vacaciones_list.php', params);
    const rows = Array.isArray(data.rows) ? data.rows : [];
    const targetYear = Number(detail.year || state.personaYear || new Date().getFullYear());
    return rows
      .filter((row) => Number(row.year) === targetYear)
      .map((row) => ({
        ...row,
        id: row.id ?? row.ID ?? row.Id ?? null,
      }))
      .sort((a, b) => {
        const pa = Number(a.periodo) || 0;
        const pb = Number(b.periodo) || 0;
        if (pa !== pb) return pa - pb;
        return String(a.tipo || '').localeCompare(String(b.tipo || ''));
      });
  }

  async function deleteVacRecord(id) {
    const formData = new FormData();
    formData.append('id', id);
    const resp = await fetch(API_BASE + 'vacaciones_delete.php', {
      method: 'POST',
      credentials: 'include',
      body: formData,
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (data && data.ok !== true) throw new Error(data.error || 'No se pudo eliminar');
  }

  async function confirmVacDelete(record) {
    if (!record || !record.id) return;
    if (!window.confirm('¿Eliminar el movimiento seleccionado?')) return;
    try {
      await deleteVacRecord(record.id);
      editState.vac.records = editState.vac.records.filter((r) => String(r.id) !== String(record.id));
      renderVacRecords();
      if (!editState.vac.records.length) {
        switchVacTab('form');
      }
      refreshActive();
      setModalMessage('Movimiento eliminado correctamente.', 'success');
    } catch (err) {
      console.error(err);
      setModalMessage(err.message || 'No se pudo eliminar el movimiento.', 'error');
    }
  }

  async function openVacEdit(detail) {
    const control = detail.control || state.personaControl;
    if (!control) {
      setModalMessage('Seleccione un trabajador para gestionar vacaciones.', 'error');
      ensureModal()?.show();
      return;
    }
    const now = new Date();
    const defaultYear = detail.year ? parseInt(detail.year, 10) : state.personaYear || now.getFullYear();
    editState.vac = {
      detail: { ...detail, control, year: defaultYear },
      records: [],
      activeTab: detail.action === 'edit' ? 'records' : 'form',
      selectedId: null,
      elements: null,
    };
    setModalTitle('Vacaciones — movimientos');
    ensureModal()?.show();
    setModalMessage('Cargando movimientos…', 'info');
    if (el.editSaveBtn) el.editSaveBtn.disabled = true;
    buildVacModalSkeleton(editState.vac.detail);
    buildVacForm(editState.vac.detail, { year: defaultYear });
    setVacFormMode('add');
    switchVacTab(editState.vac.activeTab);
    const personaName = getPersonaName(control);
    try {
      const records = await loadVacRecords(editState.vac.detail);
      editState.vac.records = records;
      renderVacRecords();
      if (detail.action === 'edit' && records.length) {
        switchVacTab('records');
      } else {
        switchVacTab('form');
      }
      const ctrlFmt = formatControl(control);
      const msg = personaName ? `Control ${ctrlFmt} · ${personaName}` : `Control ${ctrlFmt}`;
      setModalMessage(msg, 'info');
      if (el.editSaveBtn && editState.vac.activeTab === 'form') {
        el.editSaveBtn.disabled = false;
      }
    } catch (err) {
      console.error(err);
      renderVacRecords();
      setModalMessage(err.message || 'No se pudo cargar la información de vacaciones.', 'error');
      if (el.editSaveBtn) el.editSaveBtn.disabled = true;
    }
  }

  function extractIncRow(detail) {
    const row = detail.row;
    if (!row) return {};
    const cells = Array.from(row.querySelectorAll('td'));
    if (!cells.length) return {};
    if (detail.scope === 'persona') {
      return {
        year: cells[0]?.textContent?.trim() || detail.year || '',
        folio: cells[1]?.textContent?.trim() || '',
        inicia: cells[2]?.textContent?.trim() || '',
        termina: cells[3]?.textContent?.trim() || '',
        dias: cells[4]?.textContent?.trim() || '',
        umf: cells[5]?.textContent?.trim() || '',
        diag: cells[6]?.textContent?.trim() || '',
      };
    }
    return {
      year: detail.year || '',
      folio: cells[2]?.textContent?.trim() || '',
      inicia: cells[3]?.textContent?.trim() || '',
      termina: cells[4]?.textContent?.trim() || '',
      dias: cells[5]?.textContent?.trim() || '',
      umf: cells[6]?.textContent?.trim() || '',
      diag: cells[7]?.textContent?.trim() || '',
    };
  }

  function openIncEdit(detail) {
    setModalTitle('Editar incapacidad');
    ensureModal()?.show();
    const parsed = extractIncRow(detail);
    const fields = [
      { type: 'hidden', name: 'id', value: detail.id || '0' },
      { type: 'hidden', name: 'control', value: detail.control || '' },
      { name: 'folio', label: 'Folio', type: 'text', value: parsed.folio || '', col: 'col-12 col-md-6', attrs: { maxlength: '12' } },
      { name: 'inicia', label: 'Inicia', type: 'text', value: toDateMask(parsed.inicia), col: 'col-6 col-md-3', attrs: { placeholder: 'dd/mm/aaaa', 'data-mask': 'date', inputmode: 'numeric', maxlength: '10' } },
      { name: 'termina', label: 'Termina', type: 'text', value: toDateMask(parsed.termina), col: 'col-6 col-md-3', attrs: { placeholder: 'dd/mm/aaaa', 'data-mask': 'date', inputmode: 'numeric', maxlength: '10' } },
      { name: 'dias', label: 'Días', type: 'number', value: parsed.dias || '', col: 'col-6 col-md-3', attrs: { min: '0', step: '1', inputmode: 'numeric' } },
      { name: 'umf', label: 'UMF', type: 'text', value: parsed.umf || '', col: 'col-6 col-md-3', attrs: { maxlength: '4' } },
      { name: 'diag', label: 'Diagnóstico', type: 'textarea', value: parsed.diag || '', col: 'col-12', attrs: { rows: 3, maxlength: '52' } },
    ];
    populateEditForm(fields);
    const persona = getPersonaName(detail.control);
    const ctrl = detail.control ? formatControl(detail.control) : '';
    setModalMessage(persona ? `Control ${ctrl} · ${persona}` : '', 'info');
    if (el.editSaveBtn) el.editSaveBtn.disabled = false;
  }

  async function handleEdit(detail) {
    if (!el.editForm || !el.editModal) return;
    editState.detail = detail;
    resetEditForm();
    switch (detail.type) {
      case 'pecos':
        await openPecosEdit(detail);
        break;
      case 'txt':
        await openTxtEdit(detail);
        break;
      case 'vac':
        openVacEdit(detail);
        break;
      case 'inc':
        openIncEdit(detail);
        break;
      default:
        setModalTitle('Editar registro');
        populateEditForm([]);
        setModalMessage('Acción no soportada.', 'error');
        ensureModal()?.show();
        if (el.editSaveBtn) el.editSaveBtn.disabled = true;
        break;
    }
  }

  function formDataFromEdit() {
    if (!el.editForm) return new FormData();
    return new FormData(el.editForm);
  }

  async function savePecos(formData) {
    const resp = await fetch(API_BASE + 'pecos_save.php', {
      method: 'POST',
      credentials: 'include',
      body: formData,
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (data && data.ok === false) throw new Error(data.error || 'No se pudo guardar');
  }

  async function saveTxt(formData) {
    const resp = await fetch(API_BASE + 'txt_save.php', {
      method: 'POST',
      credentials: 'include',
      body: formData,
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (data && data.ok === false) throw new Error(data.error || 'No se pudo guardar');
  }

  async function saveVac(formData) {
    const resp = await fetch(API_BASE + 'vacaciones_save.php', {
      method: 'POST',
      credentials: 'include',
      body: formData,
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (data && data.ok === false) throw new Error(data.error || 'No se pudo guardar');
  }

  async function saveInc(formData) {
    const resp = await fetch(API_BASE + 'incapacidades_save.php', {
      method: 'POST',
      credentials: 'include',
      body: formData,
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (data && data.ok === false) throw new Error(data.error || 'No se pudo guardar');
  }

  async function handleSaveEdit() {
    if (!editState.detail) return;
    if (!el.editSaveBtn) return;
    const detail = editState.detail;
    if (detail.type === 'vac' && editState.vac && editState.vac.activeTab !== 'form') {
      setModalMessage('Utilice la pestaña “Registrar” para capturar o modificar un movimiento.', 'info');
      return;
    }
    const formData = formDataFromEdit();
    if (detail.type === 'vac' && editState.vac) {
      if (editState.vac.selectedId) {
        formData.set('id', editState.vac.selectedId);
      }
      if (!formData.get('control')) {
        formData.set('control', detail.control || state.personaControl || '');
      }
    }
    if (detail.type === 'inc' || detail.type === 'vac') {
      const fields = detail.type === 'inc' ? ['inicia', 'termina'] : ['inicia', 'reanuda'];
      fields.forEach((field) => {
        const value = formData.get(field);
        if (value) {
          formData.set(field, toDMY(value));
        }
      });
    }
    try {
      el.editSaveBtn.disabled = true;
      setModalMessage('Guardando…', 'info');
      if (detail.type === 'pecos') {
        await savePecos(formData);
      } else if (detail.type === 'txt') {
        await saveTxt(formData);
      } else if (detail.type === 'vac') {
        await saveVac(formData);
      } else if (detail.type === 'inc') {
        await saveInc(formData);
      } else {
        throw new Error('Acción no soportada');
      }
      setModalMessage('Cambios guardados correctamente.', 'success');
      ensureModal()?.hide();
      editState.detail = null;
      refreshActive();
    } catch (err) {
      console.error(err);
      setModalMessage(err.message || 'No se pudo guardar el registro.', 'error');
    } finally {
      if (el.editSaveBtn) el.editSaveBtn.disabled = false;
    }
  }

  async function deletePecos(detail) {
    if (!detail.control || !detail.year) return;
    const formData = new FormData();
    formData.append('control', detail.control);
    formData.append('year', detail.year);
    for (let i = 1; i <= 12; i += 1) {
      formData.append(`dia${i}`, '0');
    }
    await savePecos(formData);
  }

  async function deleteTxt(detail) {
    if (!detail.control || !detail.year) return;
    const formData = new FormData();
    formData.append('control', detail.control);
    formData.append('year', detail.year);
    formData.append('js', '0');
    formData.append('vs', '0');
    formData.append('dm', '0');
    formData.append('ds', '0');
    formData.append('muert', '0');
    formData.append('ono', '0');
    await saveTxt(formData);
  }

  async function deleteInc(detail) {
    if (!detail.id) return;
    const formData = new FormData();
    formData.append('id', detail.id);
    const resp = await fetch(API_BASE + 'incapacidades_delete.php', {
      method: 'POST',
      credentials: 'include',
      body: formData,
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (data && data.ok === false) throw new Error(data.error || 'No se pudo eliminar');
  }

  async function handleDelete(detail) {
    const { type } = detail;
    try {
      if (type === 'pecos') {
        if (!window.confirm('¿Desea eliminar los datos de PECO para este año?')) return;
        await deletePecos(detail);
        refreshActive();
      } else if (type === 'txt') {
        if (!window.confirm('¿Desea eliminar los datos de Tiempo x Tiempo para este año?')) return;
        await deleteTxt(detail);
        refreshActive();
      } else if (type === 'inc') {
        if (!detail.id) {
          window.alert('No se pudo determinar el registro a eliminar.');
          return;
        }
        if (!window.confirm('¿Eliminar la incapacidad seleccionada?')) return;
        await deleteInc(detail);
        refreshActive();
      } else if (type === 'vac') {
        window.alert('Para eliminar un movimiento de vacaciones utilice el módulo correspondiente.');
      } else {
        window.alert('Acción no disponible.');
      }
    } catch (err) {
      console.error(err);
      window.alert(err.message || 'No se pudo completar la acción.');
    }
  }

  function handlePrestAction(event) {
    const detail = event.detail || {};
    if (detail.action === 'edit' || detail.action === 'add') {
      handleEdit(detail);
    } else if (detail.action === 'delete') {
      handleDelete(detail);
    }
  }

  function renderIncPersona(data) {
    const table = el.tables.incPersona;
    if (!table) return;
    const rows = data.rows || [];
    const tbody = table.tBodies[0];
    tbody.innerHTML = '';
    if (!rows.length) {
      clearTable(table, 9, 'Sin datos…');
      return;
    }
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const yearText = row.INICIA && row.INICIA.includes('/') ? row.INICIA.split('/').pop() : '';
      tr.innerHTML = `
        <td>${yearText || ''}</td>
        <td>${row.FOLIO || ''}</td>
        <td>${row.INICIA || ''}</td>
        <td>${row.TERMINA || ''}</td>
        <td class="text-center">${row.DIAS ?? 0}</td>
        <td>${row.UMF || ''}</td>
        <td>${row.DIAGNOSTICO || ''}</td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-primary" data-prest-action="edit" data-prest-type="inc" data-prest-scope="persona" data-prest-control="${state.personaControl}" data-prest-id="${row.Id ?? row.id ?? ''}" data-prest-year="${yearText || ''}">Editar</button></td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-danger" data-prest-action="delete" data-prest-type="inc" data-prest-scope="persona" data-prest-control="${state.personaControl}" data-prest-id="${row.Id ?? row.id ?? ''}" data-prest-year="${yearText || ''}">Eliminar</button></td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderIncYear(data) {
    const table = el.tables.incYear;
    if (!table) return;
    const rows = data.rows || [];
    const tbody = table.tBodies[0];
    tbody.innerHTML = '';
    if (!rows.length) {
      clearTable(table, 10, 'Sin datos…');
      return;
    }
    const targetYear = data.year ?? state.year;
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="nowrap">${row.oaci || ''}</td>
        <td class="nowrap">${formatControl(row.control)} · ${row.nombres || ''}</td>
        <td>${row.FOLIO || ''}</td>
        <td>${row.INICIA || ''}</td>
        <td>${row.TERMINA || ''}</td>
        <td class="text-center">${row.DIAS ?? 0}</td>
        <td>${row.UMF || ''}</td>
        <td>${row.DIAGNOSTICO || ''}</td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-primary" data-prest-action="edit" data-prest-type="inc" data-prest-scope="anio" data-prest-control="${row.control}" data-prest-year="${targetYear}" data-prest-id="${row.Id ?? row.id ?? ''}">Editar</button></td>
        <td class="nowrap"><button class="btn btn-sm btn-outline-danger" data-prest-action="delete" data-prest-type="inc" data-prest-scope="anio" data-prest-control="${row.control}" data-prest-year="${targetYear}" data-prest-id="${row.Id ?? row.id ?? ''}">Eliminar</button></td>
      `;
      tbody.appendChild(tr);
    });
  }

  function refreshYearsSelect(select, years, placeholder) {
    if (!select) return;
    select.innerHTML = '';
    years.forEach((year) => {
      const opt = document.createElement('option');
      opt.value = year;
      opt.textContent = year;
      select.appendChild(opt);
    });
    if (placeholder !== undefined) {
      select.value = String(placeholder);
    }
  }

  async function refreshActive() {
    if (!state.init) return;
    const tab = activeTab();
    const personaMode = state.mode === 'persona';
    const params = {};
    if (personaMode) {
      params.mode = 'persona';
      params.control = state.personaControl;
      params.year = state.personaYear;
    } else {
      params.mode = 'anio';
      params.year = state.year;
      params.stations = stationsParam();
    }
    try {
      setError('');
      if (tab === 'pecos') {
        const data = await apiFetch('pecos_list.php', params);
        if (personaMode) renderPecosPersona(data);
        else renderPecosYear(data);
      } else if (tab === 'txt') {
        const data = await apiFetch('txt_list.php', params);
        if (personaMode) renderTxtPersona(data);
        else renderTxtYear(data);
      } else if (tab === 'vac') {
        const data = await apiFetch('vacaciones_list.php', params);
        if (personaMode) {
          renderVacPersona(data);
          if (Array.isArray(data.available_years) && data.available_years.length && el.personaYearSelect) {
            const years = Array.from(new Set([...data.available_years, ...state.init.years]));
            years.sort((a, b) => b - a);
            refreshYearsSelect(el.personaYearSelect, years, state.personaYear);
          }
        } else {
          renderVacYear(data);
        }
      } else if (tab === 'inc') {
        const data = await apiFetch('incapacidades_list.php', params);
        if (personaMode) renderIncPersona(data);
        else renderIncYear(data);
      }
    } catch (err) {
      console.error(err);
      setError(err.message || 'Error al cargar datos');
    }
  }

  function handlePersonaChange() {
    if (!el.personaSelect) return;
    setPersonaControl(el.personaSelect.value || '', { updateSearch: true, silent: false });
  }

  function handlePersonaSearchInput() {
    if (!el.personaSearch) return;
    const wrap = el.personaWrap ? el.personaWrap.querySelector('.persona-search-input') : null;
    if (wrap) {
      wrap.classList.toggle('has-value', el.personaSearch.value.trim().length > 0);
    }
  }

  function handlePersonaSearchChange() {
    if (!el.personaSearch) return;
    const control = resolvePersonaControlFromInput(el.personaSearch.value);
    setPersonaControl(control, { updateSearch: true, silent: false });
  }

  function handlePersonaClear() {
    if (!el.personaSearch) return;
    el.personaSearch.value = '';
    setPersonaControl('', { updateSearch: true, silent: false });
  }

  function handlePersonaYearChange() {
    if (!el.personaYearSelect) return;
    state.personaYear = parseInt(el.personaYearSelect.value, 10) || state.personaYear;
    refreshActive();
  }

  function handleYearChange() {
    if (!el.yearSelect) return;
    state.year = parseInt(el.yearSelect.value, 10) || state.year;
    refreshActive();
  }

  function bindEvents() {
    el.modeBtns.forEach((btn) => {
      btn.addEventListener('click', () => setMode(btn.getAttribute('data-mode')));
    });
    if (el.personaSelect) el.personaSelect.addEventListener('change', handlePersonaChange);
    if (el.personaSearch) {
      el.personaSearch.addEventListener('input', handlePersonaSearchInput);
      el.personaSearch.addEventListener('change', handlePersonaSearchChange);
    }
    if (el.personaClear) {
      el.personaClear.addEventListener('click', handlePersonaClear);
    }
    if (el.personaYearSelect) el.personaYearSelect.addEventListener('change', handlePersonaYearChange);
    if (el.yearSelect) el.yearSelect.addEventListener('change', handleYearChange);
    if (el.oaciAll) {
      el.oaciAll.addEventListener('change', () => {
        const on = el.oaciAll.checked;
        document.querySelectorAll('.oaci-switch').forEach((sw) => {
          sw.checked = on;
        });
        refreshPersonaOptions();
        refreshActive();
      });
    }
    document.addEventListener('click', handleActionClick);
    document.addEventListener('shown.bs.tab', (ev) => {
      if (ev.target && ev.target.id && ev.target.id.startsWith('tab-')) {
        refreshActive();
      }
    });
    document.addEventListener('prestaciones:action', handlePrestAction);
    if (el.editModal) {
      el.editModal.addEventListener('hidden.bs.modal', () => {
        editState.detail = null;
        resetEditForm();
      });
    }
    if (el.editSaveBtn) {
      el.editSaveBtn.addEventListener('click', handleSaveEdit);
    }
  }

  function populateInit(data) {
    state.init = data;
    state.personaYear = Array.isArray(data.years) && data.years.length ? data.years[0] : state.personaYear;
    state.year = state.personaYear;
    refreshPersonaOptions({ preserveSelection: false });
    if (state.personaControl) {
      setPersonaControl(state.personaControl, { updateSearch: true, silent: true });
    }
    if (el.personaYearSelect && Array.isArray(data.years)) {
      refreshYearsSelect(el.personaYearSelect, data.years, state.personaYear);
    }
    if (el.yearSelect && Array.isArray(data.years)) {
      refreshYearsSelect(el.yearSelect, data.years, state.year);
    }
    if (el.oaciList) {
      el.oaciList.innerHTML = (data.stations || [])
        .map((oaci) => `
          <div class="form-check form-switch">
            <input class="form-check-input oaci-switch" type="checkbox" role="switch" id="oaci_${oaci}" data-oaci="${oaci}" checked>
            <label class="form-check-label" for="oaci_${oaci}">${oaci}</label>
          </div>
        `)
        .join('');
      document.querySelectorAll('.oaci-switch').forEach((sw) => {
        sw.addEventListener('change', () => {
          if (!sw.checked && el.oaciAll) {
            el.oaciAll.checked = false;
          }
          refreshPersonaOptions();
          refreshActive();
        });
      });
      if (el.oaciAll) el.oaciAll.checked = true;
    }
  }

  async function init() {
    try {
      const data = await apiFetch('prestaciones_init.php', {});
      populateInit(data);
      bindEvents();
      toggleModeElements();
      refreshActive();
    } catch (err) {
      console.error(err);
      setError(err.message || 'No se pudo iniciar el módulo');
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
