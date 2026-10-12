/*
 * Management Admin > Online Payments & Ledger (reference component "Rn").
 * Client-side gateway/status/search filters, receipt modal with print,
 * refund / approve -> admin.paymentStatus, invoice modal -> admin.invoice.
 */
(function () {
  'use strict';

  var root = document.getElementById('admin-payments');
  if (!root || !window.EVO) return;

  var payments = ((window.EVO_PAGE || {}).admin || {}).payments || [];
  var byId = {};
  payments.forEach(function (p) {
    byId[p.id] = p;
  });

  /* ---------------- KPIs (same maths as the reference) ---------------- */
  function refreshKpis() {
    var done = payments.filter(function (p) {
      return p.status === 'completed';
    });
    var gross = done.reduce(function (sum, p) {
      return sum + Number(p.amount || 0);
    }, 0);
    var avg = done.length > 0 ? Math.round(gross / done.length) : 0;
    var set = function (key, text) {
      var el = EVO.qs('[data-pay-kpi="' + key + '"]', root);
      if (el) el.textContent = text;
    };
    set('gross', EVO.fmt.peso(gross));
    set('orders', String(done.length));
    set('avg', EVO.fmt.peso(avg));
  }

  /* ---------------- Filters ---------------- */
  var searchEl = EVO.qs('[data-pay-search]', root);
  var methodEl = EVO.qs('[data-pay-method]', root);
  var statusEl = EVO.qs('[data-pay-status]', root);

  function applyFilters() {
    var method = methodEl ? methodEl.value : 'all';
    var status = statusEl ? statusEl.value : 'all';
    var q = searchEl ? searchEl.value : '';
    var needle = q.toLowerCase();
    EVO.qsa('[data-pay-row]', root).forEach(function (row) {
      var show = true;
      if ((method !== 'all' && row.getAttribute('data-method') !== method) || (status !== 'all' && row.getAttribute('data-status') !== status)) show = false;
      if (show && q.trim()) {
        show = (row.getAttribute('data-search') || '').split('\n').some(function (field) {
          return field.indexOf(needle) !== -1;
        });
      }
      row.hidden = !show;
    });
  }
  if (searchEl) searchEl.addEventListener('input', applyFilters);
  if (methodEl) methodEl.addEventListener('change', applyFilters);
  if (statusEl) statusEl.addEventListener('change', applyFilters);

  /* ---------------- Status changes ---------------- */
  function renderStatus(row, status) {
    row.setAttribute('data-status', status);
    EVO.qsa('[data-pay-when]', row).forEach(function (el) {
      el.hidden = el.getAttribute('data-pay-when') !== status;
    });
  }

  /* ---------------- Receipt ---------------- */
  var receiptModal = EVO.qs('[data-modal="admin-pay-receipt"]');
  function rc(key, text) {
    var el = receiptModal && EVO.qs('[data-rc="' + key + '"]', receiptModal);
    if (el) el.textContent = text;
    return el;
  }
  function openReceipt(id) {
    var p = byId[id];
    if (!p || !receiptModal) return;
    rc('referenceNumber', p.referenceNumber);
    rc('amount', EVO.fmt.peso(p.amount) + ' PHP');
    rc('customerName', p.customerName);
    rc('contact', p.customerEmail || p.customerPhone || '');
    rc('paymentMethod', p.paymentMethod);
    rc('nfcCardSerial', p.nfcCardSerial || 'N/A');
    rc('date', EVO.fmt.datetime(p.timestamp));
    var note = rc('receiptNote', p.receiptNote || '');
    if (note) note.hidden = !p.receiptNote;
    EVO.openModal('admin-pay-receipt');
  }

  function printReceipt() {
    var box = receiptModal && EVO.qs('[data-pay-receipt-box]', receiptModal);
    if (!box) return window.print();
    var cleaned = false;
    function cleanup() {
      if (cleaned) return;
      cleaned = true;
      document.body.classList.remove('evo-printing');
      box.removeAttribute('data-print-area');
      window.removeEventListener('afterprint', cleanup);
    }
    document.body.classList.add('evo-printing');
    box.setAttribute('data-print-area', '');
    window.addEventListener('afterprint', cleanup);
    window.print();
    setTimeout(cleanup, 1000);
  }

  root.addEventListener('click', function (ev) {
    var recBtn = ev.target.closest('[data-pay-receipt]');
    if (recBtn) {
      ev.preventDefault();
      openReceipt(recBtn.getAttribute('data-pay-receipt'));
      return;
    }
    if (ev.target.closest('[data-pay-print]')) {
      ev.preventDefault();
      printReceipt();
      return;
    }
    var setBtn = ev.target.closest('[data-pay-set]');
    if (setBtn) {
      ev.preventDefault();
      var row = setBtn.closest('[data-pay-row]');
      var id = row && row.getAttribute('data-pay-row');
      var status = setBtn.getAttribute('data-pay-set');
      if (!id) return;
      setBtn.disabled = true;
      EVO.api('admin.paymentStatus', { id: id, status: status })
        .then(function () {
          if (byId[id]) byId[id].status = status;
          renderStatus(row, status);
          refreshKpis();
          applyFilters();
        })
        .catch(function (err) {
          EVO.toast(err.message, 'error');
        })
        .then(function () {
          setBtn.disabled = false;
        });
    }
  });

  /* ---------------- Invoice ---------------- */
  var invForm = document.getElementById('pay-invoice-form');
  var invModal = EVO.qs('[data-modal="admin-pay-invoice"]');
  var invDone = invModal && EVO.qs('[data-invoice-done]', invModal);
  var invLink = invModal && EVO.qs('[data-invoice-link]', invModal);
  var invoiceCreated = false;

  if (invForm) {
    invForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var data = EVO.formData(invForm);
      var btn = invForm.querySelector('button[type="submit"]');
      var restore = EVO.busy(btn);
      EVO.api('admin.invoice', {
        clientName: data.clientName,
        clientEmail: data.clientEmail,
        item: data.item,
        amount: Number(data.amount),
      })
        .then(function (res) {
          restore();
          invoiceCreated = true;
          if (invLink) invLink.value = (res.payment && res.payment.invoiceLink) || '';
          invForm.hidden = true;
          if (invDone) invDone.hidden = false;
        })
        .catch(function (err) {
          restore();
          EVO.toast(err.message, 'error');
        });
    });
  }

  if (invModal) {
    EVO.qs('[data-invoice-copy]', invModal).addEventListener('click', function () {
      var link = invLink ? invLink.value : '';
      EVO.copy(link)
        .catch(function () {})
        .then(function () {
          EVO.flash('Invoice link copied to clipboard.');
          EVO.closeModal(invModal);
        });
    });
  }

  // Closing the invoice modal resets it (reference: d(false), p('')); after an
  // invoice was issued, reload so the new pending ledger row is listed.
  document.addEventListener('evo:modal-close', function (ev) {
    if (!ev.detail || ev.detail.id !== 'admin-pay-invoice') return;
    if (invoiceCreated) {
      window.location.reload();
      return;
    }
    if (invForm) invForm.hidden = false;
    if (invDone) invDone.hidden = true;
  });
})();
