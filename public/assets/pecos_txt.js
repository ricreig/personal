/* global bootstrap */
(()=>{
  const API = (window.API_BASE || '/api/').replace(/\/+$/,'/') ;

  const qs = (s, el=document) => el.querySelector(s);
  const qsa = (s, el=document) => Array.from(el.querySelectorAll(s));
  const byId = (id)=> document.getElementById(id);
  const state = {
    stationsAll: [],
    stationsSelected: [],
    multiStation: false,
    mode: 'persona', // persona | anio
    years: [],
    employees: [],
    employeeMap: new Map(),
  };

  function fetchJSON(url, opts={}){
    return fetch(url,{credentials:'include',...opts}).then(async r=>{
      const ct = (r.headers.get('content-type')||'').toLowerCase();
      const data = ct.includes('json') ? await r.json() : await r.text();
      if (!r.ok) throw new Error(data && data.error ? data.error : `HTTP ${r.status}`);
      return data;
    });
  }

  function badgeStation(oaci, checked){
    const id = `st_${oaci}`;
    return `
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="${id}" value="${oaci}" ${checked?'checked':''}>
        <label class="form-check-label" for="${id}">${oaci}</label>
      </div>`;
  }

  function renderStations(){
    const wrap = byId('stationsWrap');
    wrap.innerHTML = '';
    if (!state.multiStation){
      // una sola -> no mostrar controles, solo un badge pasivo
      const o = state.stationsAll[0] || '';
      wrap.innerHTML = `<span class="badge text-bg-secondary">${o}</span>`;
      return;
    }
    wrap.innerHTML = state.stationsAll.map(o=>badgeStation(o, state.stationsSelected.includes(o))).join('');
    wrap.querySelectorAll('input[type=checkbox]').forEach(chk=>{
      chk.addEventListener('change', ()=>{
        const sel = qsa('input[type=checkbox]', wrap).filter(c=>c.checked).map(c=>c.value);
        state.stationsSelected = sel.length ? sel : state.stationsAll.slice(); // evita quedarse en 0
        refresh();
      });
    });
  }

  function setMode(mode){
    state.mode = mode;
    byId('modePersona').checked = (mode === 'persona');
    byId('modeAnio').checked    = (mode === 'anio');
    byId('personaSelectWrap').classList.toggle('d-none', mode !== 'persona');
    byId('contentPersona').classList.toggle('d-none', mode !== 'persona');
    byId('anioSelectWrap').classList.toggle('d-none', mode !== 'anio');
    byId('contentAnio').classList.toggle('d-none', mode !== 'anio');
  }

  function renderYearsSelect(){
    const sel = byId('selAnio');
    sel.innerHTML = state.years.map(y=>`<option value="${y}">${y}</option>`).join('');
    sel.value = String(new Date().getFullYear());
    sel.addEventListener('change', ()=> refreshAnio());
  }

  async function loadEmployees(){
    const body = new FormData();
    state.stationsSelected.forEach(o=> body.append('stations[]', o));
    const res = await fetchJSON(API + 'pecos_txt_empleados.php', { method:'POST', body });
    state.employees = res.data || [];
    state.employeeMap = new Map(state.employees.map(e=>[String(e.control), e]));
    renderEmployeeSelect();
  }

  function renderEmployeeSelect(){
    const sel = byId('selPersona');
    sel.innerHTML = state.employees.map(e=>`<option value="${e.control}">${e.nombres} — ${e.oaci}</option>`).join('');
    if (state.employees.length) sel.value = String(state.employees[0].control);
    sel.addEventListener('change', ()=> refreshPersona());
  }

  function fmt(val){ return (val==null || String(val).trim()==='' || String(val)==='0') ? '—' : String(val); }

  function yearsRange(minY, maxY){
    const from = Math.min(minY||new Date().getFullYear(), maxY||new Date().getFullYear());
    const to   = Math.max(minY||new Date().getFullYear(), maxY||new Date().getFullYear());
    const out = []; for (let y=from; y<=to; y++) out.push(y); return out;
  }

  async function refreshPersona(){
    const ctrl = byId('selPersona').value;
    if (!ctrl) return;
    const res = await fetchJSON(API + 'pecos_txt_persona.php?control=' + encodeURIComponent(ctrl));
    const head1 = byId('personaHeadPecos');
    const head2 = byId('personaHeadTxt');
    head1.textContent = `— ${res.nombre} · ${res.oaci}`;
    head2.textContent = `— ${res.nombre} · ${res.oaci}`;
    const rowsP = [], rowsT = [];
    const yrs = yearsRange(res.minYear, res.maxYear);
    yrs.forEach(y=>{
      const p = (res.pecos && res.pecos[String(y)]) || {};
      const t = (res.txt && res.txt[String(y)]) || {};
      rowsP.push(`<tr><td class="fw-semibold">${y}</td>${Array.from({length:12},(_,i)=>`<td>${fmt(p['dia'+(i+1)])}</td>`).join('')}<td class="w-min"><button class="btn btn-sm btn-outline-primary" data-edit data-control="${res.control}" data-year="${y}">Editar</button></td></tr>`);
      rowsT.push(`<tr><td class="fw-semibold">${y}</td><td>${fmt(t.js)}</td><td>${fmt(t.vs)}</td><td>${fmt(t.dm)}</td><td>${fmt(t.ds)}</td><td>${fmt(t.muert)}</td><td>${fmt(t.ono)}</td><td>${fmt(res.nacimiento)}</td><td class="w-min"><button class="btn btn-sm btn-outline-primary" data-edit data-control="${res.control}" data-year="${y}">Editar</button></td></tr>`);
    });
    byId('tblPecosPersona').querySelector('tbody').innerHTML = rowsP.join('');
    byId('tblTxtPersona').querySelector('tbody').innerHTML = rowsT.join('');
    bindEditButtons();
  }

  async function refreshAnio(){
    const anio = byId('selAnio').value;
    const body = new FormData();
    body.append('year', anio);
    state.stationsSelected.forEach(o=> body.append('stations[]', o));
    const res = await fetchJSON(API + 'pecos_txt_year.php', { method:'POST', body });
    const showOACI = !!res.showOACI;
    byId('thEstacion').classList.toggle('d-none', !showOACI);
    const head = byId('anioHead');
    head.textContent = `— ${anio}${showOACI ? ' · ' + (state.stationsSelected.join(', ')) : ''}`;

    const rows = [];
    (res.rows || []).forEach(r=>{
      rows.push(`<tr>
        ${showOACI ? `<td class="nowrap">${r.oaci||''}</td>`:''}
        <td class="nowrap">${r.nombres||''} <span class="muted">#${r.control}</span></td>
        ${Array.from({length:12},(_,i)=>`<td>${fmt(r.pecos && r.pecos['dia'+(i+1)])}</td>`).join('')}
        <td>${fmt(r.txt && r.txt.js)}</td><td>${fmt(r.txt && r.txt.vs)}</td><td>${fmt(r.txt && r.txt.dm)}</td><td>${fmt(r.txt && r.txt.ds)}</td><td>${fmt(r.txt && r.txt.muert)}</td><td>${fmt(r.txt && r.txt.ono)}</td><td>${fmt(r.nacimiento)}</td>
        <td class="w-min"><button class="btn btn-sm btn-outline-primary" data-edit data-control="${r.control}" data-year="${anio}">Editar</button></td>
      </tr>`);
    });
    byId('tblAnio').querySelector('tbody').innerHTML = rows.join('');
    bindEditButtons();
  }

  function bindEditButtons(){
    qsa('[data-edit]').forEach(b=>{
      b.addEventListener('click', async ()=>{
        const control = b.getAttribute('data-control');
        const year = b.getAttribute('data-year');
        try {
          const res = await fetchJSON(API + 'pecos_txt_persona_year.php?control='+encodeURIComponent(control)+'&year='+encodeURIComponent(year));
          fillEdit(res);
          const m = new bootstrap.Modal(byId('editModal')); m.show();
        } catch(e){ alert(e.message||'Error cargando registro'); }
      });
    });
    byId('btnSave').onclick = saveEdit;
  }

  function fillEdit(data){
    byId('edControl').value = data.control;
    byId('edYear').value = data.year;
    for (let i=1;i<=12;i++){ byId('edP'+i).value = data.pecos['dia'+i] || ''; }
    byId('edJS').value = data.txt.js || '';
    byId('edVS').value = data.txt.vs || '';
    byId('edDM').value = data.txt.dm || '';
    byId('edDS').value = data.txt.ds || '';
    byId('edMU').value = data.txt.muert || '';
    byId('edON').value = data.txt.ono || '';
  }

  async function saveEdit(){
    const control = byId('edControl').value;
    const year    = byId('edYear').value;
    const fd = new FormData();
    fd.append('control', control);
    fd.append('year', year);
    for (let i=1;i<=12;i++){ fd.append('pecos[dia'+i+']', byId('edP'+i).value.trim()); }
    fd.append('txt[js]', byId('edJS').value.trim());
    fd.append('txt[vs]', byId('edVS').value.trim());
    fd.append('txt[dm]', byId('edDM').value.trim());
    fd.append('txt[ds]', byId('edDS').value.trim());
    fd.append('txt[muert]', byId('edMU').value.trim());
    fd.append('txt[ono]', byId('edON').value.trim());
    try{
      const res = await fetchJSON(API + 'pecos_txt_save.php', { method:'POST', body: fd });
      bootstrap.Modal.getInstance(byId('editModal')).hide();
      if (state.mode === 'persona') refreshPersona(); else refreshAnio();
    }catch(e){ alert(e.message||'No se pudo guardar'); }
  }

  async function refresh(){
    if (state.mode === 'persona'){
      await loadEmployees();
      await refreshPersona();
    } else {
      await refreshAnio();
    }
  }

  async function init(){
    byId('modePersona').addEventListener('change', ()=>{ if (byId('modePersona').checked){ setMode('persona'); refresh(); } });
    byId('modeAnio').addEventListener('change', ()=>{ if (byId('modeAnio').checked){ setMode('anio'); refresh(); } });
    // init data
    const init = await fetchJSON(API + 'pecos_txt_init.php');
    state.stationsAll = init.stations || [];
    state.stationsSelected = init.defaultStations || state.stationsAll.slice();
    state.multiStation = (state.stationsAll.length > 1);
    state.years = init.years || [];
    renderStations();
    renderYearsSelect();
    await refresh();
  }

  document.addEventListener('DOMContentLoaded', init, { once:true });
})();