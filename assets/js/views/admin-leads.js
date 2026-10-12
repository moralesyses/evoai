/*
 * Management Admin > Global Leads CRM (reference component "Fn").
 * Client-side search + status filter, status select -> admin.leadStatus,
 * CSV export (all leads, same columns as the reference), chat -> E2EE Chat.
 */
(function () {
  'use strict';

  var root = document.getElementById('admin-leads');
  if (!root || !window.EVO) return;

  var leads = ((window.EVO_PAGE || {}).admin || {}).leads || [];
  var byId = {};
  leads.forEach(function (l) {
    byId[l.id] = l;
  });

  var SELECT_BASE = 'text-[10px] font-bold uppercase rounded-lg px-2 py-1 bg-zinc-900 border';
  function selectClass(status) {
    return status === 'converted' ? 'border-emerald-500/50 text-emerald-400' : status === 'contacted' ? 'border-sky-500/50 text-sky-400' : 'border-amber-500/50 text-amber-400';
  }

  /* ---------------- Filters ---------------- */
  var searchEl = EVO.qs('[data-lead-search]', root);
  var filter = 'all';

  function applyFilters() {
    var q = (searchEl ? searchEl.value : '').toLowerCase();
    EVO.qsa('[data-lead-row]', root).forEach(function (row) {
      var matchText = (row.getAttribute('data-search') || '').split('\n').some(function (field) {
        return field.indexOf(q) !== -1;
      });
      var matchStatus = filter === 'all' || row.getAttribute('data-status') === filter;
      row.hidden = !(matchText && matchStatus);
    });
  }
  if (searchEl) searchEl.addEventListener('input', applyFilters);

  /* ---------------- CSV export ---------------- */
  function cell(v) {
    var s = String(v == null ? '' : v);
    // Neutralise spreadsheet formulas (keep phone numbers such as "+63 917 ...").
    if (/^[=@\t\r]/.test(s) || (/^[+-]/.test(s) && !/^[+-][\d\s().-]*$/.test(s))) s = "'" + s;
    return '"' + s.replace(/"/g, '""') + '"';
  }
  function exportCsv() {
    var header = ['ID', 'Name', 'Email', 'Phone', 'Company', 'Interest', 'Message', 'Status', 'Date'];
    var rows = leads.map(function (l) {
      return [l.id, cell(l.name), cell(l.email), cell(l.phone), cell(l.company), cell(l.interest), cell(l.message), l.status, new Date(l.createdAt).toISOString()];
    });
    var csv = [header.join(',')].concat(rows.map(function (r) {
      return r.join(',');
    })).join('\n');
    EVO.download('88MMM_Circle_Leads_' + new Date().toISOString().slice(0, 10) + '.csv', csv, 'text/csv;charset=utf-8');
  }

  root.addEventListener('click', function (ev) {
    var f = ev.target.closest('[data-lead-filter]');
    if (f) {
      ev.preventDefault();
      filter = f.getAttribute('data-lead-filter');
      EVO.qsa('[data-lead-filter]', root).forEach(function (b) {
        var on = b === f;
        EVO.setActive(b, on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.removeAttribute('aria-selected');
      });
      applyFilters();
      return;
    }
    if (ev.target.closest('[data-lead-export]')) {
      ev.preventDefault();
      exportCsv();
      return;
    }
    if (ev.target.closest('[data-lead-chat]')) {
      ev.preventDefault();
      EVO.go('chat');
    }
  });

  /* ---------------- Status ---------------- */
  root.addEventListener('change', function (ev) {
    var sel = ev.target.closest('[data-lead-status]');
    if (!sel) return;
    var id = sel.getAttribute('data-lead-status');
    var prev = sel.getAttribute('data-current');
    var status = sel.value;
    var row = sel.closest('[data-lead-row]');
    sel.className = SELECT_BASE + ' ' + selectClass(status);
    sel.disabled = true;
    EVO.api('admin.leadStatus', { id: id, status: status })
      .then(function () {
        sel.setAttribute('data-current', status);
        if (row) row.setAttribute('data-status', status);
        if (byId[id]) byId[id].status = status;
        applyFilters();
      })
      .catch(function (err) {
        sel.value = prev;
        sel.className = SELECT_BASE + ' ' + selectClass(prev);
        EVO.toast(err.message, 'error');
      })
      .then(function () {
        sel.disabled = false;
      });
  });
})();
