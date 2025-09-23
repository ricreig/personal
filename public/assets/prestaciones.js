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

  const el = {
    error: document.getElementById('prestError'),
    modeBtns: Array.from(document.querySelectorAll('#modeSwitch [data-mode]')),
    personaWrap: document.getElementById('personaSelectWrap'),
    personaSelect: document.getElementById('personaSelect'),
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

function toggleModeElements() {
  const personaMode = state.mode === 'persona';

  if (el.personaWrap) {
    el.personaWrap.classList.toggle('d-none', !personaMode);
  }
  if (el.personaSelect) {
    el.personaSelect.toggleAttribute('disabled', !personaMode);
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
    };
    document.dispatchEvent(new CustomEvent('prestaciones:action', { detail }));
    if (!btn.hasAttribute('data-prest-silent')) {
      console.info('Acción prestaciones', detail);
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
    state.personaControl = el.personaSelect.value || '';
    refreshActive();
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
    if (el.personaYearSelect) el.personaYearSelect.addEventListener('change', handlePersonaYearChange);
    if (el.yearSelect) el.yearSelect.addEventListener('change', handleYearChange);
    if (el.oaciAll) {
      el.oaciAll.addEventListener('change', () => {
        const on = el.oaciAll.checked;
        document.querySelectorAll('.oaci-switch').forEach((sw) => {
          sw.checked = on;
        });
        refreshActive();
      });
    }
    document.addEventListener('click', handleActionClick);
    document.addEventListener('shown.bs.tab', (ev) => {
      if (ev.target && ev.target.id && ev.target.id.startsWith('tab-')) {
        refreshActive();
      }
    });
  }

  function populateInit(data) {
    state.init = data;
    state.personaYear = Array.isArray(data.years) && data.years.length ? data.years[0] : state.personaYear;
    state.year = state.personaYear;
    if (el.personaSelect) {
      el.personaSelect.innerHTML = '<option value="">Seleccione…</option>' +
        (data.personas || [])
          .map((p) => `<option value="${p.control}">${p.oaci ? `${p.oaci} · ` : ''}${formatControl(p.control)} · ${p.nombres || ''}</option>`)
          .join('');
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
