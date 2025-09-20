/* Prestaciones module JS — toggles nativos + select all + tabs/toolbar
   Requires: jQuery, Bootstrap bundle, assets/vendor/jquery.checkbox.js
*/
(function(){
  const API = (window.API_BASE || '/api/').replace(/\/+$/, '/') ;

  const state = {
    mode: 'persona', // 'persona' | 'anio'
    oaci: [],        // active stations
    personas: [],    // {control,nombres,oaci}
    years: [],       // [2025, 2024, ... 2017]
    activeTab: 'pecos', // 'pecos' | 'txt' | 'vac' | 'inc'
    selectedPersona: '',
    selectedYear: ''
  };

  function debounce(fn, ms){ let t; return function(){ clearTimeout(t); t=setTimeout(()=>fn.apply(this, arguments), ms); }; }

  async function fetchJSON(url, opts){
    const r = await fetch(url, { credentials:'include', ...opts });
    if (!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
  }

  function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]); }

  function emit(name, detail){ document.dispatchEvent(new CustomEvent(name, { detail })); }

  // ---------- UI builders ----------
  function buildStationsUI(payload){
    const wrapSingle = document.getElementById('stationsSingle');
    const wrapMulti  = document.getElementById('stationsMulti');
    const singleOaci = document.getElementById('singleOaci');
    const list       = document.getElementById('oaciList');

    if (!Array.isArray(payload.oaci) || payload.oaci.length === 0) {
      wrapSingle.classList.remove('d-none');
      if (singleOaci) singleOaci.textContent = '—';
      return;
    }

    if (payload.oaci.length === 1) {
      wrapSingle.classList.remove('d-none');
      if (singleOaci) singleOaci.textContent = payload.oaci[0];
      state.oaci = [payload.oaci[0]];
      emit('oaci:changed', state.oaci.slice());
      return;
    }

    // Multi
    wrapMulti.classList.remove('d-none');
    list.innerHTML = '';
    payload.oaci.forEach(o=>{
      const id = 'toggle-'+o;
      list.insertAdjacentHTML('beforeend', `
        <div class="form-check form-switch">
          <input class="form-check-input oaci-child" type="checkbox" role="switch" id="${id}" value="${o}" checked>
          <label class="form-check-label switch-label" for="${id}">${o}</label>
        </div>
      `);
    });

    // Parent checkbox plugin
    const parent = $('#oaciAll');
    parent.attr('data-children', '#oaciList .oaci-child');
    const cb = parent.checkbox(function(values){
      state.oaci = values.slice();
      emit('oaci:changed', state.oaci.slice());
      refreshActive();
    });

    // Children debounce
    $('#oaciList .oaci-child').on('change', debounce(function(){
      // recompute active OACI
      const vals = Array.from(document.querySelectorAll('#oaciList .oaci-child:checked')).map(i=>i.value);
      state.oaci = vals;
      emit('oaci:changed', state.oaci.slice());
      refreshActive();
    }, 180));

    // initial values
    state.oaci = payload.oaci.slice();
    emit('oaci:changed', state.oaci.slice());
  }

  function buildSelectors(payload){
    // Personas
    state.personas = Array.isArray(payload.personas) ? payload.personas : [];
    const selPersona = document.getElementById('personaSelect');
    const prev = selPersona.value;
    selPersona.innerHTML = '<option value="">Seleccione…</option>';
    state.personas.forEach(p => {
      const opt = document.createElement('option');
      opt.value = String(p.control);
      opt.textContent = `${p.oaci} — ${p.control} · ${p.nombres}`;
      selPersona.appendChild(opt);
    });
    if (prev) selPersona.value = prev;

    // Years
    state.years = Array.isArray(payload.years) ? payload.years : [];
    const selYear = document.getElementById('anioSelect');
    selYear.innerHTML = '';
    state.years.forEach(y=>{
      const opt = document.createElement('option');
      opt.value = String(y); opt.textContent = String(y);
      selYear.appendChild(opt);
    });
    if (!state.selectedYear && state.years.length) state.selectedYear = String(state.years[0]);
    selYear.value = state.selectedYear;
  }

  // ---------- Render tables ----------

  function renderPecosPersona(rows){
    const tb = document.querySelector('#tblPecosPersona tbody');
    if (!rows || !rows.length){
      tb.innerHTML = '<tr><td colspan="15" class="text-secondary">Sin datos.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r=>{
      const cels = [];
      cels.push(`<td>${esc(r.year)}</td>`);
      for (let i=1;i<=12;i++){ cels.push(`<td>${esc(r['dia'+i]||'')}</td>`); }
      cels.push(`<td><button class="btn btn-sm btn-primary" data-edit="pecos" data-control="${esc(r.control)}" data-year="${esc(r.year)}">Editar</button></td>`);
      cels.push(`<td><button class="btn btn-sm btn-outline-danger" data-del="pecos" data-control="${esc(r.control)}" data-year="${esc(r.year)}">Eliminar</button></td>`);
      return `<tr>${cels.join('')}</tr>`;
    }).join('');
  }

  function renderPecosYear(rows){
    const tb = document.querySelector('#tblPecosYear tbody');
    if (!rows || !rows.length){
      tb.innerHTML = '<tr><td class="text-secondary">Sin datos.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r=>{
      const cels = [];
      cels.push(`<td>${esc(r.oaci||'')}</td>`);
      cels.push(`<td>${esc(r.nombres||'')}<br><small class="text-secondary">#${esc(r.control||'')}</small></td>`);
      for (let i=1;i<=12;i++){ cels.push(`<td>${esc(r['dia'+i]||'')}</td>`); }
      cels.push(`<td><button class="btn btn-sm btn-primary" data-edit="pecos" data-control="${esc(r.control)}" data-year="${esc(state.selectedYear)}">Editar</button></td>`);
      cels.push(`<td><button class="btn btn-sm btn-outline-danger" data-del="pecos" data-control="${esc(r.control)}" data-year="${esc(state.selectedYear)}">Eliminar</button></td>`);
      return `<tr>${cels.join('')}</tr>`;
    }).join('');
  }

  function renderTxtPersona(rows){
    const tb = document.querySelector('#tblTxtPersona tbody');
    if (!rows || !rows.length){
      tb.innerHTML = '<tr><td colspan="10" class="text-secondary">Sin datos.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r=>{
      const cels = [];
      cels.push(`<td>${esc(r.year)}</td>`);
      ['js','vs','dm','ds','muert','ono'].forEach(k=> cels.push(`<td>${esc(r[k]||'')}</td>`));
      cels.push(`<td>${esc(r.fecha_nacimiento||'')}</td>`);
      cels.push(`<td><button class="btn btn-sm btn-primary" data-edit="txt" data-control="${esc(r.control)}" data-year="${esc(r.year)}">Editar</button></td>`);
      cels.push(`<td><button class="btn btn-sm btn-outline-danger" data-del="txt" data-control="${esc(r.control)}" data-year="${esc(r.year)}">Eliminar</button></td>`);
      return `<tr>${cels.join('')}</tr>`;
    }).join('');
  }

  function renderTxtYear(rows){
    const tb = document.querySelector('#tblTxtYear tbody');
    if (!rows || !rows.length){
      tb.innerHTML = '<tr><td class="text-secondary">Sin datos.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r=>{
      const cels = [];
      cels.push(`<td>${esc(r.oaci||'')}</td>`);
      cels.push(`<td>${esc(r.nombres||'')}<br><small class="text-secondary">#${esc(r.control||'')}</small></td>`);
      ['js','vs','dm','ds','muert','ono'].forEach(k=> cels.push(`<td>${esc(r[k]||'')}</td>`));
      cels.push(`<td>${esc(r.fecha_nacimiento||'')}</td>`);
      cels.push(`<td><button class="btn btn-sm btn-primary" data-edit="txt" data-control="${esc(r.control)}" data-year="${esc(state.selectedYear)}">Editar</button></td>`);
      cels.push(`<td><button class="btn btn-sm btn-outline-danger" data-del="txt" data-control="${esc(r.control)}" data-year="${esc(state.selectedYear)}">Eliminar</button></td>`);
      return `<tr>${cels.join('')}</tr>`;
    }).join('');
  }

  function renderVacPersona(rows){
    const tb = document.querySelector('#tblVacPersona tbody');
    if (!rows || !rows.length){
      tb.innerHTML = '<tr><td colspan="9" class="text-secondary">Sin datos.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r=>{
      const cels = [];
      cels.push(`<td>${esc(r.year)}</td>`);
      cels.push(`<td>${esc(r.dias_ant)}</td>`);
      cels.push(`<td>${esc(r.pr)}</td>`);
      cels.push(`<td>${esc(r.vac1_rest)}</td>`);
      cels.push(`<td>${esc(r.vac2_rest)}</td>`);
      cels.push(`<td>${esc(r.ant_usados)}</td>`);
      cels.push(`<td>${esc(r.pr_usados)}</td>`);
      cels.push(`<td><button class="btn btn-sm btn-primary" data-edit="vac" data-control="${esc(r.control)}" data-year="${esc(r.year)}">Editar</button></td>`);
      cels.push(`<td><button class="btn btn-sm btn-outline-danger" data-del="vac" data-control="${esc(r.control)}" data-year="${esc(r.year)}">Eliminar</button></td>`);
      return `<tr>${cels.join('')}</tr>`;
    }).join('');
  }
  function renderVacYear(rows){
    const tb = document.querySelector('#tblVacYear tbody');
    if (!rows || !rows.length){
      tb.innerHTML = '<tr><td class="text-secondary">Sin datos.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r=>{
      const cels = [];
      cels.push(`<td>${esc(r.oaci||'')}</td>`);
      cels.push(`<td>${esc(r.nombres||'')}<br><small class="text-secondary">#${esc(r.control||'')}</small></td>`);
      cels.push(`<td>${esc(r.dias_ant)}</td>`);
      cels.push(`<td>${esc(r.pr)}</td>`);
      cels.push(`<td>${esc(r.vac1_rest)}</td>`);
      cels.push(`<td>${esc(r.vac2_rest)}</td>`);
      cels.push(`<td>${esc(r.ant_usados)}</td>`);
      cels.push(`<td>${esc(r.pr_usados)}</td>`);
      cels.push(`<td><button class="btn btn-sm btn-primary" data-edit="vac" data-control="${esc(r.control)}" data-year="${esc(state.selectedYear)}">Editar</button></td>`);
      cels.push(`<td><button class="btn btn-sm btn-outline-danger" data-del="vac" data-control="${esc(r.control)}" data-year="${esc(state.selectedYear)}">Eliminar</button></td>`);
      return `<tr>${cels.join('')}</tr>`;
    }).join('');
  }

  function renderIncPersona(rows){
    const tb = document.querySelector('#tblIncPersona tbody');
    if (!rows || !rows.length){
      tb.innerHTML = '<tr><td colspan="9" class="text-secondary">Sin datos.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r=>{
      const cels = [];
      cels.push(`<td>${esc(r.year)}</td>`);
      cels.push(`<td>${esc(r.folio||'')}</td>`);
      cels.push(`<td>${esc(r.inicia||'')}</td>`);
      cels.push(`<td>${esc(r.termina||'')}</td>`);
      cels.push(`<td>${esc(r.dias||'')}</td>`);
      cels.push(`<td>${esc(r.umf||'')}</td>`);
      cels.push(`<td>${esc(r.diag||'')}</td>`);
      cels.push(`<td><button class="btn btn-sm btn-primary" data-edit="inc" data-id="${esc(r.id)}">Editar</button></td>`);
      cels.push(`<td><button class="btn btn-sm btn-outline-danger" data-del="inc" data-id="${esc(r.id)}">Eliminar</button></td>`);
      return `<tr>${cels.join('')}</tr>`;
    }).join('');
  }
  function renderIncYear(rows){
    const tb = document.querySelector('#tblIncYear tbody');
    if (!rows || !rows.length){
      tb.innerHTML = '<tr><td class="text-secondary">Sin datos.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(r=>{
      const cels = [];
      cels.push(`<td>${esc(r.oaci||'')}</td>`);
      cels.push(`<td>${esc(r.nombres||'')}<br><small class="text-secondary">#${esc(r.control||'')}</small></td>`);
      cels.push(`<td>${esc(r.folio||'')}</td>`);
      cels.push(`<td>${esc(r.inicia||'')}</td>`);
      cels.push(`<td>${esc(r.termina||'')}</td>`);
      cels.push(`<td>${esc(r.dias||'')}</td>`);
      cels.push(`<td>${esc(r.umf||'')}</td>`);
      cels.push(`<td>${esc(r.diag||'')}</td>`);
      cels.push(`<td><button class="btn btn-sm btn-primary" data-edit="inc" data-id="${esc(r.id)}">Editar</button></td>`);
      cels.push(`<td><button class="btn btn-sm btn-outline-danger" data-del="inc" data-id="${esc(r.id)}">Eliminar</button></td>`);
      return `<tr>${cels.join('')}</tr>`;
    }).join('');
  }

  // ---------- Data fetchers ----------
  async function refreshActive(){
    const tab = state.activeTab;
    if (state.mode === 'persona'){
      const ctrl = document.getElementById('personaSelect').value.trim();
      state.selectedPersona = ctrl;
      if (!ctrl) return;
      if (tab === 'pecos'){
        const rows = await fetchJSON(API+'pecos_list.php?mode=persona&control='+encodeURIComponent(ctrl));
        renderPecosPersona(rows.data || []);
      } else if (tab === 'txt'){
        const rows = await fetchJSON(API+'txt_list.php?mode=persona&control='+encodeURIComponent(ctrl));
        renderTxtPersona(rows.data || []);
      } else if (tab === 'vac'){
        const rows = await fetchJSON(API+'vacaciones_list.php?mode=persona&control='+encodeURIComponent(ctrl));
        renderVacPersona(rows.data || []);
      } else if (tab === 'inc'){
        const rows = await fetchJSON(API+'incapacidades_list.php?mode=persona&control='+encodeURIComponent(ctrl));
        renderIncPersona(rows.data || []);
      }
    } else {
      const year = document.getElementById('anioSelect').value.trim();
      state.selectedYear = year;
      const oaci = state.oaci.slice();
      const qs = 'year='+encodeURIComponent(year)+'&oaci='+encodeURIComponent(oaci.join(','));
      if (tab === 'pecos'){
        const rows = await fetchJSON(API+'pecos_list.php?mode=anio&'+qs);
        renderPecosYear(rows.data || []);
      } else if (tab === 'txt'){
        const rows = await fetchJSON(API+'txt_list.php?mode=anio&'+qs);
        renderTxtYear(rows.data || []);
      } else if (tab === 'vac'){
        const rows = await fetchJSON(API+'vacaciones_list.php?mode=anio&'+qs);
        renderVacYear(rows.data || []);
      } else if (tab === 'inc'){
        const rows = await fetchJSON(API+'incapacidades_list.php?mode=anio&'+qs);
        renderIncYear(rows.data || []);
      }
    }
  }

  // ---------- Events & Init ----------
  document.addEventListener('DOMContentLoaded', async function(){
    try {
      const init = await fetchJSON(API+'prestaciones_init.php');
      buildStationsUI(init);
      buildSelectors(init);
      // default selections
      if (init.personas && init.personas[0]) {
        document.getElementById('personaSelect').value = String(init.personas[0].control);
      }
      if (init.years && init.years[0]) {
        document.getElementById('anioSelect').value = String(init.years[0]);
      }
    } catch(e){
      console.error('Init error', e);
    }

    // Mode switch
    document.querySelectorAll('#modeSwitch [data-mode]').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        const m = btn.getAttribute('data-mode');
        if (state.mode === m) return;
        state.mode = m;
        document.querySelectorAll('#modeSwitch [data-mode]').forEach(b=>{
          b.classList.toggle('btn-primary', b===btn);
          b.classList.toggle('btn-outline-secondary', b!==btn);
        });
        document.getElementById('personaSelectWrap').classList.toggle('d-none', m!=='persona');
        document.getElementById('anioSelectWrap').classList.toggle('d-none', m!=='anio');
        // show/hide per-tab panes
        document.getElementById('pecosPersona').classList.toggle('d-none', !(m==='persona'));
        document.getElementById('pecosAnio').classList.toggle('d-none', !(m==='anio'));
        document.getElementById('txtPersona').classList.toggle('d-none', !(m==='persona'));
        document.getElementById('txtAnio').classList.toggle('d-none', !(m==='anio'));
        document.getElementById('vacPersona').classList.toggle('d-none', !(m==='persona'));
        document.getElementById('vacAnio').classList.toggle('d-none', !(m==='anio'));
        document.getElementById('incPersona').classList.toggle('d-none', !(m==='persona'));
        document.getElementById('incAnio').classList.toggle('d-none', !(m==='anio'));
        emit('mode:changed', m);
        refreshActive();
      });
    });

    // Persona/year selectors
    document.getElementById('personaSelect').addEventListener('change', ()=>{ refreshActive(); });
    document.getElementById('anioSelect').addEventListener('change', ()=>{ refreshActive(); });

    // Tabs
    document.querySelectorAll('#prestTabs button[data-bs-toggle="tab"]').forEach(btn=>{
      btn.addEventListener('shown.bs.tab', (e)=>{
        const id = e.target.id;
        state.activeTab = id.replace('tab-','');
        refreshActive();
      });
    });

    // First paint
    refreshActive();
  });

  // Expose refresh for external triggers (keeps backward compat)
  window.refreshPecosTxtViews = function(detail){
    refreshActive();
  };

})();
