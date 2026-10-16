/*
 * Management Admin > Binary Complan: live "Plan at a glance" while editing,
 * save through admin.complanSave.
 */
(function () {
  'use strict';

  var root = document.getElementById('admin-complan');
  if (!root || !window.EVO) return;
  var form = document.getElementById('cp-form');
  var msg = EVO.qs('[data-cp-msg]', root);

  function int(name) {
    var v = parseInt(form.elements[name].value, 10);
    return isNaN(v) || v < 0 ? 0 : v;
  }
  function peso(n) {
    return '₱' + Number(n).toLocaleString('en-US');
  }
  function out(key, text) {
    var el = EVO.qs('[data-cp-out="' + key + '"]', root);
    if (el) el.textContent = text;
  }

  // Pairs per day = pairs per cycle × cycles per day; incomes follow from the pairing bonus.
  function summarize() {
    var pairs = int('maxPairsPerCycle');
    var cycles = int('cyclesPerDay');
    var bonus = int('pairingBonus');
    out('pairsPerDay', pairs * cycles);
    out('pairsFormula', '(' + pairs + ' matches × ' + cycles + ' round' + (cycles === 1 ? '' : 's') + ')');
    out('direct', peso(int('directReferralBonus')));
    out('pairing', peso(bonus) + ' per match');
    out('perCycle', peso(pairs * bonus));
    out('perDay', peso(pairs * cycles * bonus));
  }
  form.addEventListener('input', summarize);
  form.addEventListener('change', summarize);

  function clearErrors() {
    msg.hidden = true;
    EVO.qsa('[data-cp-error]', form).forEach(function (el) {
      el.hidden = true;
      el.textContent = '';
    });
    EVO.qsa('[aria-invalid]', form).forEach(function (el) {
      el.removeAttribute('aria-invalid');
    });
  }

  root.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-cp-standard]')) return;
    var d = JSON.parse(root.getAttribute('data-defaults'));
    ['directReferralBonus', 'pairingBonus', 'maxPairsPerCycle', 'cyclesPerDay'].forEach(function (k) {
      form.elements[k].value = d[k];
    });
    clearErrors();
    summarize();
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearErrors();
    var data = {};
    ['directReferralBonus', 'pairingBonus', 'maxPairsPerCycle', 'cyclesPerDay'].forEach(function (k) {
      data[k] = form.elements[k].value;
    });
    var btn = form.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>Saving…</span>');
    EVO.api('admin.complanSave', data)
      .then(function (res) {
        if (res.saved) {
          EVO.reload(res.message);
          return;
        }
        restore();
        msg.textContent = res.message;
        msg.hidden = false;
        Object.keys(res.errors || {}).forEach(function (k) {
          var el = EVO.qs('[data-cp-error="' + k + '"]', form);
          if (el) {
            el.textContent = res.errors[k];
            el.hidden = false;
          }
          if (form.elements[k]) form.elements[k].setAttribute('aria-invalid', 'true');
        });
      })
      .catch(function (err) {
        restore();
        msg.textContent = err.message;
        msg.hidden = false;
      });
  });
})();
