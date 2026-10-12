/* Backoffice tab 6 — Member Shop: cart add / quantity / remove, checkout (MLM balance or
   Maya Checkout), order banner, and the wait for Maya's confirmation after paying there. */
(function () {
  'use strict';

  var root = document.getElementById('bo-shop');
  if (!root) return;

  var FORM_KEY = 'evo_shop_checkout';

  function fail(e) {
    EVO.toast(e.message || 'Request failed.', 'error');
  }

  /* Keep the delivery address + payment method across the reloads that follow cart edits
     (the reference keeps them in component state). */
  function saveFormState() {
    var form = document.getElementById('shop-checkout-form');
    if (!form) return;
    try {
      sessionStorage.setItem(FORM_KEY, JSON.stringify({ address: form.elements.address.value, method: form.elements.method.value }));
    } catch (e) {}
  }

  function setMethod(form, method) {
    form.elements.method.value = method;
    EVO.qsa('[data-pay-method]', form).forEach(function (b) {
      var on = b.getAttribute('data-pay-method') === method;
      EVO.setActive(b, on);
      b.removeAttribute('aria-selected');
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    // "Complete Order" for the balance, "Continue to Maya" for Maya.
    var submit = form.querySelector('button[type="submit"]');
    var label = submit && submit.getAttribute('data-label-' + method);
    if (label) submit.textContent = label;
  }

  function restoreFormState() {
    var form = document.getElementById('shop-checkout-form');
    if (!form) return;
    var saved = null;
    try {
      saved = JSON.parse(sessionStorage.getItem(FORM_KEY) || 'null');
    } catch (e) {}
    if (!saved) return;
    if (typeof saved.address === 'string' && saved.address) form.elements.address.value = saved.address;
    if (saved.method && form.querySelector('[data-pay-method="' + saved.method + '"]')) setMethod(form, saved.method);
  }

  function cartCall(action, data, btn) {
    if (btn) btn.disabled = true;
    saveFormState();
    EVO.api(action, data)
      .then(function () {
        EVO.reload();
      })
      .catch(function (e) {
        if (btn) btn.disabled = false;
        fail(e);
      });
  }

  root.addEventListener('click', function (ev) {
    var t = ev.target;

    var add = t.closest('[data-cart-add]');
    if (add) {
      cartCall('mlm.cartAdd', { productId: add.getAttribute('data-cart-add'), quantity: 1 }, add);
      return;
    }

    var qty = t.closest('[data-cart-qty]');
    if (qty) {
      cartCall('mlm.cartUpdate', { productId: qty.getAttribute('data-cart-qty'), quantity: parseInt(qty.getAttribute('data-qty'), 10) || 0 }, qty);
      return;
    }

    var rm = t.closest('[data-cart-remove]');
    if (rm) {
      cartCall('mlm.cartRemove', { productId: rm.getAttribute('data-cart-remove') }, rm);
      return;
    }

    var pay = t.closest('[data-pay-method]');
    if (pay) {
      setMethod(pay.closest('form'), pay.getAttribute('data-pay-method'));
      return;
    }

    var go = t.closest('[data-shop-go]');
    if (go) {
      EVO.activateTab('shop', go.getAttribute('data-shop-go'));
    }
  });

  root.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form.id !== 'shop-checkout-form') return;
    ev.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    var maya = form.elements.method.value === 'maya';
    var restore = EVO.busy(btn, maya ? 'Opening Maya Checkout...' : 'Authorizing Payment...');
    EVO.api('mlm.checkout', { method: form.elements.method.value, address: form.elements.address.value })
      .then(function (res) {
        if (res.redirectUrl) {
          // Maya's page; it sends the member back to the orders sub-tab (?maya=<rrn>).
          saveFormState();
          window.location.assign(res.redirectUrl);
          return;
        }
        try {
          sessionStorage.removeItem(FORM_KEY);
        } catch (e) {}
        // Same view, orders sub-tab, with the confirmation banner for this order.
        var u = new URL(window.location.href);
        u.searchParams.set('view', 'backoffice');
        u.searchParams.set('tab', 'shop');
        u.searchParams.set('shop', 'orders');
        u.searchParams.set('order', res.order.orderNumber);
        window.location.replace(u.pathname.replace(/\/$/, '/index.php') + '?' + u.searchParams.toString());
      })
      .catch(function (e) {
        restore();
        fail(e);
      });
  });

  restoreFormState();

  // Back from Maya before Maya confirmed the payment: ask again every few seconds, then reload.
  var wait = root.querySelector('[data-shop-maya-wait]');
  if (wait) {
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      EVO.api('payments.mayaStatus', { rrn: wait.getAttribute('data-shop-maya-wait') })
        .then(function (res) {
          if (res.state !== 'pending') {
            clearInterval(timer);
            window.location.reload();
          }
        })
        .catch(function () {});
      if (tries >= 40) clearInterval(timer);
    }, 3000);
  } else if (root.querySelector('[data-shop-maya-result]')) {
    // Shown once: a reload doesn't repeat it.
    EVO.setParam('maya', null);
    EVO.setParam('result', null);
  }

  // The confirmation banner disappears after 4 seconds (reference setTimeout 4e3).
  var banner = root.querySelector('[data-order-banner]');
  if (banner) {
    EVO.setParam('order', null);
    setTimeout(function () {
      banner.hidden = true;
    }, 4000);
  }
})();
