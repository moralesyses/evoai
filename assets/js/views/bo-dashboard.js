/* Member Backoffice — tab 1 "Dashboard" (reference component Tn). */
(function () {
  'use strict';

  var root = document.getElementById('bo-dashboard');
  if (!root) return;

  var balance = Number(root.getAttribute('data-balance')) || 0;
  var form = document.getElementById('bo-encash-form');
  var modal = document.querySelector('[data-modal="bo-encash"]');

  /* ---------------- Notifications ---------------- */
  function markRead(item) {
    if (item.getAttribute('data-read') === '1') return;
    EVO.api('mlm.notifRead', { id: item.getAttribute('data-bo-notif') })
      .then(function () {
        item.setAttribute('data-read', '1');
        item.classList.remove('bg-zinc-900', 'border-[#f3bd35]/30', 'text-zinc-200');
        item.classList.add('bg-zinc-950/60', 'border-zinc-800/60', 'text-zinc-400');
        var dot = item.querySelector('[data-bo-dot]');
        if (dot) dot.remove();
        var counter = root.querySelector('[data-bo-unread]');
        if (counter) counter.textContent = String(root.querySelectorAll('[data-bo-notif][data-read="0"]').length);
      })
      .catch(function (e) {
        EVO.toast(e.message, 'error');
      });
  }

  /* ---------------- Encashment modal ---------------- */
  var submitBtn = form.querySelector('[data-bo-encash-submit]');
  var amountInput = form.querySelector('[name="amount"]');
  var errBox = form.querySelector('[data-bo-encash-error]');
  var okBox = modal.querySelector('[data-bo-encash-success]');
  var busy = false;

  function amount() {
    var n = Number(amountInput.value);
    return isFinite(n) ? n : 0;
  }

  function net(g) {
    return Math.max(0, g - Math.round(g * 0.1) - 50);
  }

  function updateSummary() {
    var g = amount();
    var tax = Math.round(g * 0.1);
    form.querySelector('[data-bo-gross]').textContent = EVO.fmt.peso(g);
    form.querySelector('[data-bo-tax]').textContent = '-' + EVO.fmt.peso(tax);
    form.querySelector('[data-bo-net]').textContent = EVO.fmt.peso(net(g)) + ' PHP';
    if (!busy) submitBtn.textContent = 'Confirm & Request ' + EVO.fmt.peso(net(g)) + ' Payout';
    submitBtn.disabled = busy || g <= 0 || g > balance;
  }

  function showError(msg) {
    errBox.querySelector('[data-msg]').textContent = msg || '';
    errBox.hidden = !msg;
  }

  amountInput.addEventListener('input', updateSummary);

  form.addEventListener('click', function (ev) {
    var ch = ev.target.closest('[data-bo-channel]');
    if (!ch) return;
    var id = ch.getAttribute('data-bo-channel');
    form.querySelector('[name="payoutMethod"]').value = id;
    EVO.qsa('[data-bo-channel]', form).forEach(function (b) {
      var on = b === ch;
      EVO.setActive(b, on);
      b.removeAttribute('aria-selected');
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (busy) return;
    showError('');
    var d = EVO.formData(form);
    busy = true;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Authorizing Payout...';
    EVO.api('mlm.encash', {
      amount: amount(),
      payoutMethod: d.payoutMethod,
      accountDetails: d.accountDetails,
      pin: d.pin,
    })
      .then(function (res) {
        if (res.success) {
          okBox.querySelector('[data-msg]').textContent = res.message;
          okBox.hidden = false;
          form.hidden = true;
          // Reference closes the modal after 2.5s; reload to show the new balance & ledger.
          setTimeout(function () {
            EVO.reload();
          }, 2500);
          return;
        }
        showError(res.message);
        busy = false;
        updateSummary();
      })
      .catch(function (e) {
        showError(e.message || 'Withdrawal submission failed.');
        busy = false;
        updateSummary();
      });
  });

  /* ---------------- Delegated clicks ---------------- */
  root.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.closest('[data-bo-view-ledger]')) {
      EVO.activateTab('bo', 'transactions');
      return;
    }
    if (t.closest('[data-bo-manage-nfc]')) {
      EVO.activateTab('bo', 'my_account');
      EVO.activateTab('acct', 'nfc_card');
      return;
    }
    var n = t.closest('[data-bo-notif]');
    if (n) markRead(n);
  });

  root.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var n = ev.target.closest('[data-bo-notif]');
    if (!n) return;
    ev.preventDefault();
    markRead(n);
  });

  updateSummary();
})();
