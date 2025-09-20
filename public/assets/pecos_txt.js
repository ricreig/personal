
(function(){
  const API = window.API_BASE ? window.API_BASE : '/api/';
  const qs  = (s, el=document)=>el.querySelector(s);
  const qsa = (s, el=document)=>Array.from(el.querySelectorAll(s));
  const $   = window.jQuery;

  let state = {
    stations: [],
    personas: [],
    years: [],
    mode: 'persona',
    selStations: [],
    selControl: null,
    selYear: (new Date()).getFullYear()
  };

  function api(url, params){
    const q = new URLSearchParams(params||{}).toString();
    return fetch(API + url + (q ? ('?'+q):''), { credentials:'same-origin' })
      .then(r=>r.json());
  }
  function apiPost(url, body){
    return fetch(API + url, {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(body||{})
    }).then(r=>r.json());
  }

  function debounce(fn, ms){
    let t=null;
    return function(){ clearTimeout(t); const args=arguments; t=setTimeout(()=>fn.apply(this,args), ms); };
  }

  // UI build
  function buildStations(){
    const wrap = qs('#stationsWrap'); wrap.innerHTML='';
    if (!state.stations.length) { wrap.textContent = '—'; return; }
    if (state.stations.length===1){
      const chip = document.createElement('span');
      chip.className = 'oaci-label';
      chip.textContent = state.stations[0];
      wrap.appendChild(chip);
      state.selStations = [state.stations[0]];
      return;
    }
    // parent checkbox
    const parentId = 'chkAllStations';
    const parent = document.createElement('div');
    parent.className='form-check form-switch me-3';
    parent.innerHTML = `<input class="form-check-input" type="checkbox" id="${parentId}" data-children=".st-child" checked>
                        <label class="form-check-label ms-1" for="${parentId}">Seleccionar todo</label>`;
    wrap.appendChild(parent);
    // children
    state.stations.forEach(o=>{
      const id = 'st-'+o;
      const div = document.createElement('div');
      div.className='form-check form-switch me-3';
      div.innerHTML = `<input class="form-check-input st-child" type="checkbox" role="switch" id="${id}" value="${o}" checked>
                       <label class="form-check-label ms-1 oaci-label" for="${id}">${o}</label>`;
      wrap.appendChild(div);
    });
    // init plugin
    const $parent = $('#'+parentId).checkbox(function(values){
      // callback returns values of checked children
      state.selStations = values;
      refreshAll();
    });
    // ensure selection array
    state.selStations = qsa('.st-child', wrap).filter(x=>x.checked).map(x=>x.value);
    // children changes with debounce
    $('.st-child').on('change', debounce(function(){
      state.selStations = qsa('.st-child').filter(x=>x.checked).map(x=>x.value);
      refreshAll();
    }, 120));
  }

  function buildSelectors(){
    // personas
    const selP = qs('#selPersona');
    selP.innerHTML = '';
    if (!state.personas.length){
      selP.disabled = true;
      selP.innerHTML = `<option value="">Sin personas disponibles</option>`;
    } else {
      selP.disabled = false;
      selP.innerHTML = `<option value="">Seleccione…</option>` + state.personas.map(p=>{
        const label = `${p.oaci} — ${p.control} · ${p.nombres}`;
        return `<option value="${p.control}">${label}</option>`;
      }).join('');
    }
    selP.addEventListener('change', ()=>{
      state.selControl = selP.value ? parseInt(selP.value,10) : null;
      refreshPersona();
    });

    // years
    const selY = qs('#selAnio');
    selY.innerHTML = state.years.map(y=>`<option value="${y}">${y}</option>`).join('');
    selY.value = String(state.selYear);
    selY.addEventListener('change', ()=>{
      state.selYear = parseInt(selY.value,10);
      refreshByYear();
    });
  }

  function switchMode(to){
    state.mode = to;
    qsa('#modeSwitch [data-mode]').forEach(b=>{
      b.classList.toggle('btn-primary', b.dataset.mode===to);
      b.classList.toggle('btn-outline-secondary', b.dataset.mode!==to);
    });
    qs('#personaSelectWrap').classList.toggle('d-none', to!=='persona');
    qs('#anioSelectWrap').classList.toggle('d-none', to!=='anio');
    document.dispatchEvent(new CustomEvent('mode:changed', { detail: to }));
    if (to==='persona') refreshPersona(); else refreshByYear();
  }

  // table builders
  function td(v){ return `<td>${v==null||v===''?'<span class="text-secondary">—</span>':v}</td>`; }
  function btnEdit(control, year, type){
    return `<button class="btn btn-sm btn-outline-primary btn-edit" data-control="${control}" data-year="${year}" data-type="${type||''}">Editar</button>`;
  }
  function nameCell(oaci, control, nombre){
    return `<a href="#" class="link-light fw-semibold person-link" data-control="${control}" data-nombre="${encodeURIComponent(nombre)}">${nombre}</a>`;
  }

  function refreshPersona(){
    const control = state.selControl;
    if (!control){ // clear tables
      qs('#tblPecosPersona tbody').innerHTML = `<tr><td colspan="14" class="text-secondary">Seleccione un trabajador…</td></tr>`;
      qs('#tblTxtPersona tbody').innerHTML   = `<tr><td colspan="9" class="text-secondary">Seleccione un trabajador…</td></tr>`;
      qs('#tblVacPersona tbody').innerHTML   = `<tr><td colspan="7" class="text-secondary">Seleccione un trabajador…</td></tr>`;
      qs('#vacResumen').innerHTML = '';
      return;
    }
    api('pecos_list.php', { mode:'persona', control })
      .then(j=>{
        if(!j.ok){ throw new Error('pecos persona'); }
        const tb = j.rows.map(r=>{
          return `<tr>${td(r.year)}${td(r.dia1)}${td(r.dia2)}${td(r.dia3)}${td(r.dia4)}${td(r.dia5)}${td(r.dia6)}${td(r.dia7)}${td(r.dia8)}${td(r.dia9)}${td(r.dia10)}${td(r.dia11)}${td(r.dia12)}<td>${btnEdit(control,r.year,'both')}</td></tr>`;
        }).join('');
        qs('#tblPecosPersona tbody').innerHTML = tb || `<tr><td colspan="14">Sin datos</td></tr>`;
      }).catch(()=>{});
    api('txt_list.php', { mode:'persona', control })
      .then(j=>{
        if(!j.ok){ throw new Error('txt persona'); }
        const tb = j.rows.map(r=>{
          return `<tr>${td(r.year)}${td(r.js)}${td(r.vs)}${td(r.dm)}${td(r.ds)}${td(r.muert)}${td(r.ono)}${td(r.fnac||'')}<td>${btnEdit(control,r.year,'both')}</td></tr>`;
        }).join('');
        qs('#tblTxtPersona tbody').innerHTML = tb || `<tr><td colspan="9">Sin datos</td></tr>`;
      }).catch(()=>{});
    api('vacaciones_list.php', { mode:'persona', control })
      .then(j=>{
        if(!j.ok) return;
        const tb = (j.historico||[]).map(r=>`<tr>${td(r.year)}${td(r.tipo)}${td(r.periodo)}${td(r.inicia)}${td(r.reanuda)}${td(r.dias)}${td(r.resta)}</tr>`).join('');
        qs('#tblVacPersona tbody').innerHTML = tb || `<tr><td colspan="7" class="text-secondary">Sin vacaciones</td></tr>`;
        const res = j.resumen||{};
        qs('#vacResumen').innerHTML = `
          <div class="row g-2">
            <div class="col-6"><div class="border rounded p-2">VAC P1 restantes: <strong>${res.vac1_rest ?? '-'}</strong></div></div>
            <div class="col-6"><div class="border rounded p-2">VAC P2 restantes: <strong>${res.vac2_rest ?? '-'}</strong></div></div>
            <div class="col-6"><div class="border rounded p-2">Antigüedad (base): <strong>${res.ant_base ?? '-'}</strong><br>Restan: <strong>${res.ant_rest ?? '-'}</strong></div></div>
            <div class="col-6"><div class="border rounded p-2">PR (base): <strong>${res.pr_base ?? '-'}</strong><br>Restan: <strong>${res.pr_rest ?? '-'}</strong></div></div>
          </div>`;
      }).catch(()=>{});
  }

  function refreshByYear(){
    const stations = state.selStations;
    const year = state.selYear;
    if (!stations.length){ 
      ['#tblPecosAnio','#tblTxtAnio','#tblVacAnio','#tblInc'].forEach(id=>{ qs(id+' tbody').innerHTML='<tr><td class="text-secondary">Sin estaciones seleccionadas…</td></tr>'; });
      return;
    }
    api('pecos_list.php', { mode:'anio', year, stations: stations.join(',') })
      .then(j=>{
        if(!j.ok) return;
        const tb = (j.rows||[]).map(r=>{
          const name = nameCell(r.oaci, r.control, r.nombres);
          return `<tr><td class="yr-sticky">${r.oaci}</td><td class="yr-sticky-2">${name}</td>${td(r.dia1)}${td(r.dia2)}${td(r.dia3)}${td(r.dia4)}${td(r.dia5)}${td(r.dia6)}${td(r.dia7)}${td(r.dia8)}${td(r.dia9)}${td(r.dia10)}${td(r.dia11)}${td(r.dia12)}<td>${btnEdit(r.control,year,'both')}</td></tr>`;
        }).join('');
        qs('#tblPecosAnio tbody').innerHTML = tb || `<tr><td colspan="16">Sin datos</td></tr>`;
      });
    api('txt_list.php', { mode:'anio', year, stations: stations.join(',') })
      .then(j=>{
        if(!j.ok) return;
        const tb = (j.rows||[]).map(r=>{
          const name = nameCell(r.oaci, r.control, r.nombres);
          return `<tr><td class="yr-sticky">${r.oaci}</td><td class="yr-sticky-2">${name}</td>${td(r.js)}${td(r.vs)}${td(r.dm)}${td(r.ds)}${td(r.muert)}${td(r.ono)}${td(r.fnac||'')}<td>${btnEdit(r.control,year,'both')}</td></tr>`;
        }).join('');
        qs('#tblTxtAnio tbody').innerHTML = tb || `<tr><td colspan="11">Sin datos</td></tr>`;
      });
    api('vacaciones_list.php', { mode:'anio', year, stations: stations.join(',') })
      .then(j=>{
        if(!j.ok) return;
        const tb = (j.rows||[]).map(r=>{
          const name = nameCell(r.oaci, r.control, r.nombres);
          return `<tr><td class="yr-sticky">${r.oaci}</td><td class="yr-sticky-2">${name}</td>${td(r.vac1_rest)}${td(r.vac2_rest)}${td(r.ant_rest)}${td(r.pr_rest)}</tr>`;
        }).join('');
        qs('#tblVacAnio tbody').innerHTML = tb || `<tr><td colspan="6">Sin datos</td></tr>`;
      });
    api('incapacidades_list.php', { mode:'anio', year, stations: stations.join(',') })
      .then(j=>{
        if(!j.ok) return;
        const tb = (j.rows||[]).map(r=>{
          const name = nameCell(r.oaci, r.control, r.nombres);
          return `<tr><td class="yr-sticky">${r.oaci}</td><td class="yr-sticky-2">${name}</td>${td(r.INICIA)}${td(r.TERMINA)}${td(r.DIAS)}${td(r.UMF)}${td(r.DIAGNOSTICO)}${td(r.FOLIO)}</tr>`;
        }).join('');
        qs('#tblInc tbody').innerHTML = tb || `<tr><td colspan="8">Sin datos</td></tr>`;
      });
  }

  // Edit modal
  function openEdit(control, year){
    // load existing values from persona lists if we have them; else fetch single year
    const rowP = qsa('#tblPecosPersona tbody tr').find(tr=>tr.firstElementChild && tr.firstElementChild.textContent.trim()==String(year));
    function valFromCell(tr, idx){ return tr && tr.children[idx] ? tr.children[idx].textContent.trim() : '0'; }
    for(let i=1;i<=12;i++){ qs('#edP'+i).value = rowP ? valFromCell(rowP, i) : '0'; }
    const rowT = qsa('#tblTxtPersona tbody tr').find(tr=>tr.firstElementChild && tr.firstElementChild.textContent.trim()==String(year));
    qs('#edJS').value = rowT ? valFromCell(rowT,1) : '0';
    qs('#edVS').value = rowT ? valFromCell(rowT,2) : '0';
    qs('#edDM').value = rowT ? valFromCell(rowT,3) : '0';
    qs('#edDS').value = rowT ? valFromCell(rowT,4) : '0';
    qs('#edMU').value = rowT ? valFromCell(rowT,5) : '0';
    qs('#edON').value = rowT ? valFromCell(rowT,6) : '0';
    qs('#edControl').value = control;
    qs('#edYear').value = year;
    new bootstrap.Modal(qs('#editModal')).show();
  }
  function bindRowEvents(){
    qsa('.btn-edit').forEach(b=>{
      b.addEventListener('click', (e)=>{
        e.preventDefault();
        openEdit(parseInt(b.dataset.control,10), parseInt(b.dataset.year,10));
      });
    });
    qsa('.person-link').forEach(a=>{
      a.addEventListener('click', (e)=>{
        e.preventDefault();
        state.selControl = parseInt(a.dataset.control,10);
        qs('#selPersona').value = String(state.selControl);
        switchMode('persona'); // jump to persona
      });
    });
  }
  document.addEventListener('click', function(e){
    if (e.target && e.target.classList.contains('btn-edit')){
      // delegated
    }
  });
  document.addEventListener('oaci:changed', refreshAll);

  function refreshAll(){
    // route based on active tab + mode
    if (state.mode==='persona') refreshPersona(); else refreshByYear();
    setTimeout(bindRowEvents, 0);
  }

  // Save
  qs('#btnSave').addEventListener('click', function(){
    const body = {
      control: parseInt(qs('#edControl').value,10),
      year: parseInt(qs('#edYear').value,10),
      pecos: {},
      txt: {
        js: qs('#edJS').value, vs: qs('#edVS').value, dm: qs('#edDM').value,
        ds: qs('#edDS').value, muert: qs('#edMU').value, ono: qs('#edON').value
      }
    };
    for(let i=1;i<=12;i++){ body.pecos['dia'+i] = qs('#edP'+i).value; }
    apiPost('pecos_txt_save.php', body).then(j=>{
      if (j && j.ok){
        bootstrap.Modal.getInstance(qs('#editModal')).hide();
        // refresh current view
        refreshAll();
      } else {
        alert('Error al guardar');
      }
    });
  });

  // Init
  function init(){
    // build mode switch
    qsa('#modeSwitch [data-mode]').forEach(btn=>{
      btn.addEventListener('click', ()=>switchMode(btn.dataset.mode));
    });
    // init data
    api('prestaciones_init.php',{}).then(j=>{
      if(!j.ok){ throw new Error('init'); }
      state.stations = j.stations||[];
      state.personas = j.personas||[];
      state.years = j.years||[];
      buildStations();
      buildSelectors();
      switchMode('persona');
      bindRowEvents();
    }).catch(err=>{
      console.error(err);
      alert('No se pudo inicializar la página.');
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
