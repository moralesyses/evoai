/*
 * Digital Card CMS > Your .com Domain (views/backoffice/domains.php): search
 * and Buy, Renew, auto-renew, and which domain the card uses as its address.
 * Buying or renewing opens the payment page (Maya Checkout, or the test page
 * when PAYMENT_DRIVER=mock).
 */
(function () {
  'use strict';
  var root = document.getElementById('bo-domains');
  if (!root) return;
  var form = root.querySelector('[data-dom-search]');
  var results = root.querySelector('[data-dom-results]');

  // Only free names come back (taken ones are never offered).
  function render(res) {
    var rows = res.results || [];
    var notes = [];
    if (res.exactYours) notes.push('You already own ' + EVO.esc(res.query) + '.');
    else if (res.exactTaken) notes.push(EVO.esc(res.query) + ' is already taken.');
    if (rows.length) {
      var valued = rows.some(function (r) { return r.priceBasis === 'value'; });
      notes.push((res.exactTaken || res.exactYours ? 'Available alternatives.' : rows.length + ' available.') +
        (res.appraisal ? ' Each name is priced at its market value (GoDaddy GoValue estimate), never below the ' + EVO.esc(EVO.fmt.peso(res.cost)) + ' registration cost.' : ' ' + EVO.esc(EVO.fmt.peso(res.cost)) + ' for the first year.') +
        (res.appraisal && !valued ? ' None of these has a higher estimate.' : ''));
    } else {
      notes.push('None of the ' + (res.checked || 0) + ' names we checked are available. Try another name.');
    }
    if (res.unverified) notes.push(res.unverified + ' more could not be checked with the .com registry right now; search again in a minute to see them.');
    if (rows.length && res.priceCheck === 'verified') notes.push('Registration cost checked today against ' + res.priceSources + ' market sources.');
    results.innerHTML =
      '<p class="text-[11px] text-zinc-400">' + notes.join(' ') + '</p>' +
      rows
        .map(function (r) {
          return (
            '<div class="px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-emerald-500/30 flex items-center justify-between gap-3">' +
            '<div class="min-w-0 flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-white break-all">' + EVO.esc(r.domain) + '</span>' +
            '<span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider border bg-emerald-500/10 border-emerald-500/30 text-emerald-300">Available</span>' +
            (r.priceBasis === 'value'
              ? '<span class="text-[10px] px-2 py-0.5 rounded-full font-bold border bg-[#f3bd35]/15 border-[#f3bd35]/40 text-[#ffda72]" title="' +
                EVO.esc('GoDaddy GoValue estimate: $' + Number(r.valueUsd).toLocaleString('en-US') + (r.valueRange ? ' (range $' + Number(r.valueRange[0]).toLocaleString('en-US') + '–$' + Number(r.valueRange[1]).toLocaleString('en-US') + ')' : '') + ' at today\'s rate') +
                '">Market value</span>'
              : '<span class="text-[10px] px-2 py-0.5 rounded-full font-bold border bg-zinc-900 border-zinc-700 text-zinc-400" title="' + EVO.esc(r.priceNote || '') + '">Registration price</span>') +
            '</div>' +
            '<button type="button" data-dom-buy="' + EVO.esc(r.domain) + '" data-dom-price="' + EVO.esc(String(r.price)) + '" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#f3bd35] hover:bg-[#ffda72] text-black text-xs font-bold transition-colors whitespace-nowrap">' +
            EVO.icon('lock', 'w-3.5 h-3.5') + '<span>Buy · ' + EVO.esc(EVO.fmt.peso(r.price)) + '</span></button>' +
            '</div>'
          );
        })
        .join('');
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, 'Searching…');
    results.innerHTML = '<p class="text-xs text-zinc-400">Checking names…</p>';
    EVO.api('domains.search', { query: form.elements.query.value })
      .then(render)
      .catch(function (e) {
        results.innerHTML = '';
        EVO.toast(e.message, 'error');
      })
      .then(restore);
  });

  // Buy / Renew → the payment page. A changed price comes back first and is shown on the button.
  function pay(btn, call) {
    var restore = EVO.busy(btn, 'Opening payment…');
    call
      .then(function (res) {
        if (res.priceChanged) {
          restore();
          btn.setAttribute('data-dom-price', String(res.price));
          btn.querySelector('span').textContent = 'Buy · ' + EVO.fmt.peso(res.price);
          EVO.toast(res.message);
          return;
        }
        window.location.href = res.redirectUrl;
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  }

  root.addEventListener('click', function (ev) {
    var buy = ev.target.closest('[data-dom-buy]');
    if (buy) {
      pay(buy, EVO.api('domains.buy', { domain: buy.getAttribute('data-dom-buy'), price: Number(buy.getAttribute('data-dom-price')) }));
      return;
    }
    var renew = ev.target.closest('[data-dom-renew]');
    if (renew) {
      pay(renew, EVO.api('domains.renew', { id: Number(renew.getAttribute('data-dom-renew')) }));
      return;
    }
    var card = ev.target.closest('[data-dom-card]');
    if (card) {
      var restore = EVO.busy(card, 'Saving…');
      EVO.api('domains.useForCard', { id: Number(card.getAttribute('data-dom-card')) })
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (e) {
          restore();
          EVO.toast(e.message, 'error');
        });
    }
  });

  root.addEventListener('change', function (ev) {
    var box = ev.target.closest('[data-dom-autorenew]');
    if (!box) return;
    var on = box.checked;
    box.disabled = true;
    EVO.api('domains.autoRenew', { id: Number(box.getAttribute('data-dom-autorenew')), on: on })
      .then(function () {
        EVO.toast(on ? 'Auto-renew is on: it is paid from your available balance before the domain expires.' : 'Auto-renew is off.');
      })
      .catch(function (e) {
        box.checked = !on;
        EVO.toast(e.message, 'error');
      })
      .then(function () {
        box.disabled = false;
      });
  });
})();
