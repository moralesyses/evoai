/*
 * Backoffice > Digital Card CMS > Card Design: colour theme or own colour,
 * font, the card's blocks (shown / hidden, order) — saved with
 * card.designSave — and the cover photo, uploaded or removed at once
 * (card.coverUpload / card.coverRemove).
 */
(function () {
  'use strict';

  var form = document.getElementById('dc-design-form');
  if (!form || !window.EVO) return;
  var readOnly = !!((window.EVO_PAGE || {}).backoffice || {}).readOnly;
  var list = form.querySelector('[data-dd-blocks]');
  var own = form.querySelector('[data-dd-own]');
  var accent = form.elements.accent;

  if (readOnly) {
    // An admin viewing the member's backoffice: nothing can be changed here.
    EVO.qsa('button, input, select', form).forEach(function (el) {
      el.disabled = true;
    });
    return;
  }

  function mediaUrl(v) {
    v = String(v || '');
    if (!v || /^(https?:)?\/\//i.test(v)) return v;
    return EVO.base + '/' + v.replace(/^\/+/, '');
  }

  /* ---------------- Colour ---------------- */
  function setTheme(id) {
    form.elements.theme.value = id;
    EVO.qsa('[data-dd-theme]', form).forEach(function (b) {
      var on = b.getAttribute('data-dd-theme') === id && !own.checked;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
  }
  form.addEventListener('click', function (ev) {
    var sw = ev.target.closest('[data-dd-theme]');
    if (sw) {
      own.checked = false;
      accent.disabled = true;
      setTheme(sw.getAttribute('data-dd-theme'));
    }
  });
  own.addEventListener('change', function () {
    accent.disabled = !own.checked;
    setTheme(form.elements.theme.value);
  });

  /* ---------------- Blocks: show / order ---------------- */
  function refreshArrows() {
    var rows = EVO.qsa('[data-dd-block]', list);
    rows.forEach(function (row, i) {
      row.querySelector('[data-dd-up]').disabled = i === 0;
      row.querySelector('[data-dd-down]').disabled = i === rows.length - 1;
    });
  }
  list.addEventListener('click', function (ev) {
    var row = ev.target.closest('[data-dd-block]');
    if (!row) return;
    if (ev.target.closest('[data-dd-up]') && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
    else if (ev.target.closest('[data-dd-down]') && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
    else return;
    refreshArrows();
  });
  refreshArrows();

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, 'Saving…');
    EVO.api('card.designSave', {
      theme: form.elements.theme.value,
      accent: own.checked ? accent.value : '',
      font: form.elements.font.value,
      blocks: EVO.qsa('[data-dd-block]', list).map(function (row) {
        return { id: row.getAttribute('data-dd-block'), show: row.querySelector('[data-dd-show]').checked };
      }),
    })
      .then(function (res) {
        restore();
        EVO.toast(res.message);
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });

  /* ---------------- Cover photo ---------------- */
  var box = form.querySelector('[data-dd-cover-box]');
  var file = box.querySelector('[data-dd-cover-file]');
  var pick = box.querySelector('[data-dd-cover-pick]');
  function showCover(path) {
    var img = box.querySelector('[data-dd-cover]');
    if (path) img.setAttribute('src', mediaUrl(path));
    img.hidden = !path;
    box.querySelector('[data-dd-nocover]').hidden = !!path;
    box.querySelector('[data-dd-cover-remove]').hidden = !path;
  }
  pick.addEventListener('click', function () {
    file.click();
  });
  file.addEventListener('change', function () {
    var f = file.files && file.files[0];
    if (!f) return;
    if (f.size > 5 * 1024 * 1024) {
      EVO.toast('That picture is too big (5 MB at most).', 'error');
      file.value = '';
      return;
    }
    var fd = new FormData();
    fd.append('image', f);
    var restore = EVO.busy(pick, 'Uploading…');
    fetch(EVO.base + '/api.php?action=card.coverUpload', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': EVO.csrf, Accept: 'application/json' }, body: fd })
      .then(function (r) {
        return r.json().catch(function () {
          return { ok: false, error: 'The server did not answer. Please try again.' };
        });
      })
      .then(function (res) {
        restore();
        file.value = '';
        if (!res.ok) throw new Error(res.error || 'The cover photo could not be uploaded.');
        showCover(res.cover);
        EVO.toast(res.message);
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });
  box.querySelector('[data-dd-cover-remove]').addEventListener('click', function () {
    var btn = this;
    if (!window.confirm('Remove your cover photo?')) return;
    var restore = EVO.busy(btn, 'Removing…');
    EVO.api('card.coverRemove', {})
      .then(function (res) {
        restore();
        showCover('');
        EVO.toast(res.message);
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });
})();
