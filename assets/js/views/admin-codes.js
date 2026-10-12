/*
 * Management Admin > Activation Codes: generate codes (admin.codesGenerate),
 * verify a code and PIN (admin.codeCheck), and in the code logs reveal PINs
 * and activate / deactivate unused codes, one at a time (admin.codeStatus) or
 * several at once: the ticked ones or every matching one (admin.codesStatus).
 * The same selection (used codes included) downloads as CSV (admin.codesExport).
 */
(function () {
  'use strict';

  var root = document.getElementById('admin-codes');
  if (!root || !window.EVO) return;
  var esc = EVO.esc;

  /* ---------------- Generate ---------------- */
  var genForm = document.getElementById('cd-gen-form');
  var genMsg = EVO.qs('[data-cd-gen-msg]', root);
  var result = EVO.qs('[data-cd-result]', root);
  var last = null;
  var PER_PAGE = 25;
  var resultPage = 1;

  // The new batch, 25 codes per page.
  function renderResult() {
    var pages = Math.max(1, Math.ceil(last.codes.length / PER_PAGE));
    resultPage = Math.min(Math.max(1, resultPage), pages);
    EVO.qs('[data-cd-result-rows]', root).innerHTML = last.codes
      .slice((resultPage - 1) * PER_PAGE, resultPage * PER_PAGE)
      .map(function (c) {
        return '<tr><td class="py-1.5 px-3 text-zinc-400">' + esc(c.serial) + '</td><td class="py-1.5 px-3">' + esc(c.code) + '</td><td class="py-1.5 px-3">' + esc(c.pin) + '</td></tr>';
      })
      .join('');
    var pager = EVO.qs('[data-cd-result-pager]', root);
    pager.hidden = pages < 2;
    EVO.qs('[data-cd-result-page]', pager).textContent = 'Page ' + resultPage + ' of ' + pages;
    EVO.qs('[data-cd-result-prev]', pager).disabled = resultPage <= 1;
    EVO.qs('[data-cd-result-next]', pager).disabled = resultPage >= pages;
  }

  function showGenError(text) {
    genMsg.textContent = text || '';
    genMsg.hidden = !text;
  }

  genForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    showGenError('');
    var f = genForm.elements;
    var btn = genForm.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>Generating…</span>');
    EVO.api('admin.codesGenerate', { package: f.package.value, quantity: f.quantity.value, owner: f.owner.value, status: f.status.value })
      .then(function (res) {
        restore();
        last = { codes: res.codes, owner: res.owner, package: f.package.options[f.package.selectedIndex].text, status: f.status.value };
        EVO.qs('[data-cd-result-title]', root).textContent = res.message;
        resultPage = 1;
        renderResult();
        result.hidden = false;
        EVO.toast(res.message);
      })
      .catch(function (err) {
        restore();
        showGenError(err.message);
      });
  });

  root.addEventListener('click', function (ev) {
    if ((ev.target.closest('[data-cd-result-prev]') || ev.target.closest('[data-cd-result-next]')) && last) {
      resultPage += ev.target.closest('[data-cd-result-next]') ? 1 : -1;
      renderResult();
      return;
    }
    if (ev.target.closest('[data-cd-copy]') && last) {
      var text = last.codes
        .map(function (c) {
          return c.serial + '  ' + c.code + '  PIN ' + c.pin;
        })
        .join('\n');
      EVO.copy(text).then(function () {
        EVO.toast('Copied ' + last.codes.length + ' codes with their PINs.');
      });
      return;
    }
    if (ev.target.closest('[data-cd-download]') && last) {
      var csv = ['serial,code,pin,package,status,owner_account,owner_username']
        .concat(
          last.codes.map(function (c) {
            return [c.serial, c.code, c.pin, '"' + last.package.replace(/"/g, '""') + '"', last.status, last.owner.id, last.owner.username].join(',');
          })
        )
        .join('\r\n');
      EVO.download('activation-codes-' + new Date().toISOString().slice(0, 10) + '.csv', csv, 'text/csv;charset=utf-8');
      return;
    }
    // Reveal / hide one PIN in the logs.
    var pin = ev.target.closest('[data-cd-pin]');
    if (pin) {
      var shown = pin.getAttribute('data-shown') === '1';
      pin.textContent = shown ? '••••••' : pin.getAttribute('data-cd-pin');
      pin.setAttribute('data-shown', shown ? '0' : '1');
      return;
    }
    // Activate / deactivate one unused code.
    var toggle = ev.target.closest('[data-cd-status]');
    if (toggle) {
      var restore = EVO.busy(toggle, '…');
      EVO.api('admin.codeStatus', { code: toggle.getAttribute('data-cd-code'), status: toggle.getAttribute('data-cd-status') })
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (err) {
          restore();
          EVO.toast(err.message, 'error');
        });
    }
  });

  /* ---------------- Select several codes, then activate / deactivate them ---------------- */
  var logs = EVO.qs('[data-cd-logs]', root);
  var bar = EVO.qs('[data-cd-bulk-bar]', root);
  var selectAll = EVO.qs('[data-cd-select-all]', root);
  var matchBtn = EVO.qs('[data-cd-select-matching]', root);
  // 'page' = the ticked codes; 'matching' = every code that matches the filters, on all pages
  // (activate / deactivate leave used codes as they are).
  var scope = 'page';

  function boxes() {
    return EVO.qsa('[data-cd-select]', root);
  }
  function ticked() {
    return boxes().filter(function (b) {
      return b.checked;
    });
  }
  function refreshBulk() {
    if (!bar) return;
    var all = boxes();
    var on = ticked().length;
    var matching = parseInt(logs.getAttribute('data-selectable-total'), 10) || 0;
    var everyOnPage = all.length > 0 && on === all.length;
    if (!everyOnPage) scope = 'page';
    bar.hidden = on === 0;
    EVO.qs('[data-cd-bulk-count]', bar).textContent = scope === 'matching' ? matching : on;
    EVO.qs('[data-cd-bulk-scope]', bar).textContent =
      scope === 'matching' ? ' (every code that matches, on all pages)' : everyOnPage && matching > all.length ? ' on this page.' : '';
    if (matchBtn) matchBtn.hidden = !(scope === 'page' && everyOnPage && matching > all.length);
    if (selectAll) {
      selectAll.checked = everyOnPage;
      selectAll.indeterminate = on > 0 && !everyOnPage;
    }
  }

  root.addEventListener('change', function (ev) {
    if (ev.target === selectAll) {
      boxes().forEach(function (b) {
        b.checked = selectAll.checked;
      });
      scope = 'page';
      refreshBulk();
      return;
    }
    if (ev.target.matches('[data-cd-select]')) {
      scope = 'page';
      refreshBulk();
    }
  });

  // The ticked codes, or the logs filters when every matching code is selected.
  function selection() {
    if (scope === 'matching') {
      return {
        filter: {
          q: logs.getAttribute('data-filter-q'),
          status: logs.getAttribute('data-filter-status'),
          package: logs.getAttribute('data-filter-package'),
        },
      };
    }
    return {
      codes: ticked().map(function (b) {
        return b.value;
      }),
    };
  }

  if (bar) {
    bar.addEventListener('click', function (ev) {
      var csvBtn = ev.target.closest('[data-cd-bulk-csv]');
      if (csvBtn) {
        var restoreCsv = EVO.busy(csvBtn, EVO.icon('refresh-cw', 'w-3 h-3 animate-spin') + '<span>Preparing…</span>');
        EVO.api('admin.codesExport', selection())
          .then(function (res) {
            restoreCsv();
            // The byte-order mark makes spreadsheet apps read names and ₱ as UTF-8.
            EVO.download(res.filename, '﻿' + res.csv, 'text/csv;charset=utf-8');
            EVO.toast(res.message);
          })
          .catch(function (err) {
            restoreCsv();
            EVO.toast(err.message, 'error');
          });
        return;
      }
      if (ev.target.closest('[data-cd-select-matching]')) {
        scope = 'matching';
        refreshBulk();
        return;
      }
      if (ev.target.closest('[data-cd-bulk-clear]')) {
        boxes().forEach(function (b) {
          b.checked = false;
        });
        scope = 'page';
        refreshBulk();
        return;
      }
      var action = ev.target.closest('[data-cd-bulk]');
      if (!action) return;
      var payload = selection();
      payload.status = action.getAttribute('data-cd-bulk');
      EVO.qsa('[data-cd-bulk]', bar).forEach(function (b) {
        b.disabled = true;
      });
      var restore = EVO.busy(action, EVO.icon('refresh-cw', 'w-3 h-3 animate-spin') + '<span>Saving…</span>');
      EVO.api('admin.codesStatus', payload)
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (err) {
          restore();
          EVO.qsa('[data-cd-bulk]', bar).forEach(function (b) {
            b.disabled = false;
          });
          EVO.toast(err.message, 'error');
        });
    });
  }
  refreshBulk();

  /* ---------------- Verify ---------------- */
  var checkForm = document.getElementById('cd-check-form');
  var checkOut = EVO.qs('[data-cd-check-result]', root);

  function row(label, value) {
    return '<div><dt class="text-zinc-500">' + esc(label) + '</dt><dd class="text-zinc-200 break-words">' + value + '</dd></div>';
  }

  function render(res, code) {
    if (!res.found) {
      checkOut.className = 'p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 space-y-3 text-xs';
      checkOut.innerHTML =
        '<p class="font-bold text-rose-300 flex items-center gap-1.5">' + EVO.icon('circle-alert', 'w-4 h-4') + 'Not a valid code</p>' +
        '<p class="text-zinc-300">No activation code <span class="font-mono">' + esc(code) + '</span> exists. Check for typing mistakes.</p>';
      checkOut.hidden = false;
      return;
    }
    var c = res.code;
    var used = c.status === 'used';
    var inactive = c.status === 'inactive';
    var pinOk = c.pinMatches;
    var headline = used
      ? 'Already used'
      : pinOk === false
        ? 'Genuine code, but the PIN is wrong'
        : inactive
          ? 'Inactive: it can’t be used until you activate it'
          : 'Active and ready to use';
    var tone = used || inactive || pinOk === false ? 'amber' : 'emerald';
    checkOut.className =
      'p-4 rounded-2xl space-y-3 text-xs ' +
      (tone === 'amber' ? 'bg-amber-950/40 border border-amber-600/40' : 'bg-emerald-500/10 border border-emerald-500/30');
    var html =
      '<p class="font-bold flex items-center gap-1.5 ' + (tone === 'amber' ? 'text-amber-200' : 'text-emerald-300') + '">' +
      EVO.icon(tone === 'amber' ? 'circle-alert' : 'circle-check', 'w-4 h-4') + esc(headline) + '</p>' +
      '<dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[11px]">' +
      row('Serial', '<span class="font-mono">' + esc(c.serial) + '</span>') +
      row('Code', '<span class="font-mono">' + esc(c.code) + '</span>') +
      row('Status', esc(c.statusLabel)) +
      row('Package', esc(c.packageLabel) + ' · ' + esc(EVO.fmt.peso(c.value))) +
      row('Owner', esc(c.owner.name) + ' <span class="font-mono text-zinc-500">@' + esc(c.owner.username) + ' · ' + esc(c.owner.id) + '</span>') +
      row('Generated', esc(c.generatedDate) + ' · ' + esc(c.issuedBy)) +
      row('PIN', pinOk === null ? '<span class="text-zinc-500">Not checked</span>' : pinOk ? '<span class="text-emerald-300">Correct</span>' : '<span class="text-rose-300">Wrong</span>');
    if (used) {
      html += row('Used by', esc(c.usedBy || '') + (c.usedByAccount ? ' <span class="text-zinc-500">(' + esc(c.usedByAccount.email) + ')</span>' : '') + (c.usedAt ? '<br><span class="text-zinc-500">' + esc(c.usedAt) + '</span>' : ''));
    } else if (c.lastTransfer) {
      html += row('Last transfer', 'To @' + esc(c.lastTransfer.to) + (c.lastTransfer.at ? '<br><span class="text-zinc-500">' + esc(c.lastTransfer.at) + '</span>' : ''));
    }
    checkOut.innerHTML = html + '</dl>';
    checkOut.hidden = false;
  }

  checkForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var f = checkForm.elements;
    var code = f.code.value.trim().toUpperCase();
    if (!code) {
      f.code.focus();
      return;
    }
    var btn = checkForm.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>Checking…</span>');
    EVO.api('admin.codeCheck', { code: code, pin: f.pin.value })
      .then(function (res) {
        restore();
        render(res, code);
      })
      .catch(function (err) {
        restore();
        EVO.toast(err.message, 'error');
      });
  });
})();
