/*
 * Online payment page: re-open the invoice checkout after the pop-up is
 * closed; after Maya Checkout, wait for Maya to confirm and print the receipt.
 */
(function () {
  'use strict';
  var dataEl = document.getElementById('checkout-data');
  var data = dataEl ? JSON.parse(dataEl.textContent) : {};

  document.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-pay-print]')) {
      document.body.classList.add('evo-printing');
      window.print();
      setTimeout(function () {
        document.body.classList.remove('evo-printing');
      }, 500);
      return;
    }
    if (!ev.target.closest('[data-pay-invoice]') || !data.invoice || !EVO.openCheckout) return;
    ev.preventDefault();
    EVO.openCheckout(data.invoice.itemType, { invoice: data.invoice });
  });

  // Back from Maya but not confirmed yet: ask every 3 seconds for about a
  // minute, then reload to show the receipt (or why it wasn't paid).
  var pending = document.querySelector('[data-maya-pending]');
  if (!pending) return;
  var rrn = pending.getAttribute('data-maya-pending');
  var text = pending.querySelector('[data-maya-wait-text]');
  var tries = 0;
  function check() {
    tries++;
    EVO.api('payments.mayaStatus', { rrn: rrn })
      .then(function (res) {
        if (res.state !== 'pending') {
          window.location.reload();
          return;
        }
        next();
      })
      .catch(next);
  }
  function next() {
    if (tries < 20) {
      setTimeout(check, 3000);
    } else if (text) {
      text.textContent = 'Maya hasn’t confirmed this payment yet. If you completed it, refresh this page in a minute: the receipt appears here once Maya confirms. If you didn’t, nothing was charged.';
    }
  }
  setTimeout(check, 2000);
})();
