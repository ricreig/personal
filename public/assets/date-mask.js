(function(){
  function normalizeDigits(value){
    const str = String(value || '').trim();
    if (!str) return '';
    const iso = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str);
    if (iso) {
      return `${iso[3]}${iso[2]}${iso[1]}`;
    }
    return str.replace(/\D+/g, '');
  }

  function expandYear(twoDigits){
    if (!twoDigits) return '';
    if (twoDigits.length >= 4) return twoDigits.slice(0, 4);
    if (twoDigits.length === 3) return twoDigits;
    const n = parseInt(twoDigits, 10);
    if (!Number.isFinite(n)) return '';
    return (n >= 50 ? '19' : '20') + twoDigits.padStart(2, '0');
  }

  function formatDigits(digits){
    if (!digits) return '';
    const clean = digits.replace(/[^0-9]/g, '').slice(0, 8);
    const day = clean.slice(0, 2);
    const month = clean.slice(2, 4);
    let year = clean.slice(4);
    if (year.length === 2) {
      year = expandYear(year);
    } else if (year.length === 3) {
      year = '2' + year.slice(0, 3);
    } else if (year.length > 4) {
      year = year.slice(0, 4);
    }
    const parts = [];
    if (day) parts.push(day);
    if (month) parts.push(month);
    if (year) parts.push(year);
    return parts.join('/');
  }

  function applyMaskToInput(input){
    if (!input || input.dataset.dateMaskApplied) return;
    input.dataset.dateMaskApplied = '1';
    input.setAttribute('autocomplete', input.getAttribute('autocomplete') || 'off');

    const handleInput = () => {
      const normalized = normalizeDigits(input.value);
      const formatted = formatDigits(normalized);
      input.value = formatted;
      if (input === document.activeElement && typeof input.setSelectionRange === 'function') {
        const len = input.value.length;
        input.setSelectionRange(len, len);
      }
    };

    const handlePaste = (event) => {
      const data = event.clipboardData || window.clipboardData;
      if (!data) return;
      event.preventDefault();
      const digits = normalizeDigits(data.getData('text'));
      input.value = formatDigits(digits);
      handleInput();
    };

    input.addEventListener('input', handleInput);
    input.addEventListener('change', handleInput);
    input.addEventListener('blur', handleInput);
    input.addEventListener('paste', handlePaste);

    handleInput();
  }

  function init(scope){
    const root = scope && scope.querySelectorAll ? scope : document;
    const targets = root.querySelectorAll('input[data-mask="date"], input.js-date-mask, input[data-date-mask]');
    targets.forEach(applyMaskToInput);
  }

  if (document.readyState !== 'loading') {
    init(document);
  } else {
    document.addEventListener('DOMContentLoaded', () => init(document));
  }

  window.applyDateMask = init;
})();
