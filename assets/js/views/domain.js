/*
 * .com domain order result page (views/domain.php): the test payment
 * buttons, waiting for the payment and registration to finish, and Try Again.
 */
(function () {
  'use strict';

  document.addEventListener('click', function (ev) {
    var pay = ev.target.closest('[data-domain-mockpay]');
    if (pay) {
      var box = pay.closest('[data-domain-mock]');
      var restore = EVO.busy(pay, 'Processing…');
      EVO.qsa('[data-domain-mockpay]').forEach(function (b) { b.disabled = true; });
      EVO.api('domains.mockPay', { order: box.getAttribute('data-domain-mock'), result: pay.getAttribute('data-domain-mockpay') })
        .then(function () {
          // The same page without mockpay=1 shows the outcome.
          window.location.href = EVO.url('domain', { order: box.getAttribute('data-domain-mock') });
        })
        .catch(function (e) {
          restore();
          EVO.qsa('[data-domain-mockpay]').forEach(function (b) { b.disabled = false; });
          EVO.toast(e.message, 'error');
        });
      return;
    }

    var again = ev.target.closest('[data-domain-again]');
    if (again) {
      var parts = again.getAttribute('data-domain-again').split(':');
      var call = parts[0] === 'renew' ? EVO.api('domains.renew', { id: Number(parts[1]) }) : EVO.api('domains.buy', { domain: parts[1], price: Number(again.getAttribute('data-domain-price')) });
      var undo = EVO.busy(again, 'Opening payment…');
      call
        .then(function (res) {
          if (res.priceChanged) {
            undo();
            again.setAttribute('data-domain-price', String(res.price));
            EVO.toast(res.message);
            return;
          }
          window.location.href = res.redirectUrl;
        })
        .catch(function (e) {
          undo();
          EVO.toast(e.message, 'error');
        });
    }
  });

  // Waiting for the payment provider or the registrar: ask every 3 seconds
  // for about two minutes, then reload to show the outcome.
  var pending = document.querySelector('[data-domain-pending]');
  if (!pending) return;
  var order = pending.getAttribute('data-domain-pending');
  var text = pending.querySelector('[data-domain-wait-text]');
  var tries = 0;
  function check() {
    tries++;
    EVO.api('domains.status', { order: order })
      .then(function (res) {
        if (['pending_payment', 'paid', 'registering'].indexOf(res.status) === -1) {
          window.location.reload();
          return;
        }
        next();
      })
      .catch(next);
  }
  function next() {
    if (tries < 40) {
      setTimeout(check, 3000);
    } else if (text) {
      text.textContent = 'This is taking longer than usual. If you completed the payment, refresh this page in a minute; it is confirmed here and in your backoffice once Maya reports it. If you didn’t pay, nothing was charged.';
    }
  }
  setTimeout(check, 2000);
})();
