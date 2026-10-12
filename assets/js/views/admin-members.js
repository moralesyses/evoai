/*
 * Management Admin > Member Access & Roles (reference component "Pn").
 * Role select -> admin.setRole, NFC hardware modal + profile edit modal ->
 * admin.updateProfile. "Log in as" uses the global [data-switch-user] handler.
 */
(function () {
  'use strict';

  var root = document.getElementById('admin-members');
  if (!root || !window.EVO) return;

  var profiles = {};
  try {
    profiles = JSON.parse((document.getElementById('admin-members-data') || {}).textContent || '{}') || {};
  } catch (e) {
    profiles = {};
  }

  var ROLE_BASE = 'text-[11px] font-bold uppercase rounded-lg px-2.5 py-1 bg-zinc-900 border';
  function roleClass(role) {
    return role === 'admin' ? 'border-[#f3bd35] text-[#ffda72]' : role === 'vip' ? 'border-purple-500/50 text-purple-400' : 'border-zinc-700 text-zinc-300';
  }

  function randomSerial(vip) {
    return (vip ? 'MMC-VIP' : 'MMC-EXE') + '-' + Math.floor(1e4 + Math.random() * 9e4);
  }

  /* ---------------- Access role ---------------- */
  root.addEventListener('change', function (ev) {
    var sel = ev.target.closest('[data-member-role]');
    if (!sel) return;
    var userId = sel.getAttribute('data-member-role');
    var prev = sel.getAttribute('data-current');
    var role = sel.value;
    sel.disabled = true;
    EVO.api('admin.setRole', { userId: userId, role: role })
      .then(function () {
        sel.setAttribute('data-current', role);
        sel.className = ROLE_BASE + ' ' + roleClass(role);
      })
      .catch(function (err) {
        sel.value = prev;
        sel.className = ROLE_BASE + ' ' + roleClass(prev);
        EVO.toast(err.message, 'error');
      })
      .then(function () {
        sel.disabled = false;
      });
  });

  /* ---------------- Edit member card modal ---------------- */
  var editForm = document.getElementById('mem-edit-form');
  var editName = EVO.qs('[data-mem-edit-name]');
  var EDIT_FIELDS = ['name', 'title', 'company', 'customSlug', 'phone', 'email', 'whatsapp', 'tier', 'nfcCardId', 'bio'];

  function openEdit(userId) {
    var p = profiles[userId];
    if (!p || !editForm) return;
    editForm.elements.userId.value = userId;
    EDIT_FIELDS.forEach(function (f) {
      var el = editForm.elements[f];
      if (el) el.value = p[f] == null ? '' : p[f];
    });
    if (editName) editName.textContent = p.name || '';
    EVO.openModal('admin-member-edit');
  }

  if (editForm) {
    editForm.elements.name.addEventListener('input', function () {
      if (editName) editName.textContent = editForm.elements.name.value;
    });
    editForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var data = EVO.formData(editForm);
      var userId = data.userId;
      var patch = {};
      EDIT_FIELDS.forEach(function (f) {
        patch[f] = data[f];
      });
      var btn = editForm.querySelector('button[type="submit"]');
      var restore = EVO.busy(btn);
      EVO.api('admin.updateProfile', { userId: userId, patch: patch })
        .then(function (res) {
          if (res.profile) profiles[userId] = res.profile;
          btn.innerHTML = EVO.icon('check', 'w-3.5 h-3.5') + '<span>Updated!</span>';
          setTimeout(function () {
            EVO.closeModal('admin-member-edit');
            EVO.reload();
          }, 1200);
        })
        .catch(function (err) {
          restore();
          EVO.toast(err.message, 'error');
        });
    });
  }

  /* ---------------- NFC hardware modal ---------------- */
  var nfcForm = document.getElementById('mem-nfc-form');

  function setTheme(id) {
    if (!nfcForm) return;
    nfcForm.elements.cardTheme.value = id;
    EVO.qsa('[data-nfc-theme]', nfcForm).forEach(function (b) {
      EVO.setActive(b, b.getAttribute('data-nfc-theme') === id);
      b.setAttribute('aria-pressed', b.getAttribute('data-nfc-theme') === id ? 'true' : 'false');
    });
  }

  function setStatus(id) {
    if (!nfcForm) return;
    nfcForm.elements.membershipStatus.value = id;
    EVO.qsa('[data-nfc-status]', nfcForm).forEach(function (b) {
      EVO.setActive(b, b.getAttribute('data-nfc-status') === id);
      b.setAttribute('aria-pressed', b.getAttribute('data-nfc-status') === id ? 'true' : 'false');
    });
  }

  function openNfc(userId) {
    var p = profiles[userId];
    if (!p || !nfcForm) return;
    nfcForm.elements.userId.value = userId;
    nfcForm.elements.nfcCardId.value = p.nfcCardId || randomSerial(false);
    nfcForm.elements.nfcAction.value = p.nfcAction || 'card';
    if (!nfcForm.elements.nfcAction.value) nfcForm.elements.nfcAction.value = 'card';
    setTheme(p.cardTheme || 'gold');
    setStatus(p.membershipStatus || 'Active');
    var idEl = EVO.qs('[data-mem-nfc-id]');
    if (idEl) idEl.textContent = userId;
    EVO.openModal('admin-member-nfc');
  }

  if (nfcForm) {
    nfcForm.addEventListener('click', function (ev) {
      var themeBtn = ev.target.closest('[data-nfc-theme]');
      if (themeBtn) {
        setTheme(themeBtn.getAttribute('data-nfc-theme'));
        return;
      }
      var statusBtn = ev.target.closest('[data-nfc-status]');
      if (statusBtn) {
        setStatus(statusBtn.getAttribute('data-nfc-status'));
        return;
      }
      var serialBtn = ev.target.closest('[data-nfc-serial]');
      if (serialBtn) {
        nfcForm.elements.nfcCardId.value = randomSerial(serialBtn.getAttribute('data-nfc-serial') === 'vip');
      }
    });

    nfcForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var data = EVO.formData(nfcForm);
      var userId = data.userId;
      var btn = nfcForm.querySelector('button[type="submit"]');
      var restore = EVO.busy(btn);
      EVO.api('admin.updateProfile', {
        userId: userId,
        patch: {
          nfcCardId: data.nfcCardId,
          cardTheme: data.cardTheme,
          nfcAction: data.nfcAction,
          membershipStatus: data.membershipStatus,
        },
      })
        .then(function (res) {
          if (res.profile) profiles[userId] = res.profile;
          btn.innerHTML = EVO.icon('check', 'w-3.5 h-3.5') + '<span>Card Synced!</span>';
          setTimeout(function () {
            EVO.closeModal('admin-member-nfc');
            EVO.reload();
          }, 1200);
        })
        .catch(function (err) {
          restore();
          EVO.toast(err.message, 'error');
        });
    });
  }

  /* ---------------- Row buttons ---------------- */
  root.addEventListener('click', function (ev) {
    var nfcBtn = ev.target.closest('[data-member-nfc]');
    if (nfcBtn) {
      ev.preventDefault();
      openNfc(nfcBtn.getAttribute('data-member-nfc'));
      return;
    }
    var editBtn = ev.target.closest('[data-member-edit]');
    if (editBtn) {
      ev.preventDefault();
      openEdit(editBtn.getAttribute('data-member-edit'));
    }
  });
})();
