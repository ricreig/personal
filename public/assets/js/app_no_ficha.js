/* app_no_ficha.js — desactiva por completo la “Ficha” (popover) y cualquier popover Bootstrap */
(function(){
  function killAllPopovers(){
    // Quitar instancias activas y atributos
    if (window.bootstrap && window.bootstrap.Popover) {
      document.querySelectorAll('[data-bs-toggle="popover"],[data-bs-trigger="focus"],[data-ficha]').forEach(function(el){
        try {
          var inst = bootstrap.Popover.getInstance(el);
          if (inst) inst.dispose();
        } catch (e) {}
        el.removeAttribute('data-bs-toggle');
        el.removeAttribute('data-bs-trigger');
        el.removeAttribute('data-bs-content');
        el.removeAttribute('title');
      });
    }
    // Remover popovers ya insertados en el DOM
    document.querySelectorAll('.popover, .popover.show, .popover.fade').forEach(function(p){ p.remove(); });
  }

  // 1) Bloqueo global: cancela cualquier intento de mostrar popovers
  document.addEventListener('show.bs.popover', function(ev){
    ev.preventDefault();
    ev.stopImmediatePropagation();
  }, true);

  // 2) Cancela clicks en triggers típicos (por si los re-crea app.js)
  document.addEventListener('click', function(ev){
    var t = ev.target.closest('[data-bs-toggle="popover"], .btn-ficha, [data-ficha]');
    if (t){
      ev.preventDefault();
      ev.stopImmediatePropagation();
    }
  }, true);

  // 3) Parchea Bootstrap para que Popover.prototype.show no haga nada
  function monkeyPatch(){
    if (window.bootstrap && window.bootstrap.Popover && !window.__POPOVER_PATCHED__) {
      try {
        var Pop = bootstrap.Popover;
        if (Pop && Pop.prototype && typeof Pop.prototype.show === 'function') {
          var _old = Pop.prototype.show;
          Pop.prototype.show = function(){ /* no-op: bloqueado */ };
          window.__POPOVER_PATCHED__ = true;
        }
      } catch(e){}
    }
  }

  // 4) Observa cambios en el DOM (DataTables redraw) y vuelve a limpiar
  var mo = new MutationObserver(function(){ killAllPopovers(); monkeyPatch(); });
  mo.observe(document.documentElement || document.body, {childList:true, subtree:true});

  // 5) Primera pasada (cuando el DOM esté listo)
  function boot(){ killAllPopovers(); monkeyPatch(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();

  // 6) Reintentos breves por si app.js inicializa más tarde
  var tries = 0, iv = setInterval(function(){
    killAllPopovers(); monkeyPatch();
    if (++tries > 10) clearInterval(iv);
  }, 500);
})();