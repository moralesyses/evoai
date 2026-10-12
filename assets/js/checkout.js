/*
 * 1EVOAI Online Payment Portal (checkout modal). Exposes EVO.openCheckout(itemType).
 * Maya is the only way to pay: submitting sends the customer to Maya Checkout's
 * hosted page, and the receipt is shown on the pay page once Maya confirms the
 * payment. Without Maya keys (data.maya.enabled false) nothing can be paid.
 */
(function () {
  'use strict';
  var modal = document.querySelector('[data-modal="checkout"]');
  if (!modal) return;
  var dataEl = document.getElementById('checkout-data');
  var data = dataEl ? JSON.parse(dataEl.textContent) : { items: {} };
  var mayaOn = !!(data.maya && data.maya.enabled);
  var form = document.getElementById('ck-form');
  var errEl = document.getElementById('ck-error');
  var submitBtn = form.querySelector('button[type=submit]');
  var submitIdle = submitBtn.innerHTML;
  var state = { item: 'membership_executive', invoice: null };

  function price() {
    if (state.invoice) return state.invoice.amount;
    var it = data.items[state.item];
    return it ? it.price : 0;
  }

  function render() {
    form.elements.itemType.value = state.item;
    form.elements.invoiceRef.value = state.invoice ? state.invoice.referenceNumber : '';
    EVO.qsa('[data-ck-tier]', form).forEach(function (b) {
      EVO.setActive(b, b.getAttribute('data-ck-tier') === state.item);
    });
    document.getElementById('ck-tiers').hidden = !!state.invoice;
    var inv = document.getElementById('ck-invoice');
    inv.hidden = !state.invoice;
    if (state.invoice) {
      inv.querySelector('[data-inv="ref"]').textContent = state.invoice.referenceNumber;
      inv.querySelector('[data-inv="amount"]').textContent = EVO.fmt.peso(state.invoice.amount);
      inv.querySelector('[data-inv="item"]').textContent = state.invoice.itemName;
      inv.querySelector('[data-inv="client"]').textContent = state.invoice.customerName;
    }
    EVO.qsa('[data-ck-amount]', form).forEach(function (el) {
      el.textContent = EVO.fmt.peso(price());
    });
    EVO.qsa('[data-ck-amount-php]', form).forEach(function (el) {
      el.textContent = EVO.fmt.peso(price()) + ' PHP';
    });
  }

  function idle() {
    submitBtn.disabled = !mayaOn;
    submitBtn.innerHTML = submitIdle;
  }

  EVO.openCheckout = function (item, opts) {
    opts = opts || {};
    // Hardware items open on the Executive tier, exactly like the reference portal.
    state.item = item === 'nfc_card_upgrade' || item === 'hardware_combo' ? 'membership_executive' : item || 'membership_executive';
    if (!data.items[state.item]) state.item = 'membership_executive';
    state.invoice = opts.invoice || null;
    errEl.hidden = true;
    idle();
    render();
    EVO.openModal('checkout');
  };

  form.addEventListener('click', function (ev) {
    var tier = ev.target.closest('[data-ck-tier]');
    if (tier) {
      state.item = tier.getAttribute('data-ck-tier');
      render();
    }
  });

  function showError(e) {
    errEl.textContent = e.message;
    errEl.hidden = false;
    idle();
  }

  // Coming back from Maya with the browser's Back button restores this page as it was left.
  window.addEventListener('pageshow', function (ev) {
    if (ev.persisted) idle();
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    errEl.hidden = true;
    if (!mayaOn || !form.reportValidity()) return;
    var d = EVO.formData(form);
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<span class="w-3.5 h-3.5 rounded-full border-2 border-black border-t-transparent animate-spin"></span><span>Opening Maya Checkout...</span>';
    EVO.api('payments.mayaStart', {
      itemType: d.itemType,
      customerName: d.customerName,
      customerEmail: d.customerEmail,
      customerPhone: d.customerPhone,
      invoiceRef: d.invoiceRef,
    })
      .then(function (res) {
        window.location.assign(res.redirectUrl);
      })
      .catch(showError);
  });

  // [data-checkout="itemType"] buttons anywhere on the page open the portal.
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-checkout]');
    if (!b) return;
    ev.preventDefault();
    EVO.openCheckout(b.getAttribute('data-checkout'));
  });

  // Auto-open for admin invoice links (?invoice=INV-...) and ?checkout=<item>.
  document.addEventListener('DOMContentLoaded', function () {
    if (data.invoice) {
      EVO.openCheckout(data.invoice.itemType, { invoice: data.invoice });
      return;
    }
    var params = new URLSearchParams(window.location.search);
    var item = params.get('checkout');
    if (item) EVO.openCheckout(item);
  });
})();
