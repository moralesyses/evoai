/*
 * Backoffice tab 4 — Transactions ledger (reference component "On").
 * Type pills + search filter the server-rendered rows and recompute the
 * filtered inflow / outflow / net totals; "Print Ledger" prints the page.
 */
(function () {
  'use strict';

  var wrap = document.querySelector('[data-bo-tx]');
  if (!wrap) return;

  var tbody = wrap.querySelector('[data-tx-body]');
  var rows = EVO.qsa('tr[data-tx-row]', tbody);
  var empty = tbody.querySelector('[data-tx-empty]');
  var search = wrap.querySelector('[data-tx-search]');
  var inEl = wrap.querySelector('[data-tx-in]');
  var outEl = wrap.querySelector('[data-tx-out]');
  var netEl = wrap.querySelector('[data-tx-net]');
  var type = 'all';

  function apply() {
    var raw = search ? search.value : '';
    var q = raw.trim() ? raw.toLowerCase() : '';
    var inflow = 0;
    var outflow = 0;
    var shown = [];
    var hidden = [];
    rows.forEach(function (r) {
      var ok = type === 'all' || r.getAttribute('data-type') === type;
      if (ok && q) {
        ok = (r.getAttribute('data-search') || '').split('\n').some(function (f) {
          return f.indexOf(q) !== -1;
        });
      }
      r.hidden = !ok;
      (ok ? shown : hidden).push(r);
      if (!ok) return;
      var amount = parseFloat(r.getAttribute('data-amount')) || 0;
      if (r.getAttribute('data-dir') === 'in') inflow += amount;
      else if (r.getAttribute('data-dir') === 'out') outflow += amount;
    });
    empty.hidden = shown.length > 0;
    if (shown.length) hidden.push(empty);
    else shown.push(empty);
    // Hidden rows first so the last visible row stays :last-child (divide-y borders).
    hidden.concat(shown).forEach(function (r) {
      tbody.appendChild(r);
    });
    inEl.textContent = '+' + EVO.fmt.peso(inflow) + ' PHP';
    outEl.textContent = '-' + EVO.fmt.peso(outflow) + ' PHP';
    netEl.textContent = EVO.fmt.peso(inflow - outflow) + ' PHP';
  }

  if (search) search.addEventListener('input', apply);

  wrap.addEventListener('click', function (ev) {
    var pill = ev.target.closest('[data-tx-type]');
    if (pill) {
      type = pill.getAttribute('data-tx-type');
      EVO.qsa('[data-tx-type]', wrap).forEach(function (b) {
        var on = b === pill;
        EVO.setActive(b, on);
        b.removeAttribute('aria-selected');
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      apply();
      return;
    }
    if (ev.target.closest('[data-tx-print]')) window.print();
  });
})();
