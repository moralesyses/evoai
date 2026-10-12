/* Member Backoffice — tab 2 "My Account" (reference component En). */
(function () {
  'use strict';

  var root = document.getElementById('bo-my-account');
  if (!root) return;

  var balance = Number(root.getAttribute('data-balance')) || 0;
  var profileId = root.getAttribute('data-profile-id');
  var serial = root.getAttribute('data-serial');
  var state = {
    theme: root.getAttribute('data-theme') || 'gold',
    action: root.getAttribute('data-action') || 'card',
    frozen: root.getAttribute('data-frozen') === '1',
    qty: 1,
    tapping: false,
  };

  var CARD_BASE =
    'w-full aspect-[1.586/1] rounded-2xl p-5 shadow-2xl relative flex flex-col justify-between overflow-hidden transition-all duration-300 border ';
  var CARD_THEMES = {
    gold: 'bg-gradient-to-br from-[#d4af37] via-[#f7e394] to-[#a3791a] text-black border-amber-300 shadow-amber-500/20',
    obsidian: 'bg-gradient-to-br from-[#1c1c1c] via-[#0d0d0d] to-[#000000] text-amber-200 border-[#f3bd35]/50 shadow-black',
    platinum: 'bg-gradient-to-br from-[#f0f0f0] via-[#d6d6d6] to-[#999999] text-black border-zinc-300 shadow-zinc-500/20',
    rosegold: 'bg-gradient-to-br from-[#f4b6a9] via-[#e89b88] to-[#b35e50] text-black border-rose-300 shadow-rose-500/20',
  };
  var TAP_LABELS = { card: 'Opening Digital Card Portal', vcard: 'vCard Direct Address Book' };

  /* ---------------- Inline banners (reference: ue / D state) ---------------- */
  var timers = {};
  function banner(sel, msg, ms, then) {
    var box = root.querySelector(sel);
    if (!box) return;
    clearTimeout(timers[sel]);
    box.querySelector('[data-msg]').textContent = msg;
    box.hidden = false;
    timers[sel] = setTimeout(function () {
      box.hidden = true;
      if (then) then();
    }, ms);
  }
  function success(msg, ms, then) {
    banner('[data-ma-success]', msg, ms || 3000, then);
  }
  function nfcMessage(msg, ms, then) {
    banner('[data-ma-nfc-msg]', msg, ms || 3500, then);
  }

  /** Mark one option of a group active (data-on/data-off on the option and its children). */
  function selectOption(options, chosen) {
    options.forEach(function (o) {
      var on = o === chosen;
      EVO.setActive(o, on);
      o.removeAttribute('aria-selected');
      EVO.qsa('[data-on]', o).forEach(function (c) {
        EVO.setActive(c, on);
        c.removeAttribute('aria-selected');
      });
      if (o.hasAttribute('role')) o.setAttribute('aria-checked', on ? 'true' : 'false');
    });
  }

  /* ---------------- Account list: switch active head ---------------- */
  function switchHead(btn) {
    var restore = EVO.busy(btn);
    EVO.api('mlm.setActive', { accountId: btn.getAttribute('data-ma-switch') })
      .then(function () {
        EVO.reload();
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  }

  /* ---------------- Profile details ---------------- */
  var profileForm = document.getElementById('ma-profile-form');
  profileForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = profileForm.querySelector('button[type=submit]');
    var restore = EVO.busy(btn);
    EVO.api('account.saveDetails', EVO.formData(profileForm))
      .then(function (res) {
        success(res.message || 'Profile updated successfully!', 3000, function () {
          EVO.reload();
        });
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });

  /* ---------------- Security & 2FA PIN ---------------- */
  var secForm = document.getElementById('ma-security-form');
  var twoFaBtn = secForm.querySelector('[data-ma-2fa]');
  var twoFaInput = secForm.querySelector('[name="twoFactor"]');
  twoFaBtn.addEventListener('click', function () {
    var on = twoFaInput.value !== '1';
    twoFaInput.value = on ? '1' : '0';
    EVO.setActive(twoFaBtn, on);
    twoFaBtn.removeAttribute('aria-selected');
    twoFaBtn.setAttribute('aria-checked', on ? 'true' : 'false');
    var knob = twoFaBtn.querySelector('[data-on]');
    EVO.setActive(knob, on);
    knob.removeAttribute('aria-selected');
  });

  secForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = EVO.formData(secForm);
    if (d.newPassword && d.newPassword !== d.confirmPassword) {
      EVO.toast('Passwords do not match', 'error');
      return;
    }
    if (d.newPassword && !d.currentPassword) {
      EVO.toast('Please enter your current password to change it.', 'error');
      secForm.querySelector('[name="currentPassword"]').focus();
      return;
    }
    var btn = secForm.querySelector('button[type=submit]');
    var restore = EVO.busy(btn);
    EVO.api('account.saveSecurity', d)
      .then(function (res) {
        ['currentPassword', 'newPassword', 'confirmPassword'].forEach(function (n) {
          secForm.querySelector('[name="' + n + '"]').value = '';
        });
        success(res.message || 'Security settings updated successfully!', 3000, function () {
          EVO.reload();
        });
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });

  /* ---------------- Activation codes: buy ---------------- */
  var buyModal = document.querySelector('[data-modal="ma-buy-codes"]');
  var buyBtn = buyModal.querySelector('[data-ma-buy-confirm]');
  var buying = false;

  function updateBuy() {
    buyModal.querySelector('[data-ma-qty-total]').textContent = EVO.fmt.peso(state.qty * 2000) + ' PHP';
    buyBtn.disabled = buying || balance < state.qty * 2000;
  }

  buyModal.addEventListener('click', function (ev) {
    var q = ev.target.closest('[data-ma-qty]');
    if (q) {
      state.qty = Number(q.getAttribute('data-ma-qty'));
      selectOption(EVO.qsa('[data-ma-qty]', buyModal), q);
      updateBuy();
      return;
    }
    if (!ev.target.closest('[data-ma-buy-confirm]') || buying) return;
    buying = true;
    buyBtn.disabled = true;
    buyBtn.textContent = 'Generating...';
    EVO.api('mlm.buyCodes', { quantity: state.qty })
      .then(function (res) {
        if (res.success) {
          EVO.closeModal(buyModal);
          success('Generated ' + (res.codes || []).length + ' activation code(s)!', 3000, function () {
            EVO.reload();
          });
          return;
        }
        EVO.toast(res.message || 'Insufficient available balance to purchase activation codes.', 'error');
        buying = false;
        buyBtn.textContent = 'Confirm Purchase';
        updateBuy();
      })
      .catch(function (e) {
        EVO.toast(e.message, 'error');
        buying = false;
        buyBtn.textContent = 'Confirm Purchase';
        updateBuy();
      });
  });

  /* ---------------- Activation codes: transfer ---------------- */
  var trModal = document.querySelector('[data-modal="ma-transfer-code"]');
  var trForm = document.getElementById('ma-transfer-form');
  var trUser = trForm.querySelector('[name="username"]');
  var trSubmit = trForm.querySelector('button[type=submit]');
  var trOk = trModal.querySelector('[data-ma-transfer-success]');
  var transferring = false;

  function openTransfer(code) {
    trForm.querySelector('[name="code"]').value = code;
    trModal.querySelector('[data-ma-transfer-code]').textContent = code;
    trOk.hidden = true;
    trForm.hidden = false;
    trSubmit.disabled = transferring || !trUser.value.trim();
    EVO.openModal('ma-transfer-code');
  }

  trUser.addEventListener('input', function () {
    trSubmit.disabled = transferring || !trUser.value.trim();
  });

  trForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (transferring || !trUser.value.trim()) return;
    transferring = true;
    trSubmit.disabled = true;
    trSubmit.textContent = 'Transferring...';
    var d = EVO.formData(trForm);
    EVO.api('mlm.transferCode', { code: d.code, username: d.username })
      .then(function (res) {
        if (res.success) {
          trOk.textContent = res.message;
          trOk.hidden = false;
          trForm.hidden = true;
          setTimeout(function () {
            EVO.reload();
          }, 2000);
          return;
        }
        EVO.toast(res.message, 'error');
        transferring = false;
        trSubmit.textContent = 'Send Code';
        trSubmit.disabled = !trUser.value.trim();
      })
      .catch(function (e) {
        EVO.toast(e.message, 'error');
        transferring = false;
        trSubmit.textContent = 'Send Code';
        trSubmit.disabled = !trUser.value.trim();
      });
  });

  /* ---------------- Smart NFC card ---------------- */
  var card = root.querySelector('[data-ma-card]');
  var nfcForm = document.getElementById('ma-nfc-form');
  var freezeBtn = root.querySelector('[data-ma-freeze]');

  function setTheme(id) {
    state.theme = id;
    card.className = CARD_BASE + (CARD_THEMES[id] || CARD_THEMES.rosegold);
    var opts = EVO.qsa('[data-ma-theme]', nfcForm);
    selectOption(
      opts,
      opts.filter(function (o) {
        return o.getAttribute('data-ma-theme') === id;
      })[0]
    );
  }

  function setAction(id) {
    state.action = id;
    var opts = EVO.qsa('[data-ma-action]', nfcForm);
    selectOption(
      opts,
      opts.filter(function (o) {
        return o.getAttribute('data-ma-action') === id;
      })[0]
    );
  }

  function toggleFreeze() {
    state.frozen = !state.frozen;
    EVO.setActive(freezeBtn, state.frozen);
    freezeBtn.removeAttribute('aria-selected');
    freezeBtn.setAttribute('aria-pressed', state.frozen ? 'true' : 'false');
    freezeBtn.textContent = state.frozen ? 'Frozen (Locked)' : 'Card Active';
  }

  nfcForm.addEventListener('click', function (ev) {
    var th = ev.target.closest('[data-ma-theme]');
    if (th) {
      setTheme(th.getAttribute('data-ma-theme'));
      return;
    }
    var ac = ev.target.closest('[data-ma-action]');
    if (ac) setAction(ac.getAttribute('data-ma-action'));
  });
  nfcForm.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var ac = ev.target.closest('[data-ma-action]');
    if (!ac) return;
    ev.preventDefault();
    setAction(ac.getAttribute('data-ma-action'));
  });

  nfcForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = nfcForm.querySelector('button[type=submit]');
    var restore = EVO.busy(btn);
    EVO.api('profile.update', {
      patch: {
        cardTheme: state.theme,
        nfcAction: state.action,
        membershipStatus: state.frozen ? 'Frozen (Card Lost)' : 'Active',
      },
    })
      .then(function () {
        nfcMessage('NFC Smart Card preferences successfully synced to your hardware chip & backoffice!', 3500, function () {
          EVO.reload();
        });
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });

  var tapButtons = EVO.qsa('[data-ma-tap]', root);
  var mainTap = root.querySelector('[data-ma-tap-main]');
  var mainTapIdle = mainTap.innerHTML;

  function simulateTap() {
    if (state.tapping) return;
    state.tapping = true;
    tapButtons.forEach(function (b) {
      b.disabled = true;
    });
    mainTap.innerHTML = EVO.icon('radio', 'w-4 h-4 text-black animate-spin') + '<span>Reading Chip...</span>';
    var done = function () {
      setTimeout(function () {
        state.tapping = false;
        tapButtons.forEach(function (b) {
          b.disabled = false;
        });
        mainTap.innerHTML = mainTapIdle;
        document.querySelector('[data-ma-tap-action]').textContent = TAP_LABELS[state.action] || 'Launching WhatsApp VIP';
        EVO.openModal('ma-nfc-tap');
      }, 750);
    };
    EVO.api('analytics.track', { action: 'nfc_tap', profileId: profileId })
      .then(function (res) {
        var taps = root.querySelector('[data-ma-taps]');
        if (res.tracked && taps) {
          var n = Number(taps.getAttribute('data-ma-taps')) + 1;
          taps.setAttribute('data-ma-taps', String(n));
          taps.textContent = EVO.fmt.num(n);
        }
        done();
      })
      .catch(done);
  }

  /* ---------------- Delegated clicks ---------------- */
  root.addEventListener('click', function (ev) {
    var t = ev.target;
    var sw = t.closest('[data-ma-switch]');
    if (sw) {
      switchHead(sw);
      return;
    }
    var tr = t.closest('[data-ma-transfer]');
    if (tr) {
      openTransfer(tr.getAttribute('data-ma-transfer'));
      return;
    }
    if (t.closest('[data-ma-tap]')) {
      simulateTap();
      return;
    }
    if (t.closest('[data-ma-copy-serial]')) {
      EVO.copy(serial)
        .then(function () {
          nfcMessage('Card Serial copied to clipboard!', 2000);
        })
        .catch(function () {
          EVO.toast('Unable to access the clipboard.', 'error');
        });
      return;
    }
    if (t.closest('[data-ma-freeze]')) {
      toggleFreeze();
      return;
    }
    var go = t.closest('[data-ma-goto]');
    if (go) EVO.activateTab('bo', go.getAttribute('data-ma-goto'));
  });

  updateBuy();
})();
