(function(){
  if (window.__BTN_EXPIRED_WIRED__) return;
  window.__BTN_EXPIRED_WIRED__ = true;

  function ensureBtn(){
    var wrap = document.querySelector('.dataTables_filter');
    if (!wrap) return null;
    var btn = document.getElementById('btnOnlyExpiredLic');
    if (btn) return btn;
    btn = document.createElement('button');
    btn.id = 'btnOnlyExpiredLic';
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-danger ms-2';
    btn.textContent = 'Vencidos';
    wrap.appendChild(btn);
    return btn;
  }
  function toggle(){
    if (!window.ACTIVE_FILTERS || typeof window.ACTIVE_FILTERS !== 'object') window.ACTIVE_FILTERS = {};
    var cur = Number(window.ACTIVE_FILTERS.onlyExpiredLic || 0);
    var next = cur ? 0 : 1;
    window.ACTIVE_FILTERS.onlyExpiredLic = next;
    var btn = document.getElementById('btnOnlyExpiredLic');
    if (btn){
      if (next){ btn.classList.add('active'); btn.classList.remove('btn-outline-danger'); btn.classList.add('btn-danger'); }
      else     { btn.classList.remove('active'); btn.classList.add('btn-outline-danger'); btn.classList.remove('btn-danger'); }
    }
    if (window.EMP_TBL && typeof window.EMP_TBL.ajax === 'function') window.EMP_TBL.ajax.reload();
  }
  function boot(){
    var btn = ensureBtn();
    if (!btn) return;
    btn.addEventListener('click', toggle, { once:false });
  }
  if (document.readyState === 'complete' || document.readyState === 'interactive') setTimeout(boot, 0);
  else document.addEventListener('DOMContentLoaded', boot);
})();
