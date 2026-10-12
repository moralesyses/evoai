/*
 * Management Admin > Encashments (views/admin/encashments.php):
 *  - Release (with the payout reference) and Reject (with a reason; the
 *    amount goes back to the member's balance) for one request;
 *  - ticked requests (one page, at most 25): Release selected (a reference
 *    each, or one for all), Reject selected (one reason), Download selected CSV;
 *  - Download all as CSV: every request matching the filters.
 */
(function () {
  'use strict';
  var root = document.getElementById('admin-encashments');
  if (!root) return;
  var current = null; // the request whose single Release / Reject modal is open
  var bulk = []; // the pending requests in the open bulk modal

  function item(row) {
    return JSON.parse(row.getAttribute('data-en-item'));
  }
  function peso(n) {
    return EVO.fmt.peso(Math.round(n * 100) / 100);
  }

  /* ---------------- One request ---------------- */

  function summary(modal, r) {
    modal.querySelector('[data-en-summary]').innerHTML =
      '<div class="flex items-center justify-between gap-3"><span class="text-zinc-400">Member</span><span class="text-zinc-200 font-semibold text-right">' + EVO.esc(r.member) + '</span></div>' +
      '<div class="flex items-center justify-between gap-3"><span class="text-zinc-400">Head</span><span class="font-mono text-zinc-300">' + EVO.esc(r.head) + ' · @' + EVO.esc(r.username) + '</span></div>' +
      '<div class="flex items-center justify-between gap-3"><span class="text-zinc-400">Request</span><span class="font-mono text-zinc-300">' + EVO.esc(r.id) + '</span></div>' +
      '<div class="flex items-center justify-between gap-3"><span class="text-zinc-400">' + EVO.esc(r.channel) + '</span><span class="text-white font-semibold text-right break-all">' + EVO.esc(r.details) + '</span></div>' +
      '<div class="flex items-center justify-between gap-3 pt-2 border-t border-zinc-800"><span class="text-zinc-400">' + EVO.esc(r.gross) + ' − ' + EVO.esc(r.tax) + ' tax − ' + EVO.esc(r.fee) + ' fee</span><span class="text-base font-black font-mono text-[#ffda72]">' + EVO.esc(r.net) + '</span></div>';
  }

  function openOne(kind, btn) {
    current = item(btn.closest('[data-en-row]'));
    var modal = document.querySelector('[data-modal="admin-en-' + kind + '"]');
    var form = modal.querySelector('form');
    form.reset();
    form.querySelector('[data-en-error]').hidden = true;
    summary(modal, current);
    var gross = modal.querySelector('[data-en-gross]');
    if (gross) gross.textContent = current.gross;
    EVO.openModal('admin-en-' + kind);
  }

  /* ---------------- Selection ---------------- */

  var boxes = EVO.qsa('[data-en-select]', root);
  var all = root.querySelector('[data-en-select-all]');
  var bar = root.querySelector('[data-en-bulk-bar]');

  function selected() {
    return boxes.filter(function (b) { return b.checked; }).map(function (b) { return item(b.closest('[data-en-row]')); });
  }

  function refresh() {
    var sel = selected();
    var pending = sel.filter(function (r) { return r.status === 'pending'; }).length;
    bar.hidden = sel.length === 0;
    bar.querySelector('[data-en-bulk-count]').textContent = sel.length;
    bar.querySelector('[data-en-bulk-pending]').textContent = sel.length ? ' · ' + pending + ' pending' + (pending < sel.length ? ' (only pending ones can be released or rejected)' : '') : '';
    EVO.qsa('[data-en-bulk]', bar).forEach(function (b) { b.disabled = pending === 0; });
    if (all) {
      all.checked = sel.length > 0 && sel.length === boxes.length;
      all.indeterminate = sel.length > 0 && sel.length < boxes.length;
    }
  }

  root.addEventListener('change', function (ev) {
    if (ev.target === all) {
      boxes.forEach(function (b) { b.checked = all.checked; });
      refresh();
    } else if (ev.target.matches('[data-en-select]')) {
      refresh();
    }
  });

  /* ---------------- Bulk modals ---------------- */

  function openBulk(kind) {
    var sel = selected();
    bulk = sel.filter(function (r) { return r.status === 'pending'; });
    if (!bulk.length) return;
    var modal = document.querySelector('[data-modal="admin-en-bulk-' + kind + '"]');
    var form = modal.querySelector('form');
    form.reset();
    form.querySelector('[data-en-error]').hidden = true;
    EVO.qsa('[data-en-bulk-n]', modal).forEach(function (el) { el.textContent = bulk.length; });
    var skipped = sel.length - bulk.length;
    var note = modal.querySelector('[data-en-bulk-skipped]');
    note.hidden = skipped === 0;
    note.textContent = skipped + ' selected request' + (skipped === 1 ? ' is' : 's are') + ' already processed and left out.';
    var total = bulk.reduce(function (sum, r) { return sum + (kind === 'release' ? r.netValue : r.grossValue); }, 0);
    modal.querySelector('[data-en-bulk-total]').textContent = peso(total);
    modal.querySelector('[data-en-bulk-list]').innerHTML = bulk
      .map(function (r) {
        var who = '<div class="min-w-0"><div class="text-zinc-200 font-semibold">' + EVO.esc(r.member) + ' <span class="font-mono text-[10px] text-zinc-500">' + EVO.esc(r.id) + '</span></div>' +
          '<div class="text-[11px] text-zinc-400 break-all">' + EVO.esc(r.channel) + ' · ' + EVO.esc(r.details) + '</div></div>';
        if (kind === 'reject') {
          return '<div class="px-3 py-2 flex items-center justify-between gap-3">' + who + '<span class="font-mono text-zinc-200 whitespace-nowrap">' + EVO.esc(r.gross) + '</span></div>';
        }
        return '<div class="px-3 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">' + who +
          '<div class="flex items-center gap-2 shrink-0"><span class="font-black font-mono text-[#ffda72] whitespace-nowrap">' + EVO.esc(r.net) + '</span>' +
          '<input type="text" data-en-bulk-ref="' + EVO.esc(r.id) + '" maxlength="100" autocomplete="off" placeholder="Payout reference" aria-label="Payout reference for ' + EVO.esc(r.id) + '" class="w-full sm:w-auto px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-xs text-white font-mono focus:outline-none focus:border-[#f3bd35]"></div></div>';
      })
      .join('');
    EVO.openModal('admin-en-bulk-' + kind);
  }

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-en-bulk-apply]')) return;
    var modal = ev.target.closest('[data-modal]');
    var same = modal.querySelector('[data-en-bulk-same]').value.trim();
    if (!same) {
      modal.querySelector('[data-en-bulk-same]').focus();
      return;
    }
    EVO.qsa('[data-en-bulk-ref]', modal).forEach(function (i) { i.value = same; });
  });

  /* ---------------- Clicks in the tab ---------------- */

  root.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.closest('[data-en-release]')) return openOne('release', t.closest('[data-en-release]'));
    if (t.closest('[data-en-reject]')) return openOne('reject', t.closest('[data-en-reject]'));
    var b = t.closest('[data-en-bulk]');
    if (b) return openBulk(b.getAttribute('data-en-bulk'));
    if (t.closest('[data-en-bulk-clear]')) {
      boxes.forEach(function (x) { x.checked = false; });
      return refresh();
    }
    var csv = t.closest('[data-en-bulk-csv], [data-en-export]');
    if (!csv) return;
    var data = csv.hasAttribute('data-en-bulk-csv')
      ? { ids: selected().map(function (r) { return r.id; }) }
      : { filter: JSON.parse(root.querySelector('[data-en-list]').getAttribute('data-filter')) };
    var restore = EVO.busy(csv, 'Preparing…');
    EVO.api('admin.encashExport', data)
      .then(function (res) {
        EVO.download(res.filename, '﻿' + res.csv, 'text/csv;charset=utf-8');
        EVO.toast('Downloaded ' + res.count + ' request' + (res.count === 1 ? '' : 's') + '.');
      })
      .catch(function (e) {
        EVO.toast(e.message, 'error');
      })
      .then(restore);
  });

  /* ---------------- Submitting ---------------- */

  function submit(form, action, payload) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var err = form.querySelector('[data-en-error]');
      var data = payload(form);
      if (!data) return;
      err.hidden = true;
      var restore = EVO.busy(form.querySelector('button[type="submit"]'), 'Saving…');
      EVO.api(action, data)
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (e) {
          restore();
          err.textContent = e.message;
          err.hidden = false;
        });
    });
  }
  submit(document.querySelector('[data-en-release-form]'), 'admin.encashRelease', function (f) {
    return current && { id: current.id, payoutReference: f.elements.payoutReference.value.trim(), note: f.elements.note.value.trim() };
  });
  submit(document.querySelector('[data-en-reject-form]'), 'admin.encashReject', function (f) {
    return current && { id: current.id, reason: f.elements.reason.value.trim() };
  });
  submit(document.querySelector('[data-en-bulk-release-form]'), 'admin.encashBulkRelease', function (f) {
    return bulk.length && {
      items: EVO.qsa('[data-en-bulk-ref]', f).map(function (i) { return { id: i.getAttribute('data-en-bulk-ref'), payoutReference: i.value.trim() }; }),
      note: f.elements.note.value.trim(),
    };
  });
  submit(document.querySelector('[data-en-bulk-reject-form]'), 'admin.encashBulkReject', function (f) {
    return bulk.length && { ids: bulk.map(function (r) { return r.id; }), reason: f.elements.reason.value.trim() };
  });
})();
