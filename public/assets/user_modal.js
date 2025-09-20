/* public/assets/user_modal.js */
(function(){
  function $(sel, ctx){ return (ctx||document).querySelector(sel); }
  function $all(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }

  async function fetchJSON(url){
    const r = await fetch(url, { credentials:'include' });
    if (!r.ok) throw new Error('HTTP '+r.status);
    return await r.json();
  }

  function badge(text){
    if (!text) return '<span class="badge bg-secondary">—</span>';
    return '<span class="badge bg-info">'+String(text)+'</span>';
  }

  function fillGenerales(emp){
    $('#um-title').textContent = emp.nombres || 'Trabajador';
    $('#um-subtitle').textContent = `#${emp.control||''} — ${emp.oaci||''}`;
    $('#um-edit-link').href = (window.BASE_URL||'/public/') + 'edit_persona.php?ctrl=' + encodeURIComponent(emp.control||'');
    $('#um-control').textContent = emp.control || '';
    $('#um-nombre').textContent = emp.nombres || '';
    $('#um-oaci').textContent   = emp.oaci || '';
    $('#um-espec').textContent  = emp.espec || '';
    $('#um-nivel').textContent  = emp.nivel || '';
    $('#um-plaza').textContent  = emp.plaza || '';
    $('#um-puesto').textContent = emp.puesto || '';
    $('#um-curp').textContent   = emp.curp || '';
    $('#um-nac').textContent    = emp.fecha_nacimiento || '';
    $('#um-ant').textContent    = emp.ant || '';
    $('#um-email').textContent  = emp.email || '';

    // Licencias (examen1 = Anexo, examen2 = Psicofísico)
    $('#um-lic-rtari-vig').textContent = emp.rtari_vig || '';
    $('#um-lic1-tipo').textContent = emp.tipo1 || '';
    $('#um-lic1-vig').textContent  = emp.vigencia1 || '';
    $('#um-lic2-tipo').textContent = emp.tipo2 || '';
    $('#um-lic2-vig').textContent  = emp.vigencia2 || '';
    $('#um-anexo').textContent     = emp.examen_vig1 || ''; // anexo
    $('#um-psico').textContent     = emp.examen_vig2 || ''; // psicofísico
  }

  function fillPecos(rows){
    const tbody = $('#um-pecos-table tbody'); tbody.innerHTML = '';
    rows.sort((a,b)=> (b.year||0)-(a.year||0));
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.year||''}</td>
        <td>${r.dia1||'0'}</td><td>${r.dia2||'0'}</td><td>${r.dia3||'0'}</td><td>${r.dia4||'0'}</td><td>${r.dia5||'0'}</td><td>${r.dia6||'0'}</td>
        <td>${r.dia7||'0'}</td><td>${r.dia8||'0'}</td><td>${r.dia9||'0'}</td><td>${r.dia10||'0'}</td><td>${r.dia11||'0'}</td><td>${r.dia12||'0'}</td>`;
      tbody.appendChild(tr);
    });
    if (!rows.length){
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="13" class="text-secondary">Sin registros.</td>`;
      tbody.appendChild(tr);
    }
  }

  function fillTxt(rows){
    const tbody = $('#um-txt-table tbody'); tbody.innerHTML = '';
    rows.sort((a,b)=> (b.year||0)-(a.year||0));
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.year||''}</td>
        <td>${r.js||'0'}</td><td>${r.vs||'0'}</td><td>${r.dm||'0'}</td><td>${r.ds||'0'}</td><td>${r.muert||'0'}</td><td>${r.ono||'0'}</td>`;
      tbody.appendChild(tr);
    });
    if (!rows.length){
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="7" class="text-secondary">Sin registros.</td>`;
      tbody.appendChild(tr);
    }
  }

  function fillVac(hist, resumen){
    const tbody = $('#um-vac-table tbody'); tbody.innerHTML = '';
    hist.sort((a,b)=> (b.year||0)-(a.year||0) || (a.periodo||0)-(b.periodo||0));
    hist.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.year||''}</td><td>${r.tipo||''}</td><td>${r.periodo||''}</td>
        <td>${r.inicia||''}</td><td>${r.reanuda||''}</td><td>${r.dias||''}</td><td>${r.obs||''}</td>`;
      tbody.appendChild(tr);
    });
    if (!hist.length){
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="7" class="text-secondary">Sin registros.</td>`;
      tbody.appendChild(tr);
    }

    const cards = $('#um-vac-cards'); cards.innerHTML = '';
    const items = [
      {k:'vac1', label:'1er Periodo', val: resumen?.vac1_rem},
      {k:'vac2', label:'2do Periodo', val: resumen?.vac2_rem},
      {k:'ant',  label:'Días Antigüedad', val: resumen?.ant_rem},
      {k:'pr',   label:'Periodo Recuperación', val: resumen?.pr_rem},
    ];
    items.forEach(it=>{
      const col = document.createElement('div');
      col.className = 'col-6';
      col.innerHTML = `
        <div class="p-3 rounded-3 border" style="background:#0f1522;border-color:rgba(255,255,255,.08)">
          <div class="small text-secondary">${it.label}</div>
          <div class="display-6">${(it.val??'—')}</div>
        </div>`;
      cards.appendChild(col);
    });
  }

  function fillInc(rows){
    const tbody = $('#um-inc-table tbody'); tbody.innerHTML = '';
    rows.sort((a,b)=> (b.INICIA||'').localeCompare(a.INICIA||''));
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.INICIA||''}</td><td>${r.TERMINA||''}</td><td>${r.DIAS||''}</td>
        <td>${r.UMF||''}</td><td>${r.DIAGNOSTICO||''}</td><td>${r.FOLIO||''}</td>`;
      tbody.appendChild(tr);
    });
    if (!rows.length){
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="6" class="text-secondary">Sin registros.</td>`;
      tbody.appendChild(tr);
    }
  }

  async function openUserModal(ctrl){
    try{
      const url = (window.API_BASE||'/api/') + 'user_full.php?ctrl=' + encodeURIComponent(ctrl);
      const data = await fetchJSON(url);
      if (!data || data.ok!==true) throw new Error('Sin datos');

      fillGenerales(data.empleado||{});
      fillPecos(data.pecos||[]);
      fillTxt(data.txt||[]);
      fillVac(data.vac_hist||[], data.vac_resumen||null);
      fillInc(data.incap||[]);

      // acciones
      const btnDel = $('#um-soft-delete');
      btnDel.onclick = async ()=>{
        if (!confirm('¿Mover a cambios_estacion y eliminar de empleados?')) return;
        const r = await fetch((window.API_BASE||'/api/') + 'empleado_soft_delete.php?ctrl='+encodeURIComponent(ctrl), {credentials:'include'});
        if (r.ok) {
          alert('Movido a cambios_estacion.');
          const m = bootstrap.Modal.getOrCreateInstance($('#userModal'));
          m.hide();
          document.dispatchEvent(new CustomEvent('user:deleted', { detail: ctrl }));
        } else {
          alert('No se pudo mover.');
        }
      };

      const m = bootstrap.Modal.getOrCreateInstance($('#userModal'));
      m.show();
    } catch(e){
      console.error(e);
      alert('No se pudo cargar la ficha.');
    }
  }

  // Exponer
  window.openUserModal = openUserModal;

  // Delegación por clase "js-open-user" data-ctrl
  document.addEventListener('click', (ev)=>{
    const a = ev.target.closest('.js-open-user[data-ctrl]');
    if (!a) return;
    ev.preventDefault();
    const ctrl = a.getAttribute('data-ctrl');
    if (ctrl) openUserModal(ctrl);
  });
})();
