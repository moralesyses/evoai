/*
 * Backoffice tab 10 — My Products: add and edit the member's own products in a
 * form with a picture upload (card.productSave, sent as multipart form data),
 * move them up / down, show or hide them on the card, delete them, and set
 * each paid order's delivery status (card.orderFulfil). Changes reload the tab.
 */
(function () {
  'use strict';

  var root = document.getElementById('bo-products');
  if (!root || !window.EVO) return;
  var data = (window.EVO_PAGE || {}).cardProducts || { products: [] };
  var readOnly = !!((window.EVO_PAGE || {}).backoffice || {}).readOnly;
  var form = document.getElementById('mp-form');
  var errEl = EVO.qs('[data-mp-error]', root);
  var preview = EVO.qs('[data-mp-preview]', root);
  var noImage = EVO.qs('[data-mp-noimage]', root);
  var objectUrl = null;

  if (readOnly) {
    // An admin viewing the member's backoffice: nothing can be changed here.
    EVO.qsa('[data-mp-add], [data-mp-edit], [data-mp-active], [data-mp-delete], [data-mp-move], [data-mp-fulfil]', root).forEach(function (el) {
      el.disabled = true;
    });
    return;
  }

  function byId(id) {
    return data.products.filter(function (p) {
      return p.id === id;
    })[0];
  }
  function showPreview(src) {
    preview.hidden = !src;
    noImage.hidden = !!src;
    if (src) preview.src = src;
    else preview.removeAttribute('src');
  }
  function showError(text) {
    errEl.textContent = text || '';
    errEl.hidden = !text;
  }

  function openForm(p) {
    form.reset();
    showError('');
    var f = form.elements;
    f.id.value = p ? p.id : '';
    f.name.value = p ? p.name : '';
    f.price.value = p ? p.price : '';
    f.stock.value = p && p.stock !== null ? p.stock : '';
    f.description.value = p ? p.description : '';
    f.active.checked = p ? p.active : true;
    EVO.qs('[data-mp-title]', root).textContent = p ? 'Edit ' + p.name : 'Add product';
    EVO.qs('[data-mp-image-req]', root).hidden = !!p;
    showPreview(p ? p.image : '');
    EVO.openModal('bo-product');
    setTimeout(function () {
      f.name.focus();
    }, 50);
  }

  form.elements.image.addEventListener('change', function () {
    var file = this.files && this.files[0];
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
    if (!file) {
      var p = byId(form.elements.id.value);
      showPreview(p ? p.image : '');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      showError('That picture is too big (5 MB at most).');
      this.value = '';
      return;
    }
    showError('');
    objectUrl = URL.createObjectURL(file);
    showPreview(objectUrl);
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    showError('');
    if (!form.reportValidity()) return;
    var f = form.elements;
    if (!f.id.value && !(f.image.files && f.image.files[0])) {
      showError('Please choose a picture of the product.');
      return;
    }
    var fd = new FormData(form);
    fd.set('active', f.active.checked ? '1' : '0');
    var btn = form.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>Saving…</span>');
    fetch(EVO.base + '/api.php?action=card.productSave', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': EVO.csrf, Accept: 'application/json' },
      body: fd,
    })
      .then(function (r) {
        return r.json().catch(function () {
          return { ok: false, error: 'The server did not answer. Please try again.' };
        });
      })
      .then(function (res) {
        if (!res.ok) throw new Error(res.error || 'Could not save the product.');
        EVO.reload(res.message);
      })
      .catch(function (e) {
        restore();
        showError(e.message);
      });
  });

  function call(btn, action, payload, reload) {
    var restore = EVO.busy(btn);
    EVO.api(action, payload)
      .then(function (res) {
        if (reload) EVO.reload(res.message);
        else {
          restore();
          if (res.message) EVO.toast(res.message);
        }
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  }

  root.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.closest('[data-mp-add]')) return openForm(null);
    var edit = t.closest('[data-mp-edit]');
    if (edit) return openForm(byId(edit.getAttribute('data-mp-edit')));
    var move = t.closest('[data-mp-move]');
    if (move) return call(move, 'card.productMove', { id: move.getAttribute('data-mp-move'), dir: move.getAttribute('data-dir') }, true);
    var act = t.closest('[data-mp-active]');
    if (act) return call(act, 'card.productActive', { id: act.getAttribute('data-mp-active'), active: act.getAttribute('data-to') === '1' }, true);
    var del = t.closest('[data-mp-delete]');
    if (del) {
      if (!window.confirm('Delete ' + del.getAttribute('data-name') + '?\n\nIt leaves your card for good. Orders already paid keep their record. To take it off your card for now, use Hide instead.')) return;
      call(del, 'card.productDelete', { id: del.getAttribute('data-mp-delete') }, true);
    }
  });

  root.addEventListener('change', function (ev) {
    var sel = ev.target.closest('[data-mp-fulfil]');
    if (!sel) return;
    sel.disabled = true;
    EVO.api('card.orderFulfil', { id: sel.getAttribute('data-mp-fulfil'), fulfillment: sel.value })
      .then(function (res) {
        sel.disabled = false;
        EVO.toast(res.message);
      })
      .catch(function (e) {
        sel.disabled = false;
        EVO.toast(e.message, 'error');
      });
  });
})();
