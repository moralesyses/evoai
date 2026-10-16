/*
 * Management Admin > Users (superadmin). The editor loads one user with
 * admin.user and saves with admin.userSave (only changed fields; refused when
 * the account changed meanwhile). Delete asks first, then admin.userDelete.
 */
(function () {
  'use strict';

  var root = document.getElementById('admin-users');
  if (!root || !window.EVO) return;

  var MSG = 'p-3 rounded-xl text-xs ';
  var MSG_OK = MSG + 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-300';
  var MSG_ERR = MSG + 'bg-rose-500/10 border border-rose-500/30 text-rose-300';
  var MSG_INFO = MSG + 'bg-amber-950/40 border border-amber-600/40 text-amber-200';

  var form = document.getElementById('us-edit-form');
  var msg = EVO.qs('[data-us-msg]', root);
  var current = null;

  function setText(sel, text) {
    var el = EVO.qs(sel, root);
    if (el) el.textContent = text == null || text === '' ? '—' : text;
  }

  function showMsg(text, cls) {
    if (!msg) return;
    msg.textContent = text || '';
    msg.className = cls;
    msg.hidden = !text;
  }

  function clearErrors() {
    EVO.qsa('[data-us-error]', form).forEach(function (el) {
      el.textContent = '';
      el.hidden = true;
    });
    EVO.qsa('[aria-invalid]', form).forEach(function (el) {
      el.removeAttribute('aria-invalid');
    });
  }

  function showErrors(errors) {
    var first = null;
    Object.keys(errors || {}).forEach(function (field) {
      var el = EVO.qs('[data-us-error="' + field + '"]', form);
      var input = form.elements[field];
      if (el) {
        el.textContent = errors[field];
        el.hidden = false;
      }
      if (input) {
        input.setAttribute('aria-invalid', 'true');
        if (!first) first = input;
      }
    });
    if (first) first.focus();
  }

  /** Put a user from the API into the editor. */
  function fill(u) {
    current = u;
    var f = form.elements;
    f.userId.value = u.id;
    f.version.value = u.updatedAt || '';
    f.name.value = u.name || '';
    f.email.value = u.email || '';
    f.title.value = u.title || '';
    f.role.value = u.role;
    f.status.value = u.status;
    f.verified.checked = !!u.verified;
    f.newPassword.value = '';
    // Superadmins can't demote or suspend themselves (the server refuses it too).
    f.role.disabled = !!u.isSelf;
    f.status.disabled = !!u.isSelf;
    f.role.title = u.isSelf ? 'You cannot remove your own admin access.' : '';
    f.status.title = u.isSelf ? 'You cannot suspend your own account.' : '';

    setText('[data-us-name]', u.name);
    setText('[data-us-id]', u.id);
    setText('[data-us-created]', u.createdAt);
    setText('[data-us-login]', u.lastLoginAt || 'Never');
    var card = EVO.qs('[data-us-card-link]', root);
    if (card) {
      card.hidden = !u.cardUrl;
      card.href = u.cardUrl || '#';
    }
    var del = EVO.qs('[data-us-delete-from-edit]', root);
    if (del) del.hidden = !!(u.isSelf || u.isFounder);
  }

  function openEdit(userId, btn) {
    var restore = EVO.busy(btn);
    EVO.api('admin.user', { userId: userId })
      .then(function (res) {
        clearErrors();
        showMsg('');
        fill(res.user);
        EVO.openModal('us-edit');
      })
      .catch(function (err) {
        EVO.toast(err.message, 'error');
      })
      .then(restore);
  }

  /* ---------------- Delete ---------------- */
  var delMsg = EVO.qs('[data-us-delete-msg]', root);
  var delBtn = EVO.qs('[data-us-delete-confirm]', root);
  var delId = null;

  function openDelete(userId, name, email) {
    delId = userId;
    EVO.qs('[data-us-delete-name]', root).textContent = name;
    EVO.qs('[data-us-delete-email]', root).textContent = email;
    if (delMsg) delMsg.hidden = true;
    EVO.openModal('us-delete');
    // Focus Cancel, so pressing Enter never deletes by accident.
    var cancel = EVO.qs('[data-us-delete-cancel]', root);
    if (cancel) cancel.focus();
  }

  if (delBtn) {
    delBtn.addEventListener('click', function () {
      if (!delId) return;
      var restore = EVO.busy(delBtn, EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>Deleting…</span>');
      EVO.api('admin.userDelete', { userId: delId })
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (err) {
          restore();
          if (delMsg) {
            delMsg.textContent = err.message;
            delMsg.hidden = false;
          }
        });
    });
  }

  /* ---------------- Row and editor buttons ---------------- */
  root.addEventListener('click', function (ev) {
    var edit = ev.target.closest('[data-us-edit]');
    if (edit) {
      openEdit(edit.getAttribute('data-us-edit'), edit);
      return;
    }
    var del = ev.target.closest('[data-us-delete]');
    if (del) {
      openDelete(del.getAttribute('data-us-delete'), del.getAttribute('data-us-name'), del.getAttribute('data-us-email'));
      return;
    }
    if (ev.target.closest('[data-us-delete-from-edit]') && current) {
      EVO.closeModal('us-edit');
      openDelete(current.id, current.name, current.email);
    }
  });

  /* ---------------- Save ---------------- */
  if (form) {
    form.elements.name.addEventListener('input', function () {
      setText('[data-us-name]', form.elements.name.value);
    });

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      clearErrors();
      showMsg('');
      var f = form.elements;
      var payload = {
        userId: f.userId.value,
        version: f.version.value,
        name: f.name.value,
        email: f.email.value,
        title: f.title.value,
        role: f.role.value,
        status: f.status.value,
        verified: f.verified.checked ? 1 : 0,
        newPassword: f.newPassword.value,
      };
      var btn = form.querySelector('button[type="submit"]');
      var restore = EVO.busy(btn, EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>Saving…</span>');
      EVO.api('admin.userSave', payload)
        .then(function (res) {
          if (res.saved) {
            EVO.reload(res.message === 'Saved.' ? 'Saved ' + res.user.name + '.' : res.message);
            return;
          }
          restore();
          if (res.errors) {
            showMsg(res.message, MSG_ERR);
            showErrors(res.errors);
          } else if (res.conflict) {
            if (res.user) fill(res.user);
            showMsg(res.message, MSG_INFO);
          } else {
            showMsg(res.message, res.unchanged ? MSG_INFO : MSG_OK);
          }
        })
        .catch(function (err) {
          restore();
          showMsg(err.message, MSG_ERR);
        });
    });
  }

  // Your own accounts (their rows' Backoffice button): make that one the Active Account, then open the Backoffice.
  EVO.qsa('[data-us-open-account]', root).forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      EVO.api('mlm.setActive', { accountId: link.getAttribute('data-us-open-account') })
        .then(function () { window.location.href = link.href; })
        .catch(function (err) { EVO.toast(err.message, 'error'); });
    });
  });
})();
