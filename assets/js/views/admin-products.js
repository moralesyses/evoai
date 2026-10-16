/*
 * Management Admin > Shop Products: add and edit products in a form with a
 * picture upload (admin.productSave, sent as multipart form data), show or
 * hide them in the Member Shop (admin.productActive) and delete them
 * (admin.productDelete). Each change reloads the tab with a message.
 */
(function () {
  'use strict';

  var root = document.getElementById('admin-products');
  if (!root || !window.EVO) return;
  var data = (window.EVO_PAGE || {}).adminProducts || { products: [] };
  var form = document.getElementById('prod-form');
  var errEl = EVO.qs('[data-prod-error]', root);
  var preview = EVO.qs('[data-prod-preview]', root);
  var noImage = EVO.qs('[data-prod-noimage]', root);
  var objectUrl = null;

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

  // Open the form empty (add) or filled with a product (edit).
  function openForm(p) {
    form.reset();
    showError('');
    var f = form.elements;
    f.id.value = p ? p.id : '';
    f.name.value = p ? p.name : '';
    f.category.value = p ? p.category : '';
    f.memberPrice.value = p ? p.memberPrice : '';
    f.price.value = p ? p.price : '';
    f.points.value = p ? p.points : 0;
    f.stock.value = p ? p.stock : 0;
    f.sortOrder.value = p ? p.sortOrder : (data.products.length ? Math.max.apply(null, data.products.map(function (x) { return x.sortOrder; })) + 1 : 1);
    f.description.value = p ? p.description : '';
    f.active.checked = p ? p.active : true;
    EVO.qs('[data-prod-title]', root).textContent = p ? 'Edit ' + p.name : 'Add product';
    EVO.qs('[data-prod-image-req]', root).hidden = !!p;
    showPreview(p ? p.image : '');
    EVO.openModal('admin-product');
    setTimeout(function () {
      f.name.focus();
    }, 50);
  }

  // A preview of the chosen picture before it is uploaded.
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
      showError('Please choose a picture for the product.');
      return;
    }
    var fd = new FormData(form);
    fd.set('active', f.active.checked ? '1' : '0');
    var btn = form.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>Saving…</span>');
    fetch(EVO.base + '/api.php?action=admin.productSave', {
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

  /* ---------------- Members' card sales ---------------- */
  var feeForm = root.querySelector('[data-cs-fee-form]');
  if (feeForm) {
    feeForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = feeForm.querySelector('button[type="submit"]');
      var restore = EVO.busy(btn, 'Saving…');
      EVO.api('admin.cardSalesSave', { feePercent: feeForm.elements.feePercent.value })
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (e) {
          restore();
          EVO.toast(e.message, 'error');
        });
    });
  }

  root.addEventListener('click', function (ev) {
    var refund = ev.target.closest('[data-cs-refund]');
    if (!refund) return;
    var note = window.prompt('Record a refund for ' + refund.getAttribute('data-cs-refund') + ' (' + refund.getAttribute('data-amount') + ')?\n\nRefund the buyer in Maya Business Manager first. ' + refund.getAttribute('data-net') + ' will be taken back from the member\'s Available Balance.\n\nWhy, or the Maya refund reference:');
    if (note === null) return;
    var restore = EVO.busy(refund);
    EVO.api('admin.cardOrderRefund', { id: refund.getAttribute('data-cs-refund'), note: note })
      .then(function (res) {
        EVO.reload(res.message);
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });

  root.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.closest('[data-prod-add]')) {
      openForm(null);
      return;
    }
    var edit = t.closest('[data-prod-edit]');
    if (edit) {
      openForm(byId(edit.getAttribute('data-prod-edit')));
      return;
    }
    var act = t.closest('[data-prod-active]');
    if (act) {
      var restore = EVO.busy(act);
      EVO.api('admin.productActive', { id: act.getAttribute('data-prod-active'), active: act.getAttribute('data-to') === '1' })
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (e) {
          restore();
          EVO.toast(e.message, 'error');
        });
      return;
    }
    var del = t.closest('[data-prod-delete]');
    if (del) {
      var carts = parseInt(del.getAttribute('data-carts'), 10) || 0;
      var msg = 'Delete ' + del.getAttribute('data-name') + '?\n\nIt leaves the shop for good' + (carts ? ' and ' + carts + ' member cart' + (carts === 1 ? '' : 's') + ' that hold it' : '') + '. Past orders keep their copy of it. To take it off the shop for now, use Hide instead.';
      if (!window.confirm(msg)) return;
      var restoreDel = EVO.busy(del);
      EVO.api('admin.productDelete', { id: del.getAttribute('data-prod-delete') })
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (e) {
          restoreDel();
          EVO.toast(e.message, 'error');
        });
    }
  });
})();
