/*
 * Management Admin > Mentor Academy (views/admin/academy.php): categories
 * (admin.academyCategorySave / Move / Delete) and learning materials
 * (admin.academySave, sent as multipart form data with a progress bar;
 * admin.academyPublish; admin.academyDelete). The materials list filters by
 * category and searches by title in the page. Each change reloads the tab
 * with a message.
 */
(function () {
  'use strict';

  var root = document.getElementById('admin-academy');
  if (!root || !window.EVO) return;
  var data = (window.EVO_PAGE || {}).adminAcademy || { materials: [], categories: [], extensions: {} };
  var form = document.getElementById('ac-form');
  var catForm = document.getElementById('ac-cat-form');
  var f = form.elements;
  var errEl = EVO.qs('[data-ac-error]', root);
  var coverPreview = EVO.qs('[data-ac-cover-preview]', root);
  var coverNone = EVO.qs('[data-ac-cover-none]', root);
  var objectUrl = null;
  var current = null;

  function byId(list, id) {
    return list.filter(function (x) {
      return x.id === id;
    })[0];
  }

  function showError(el, text) {
    el.textContent = text || '';
    el.hidden = !text;
  }

  function sizeText(bytes) {
    if (bytes >= 1048576) return (Math.round((bytes / 1048576) * 10) / 10) + ' MB';
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }

  /* ---------------- Filter the materials list ---------------- */
  var activeCat = '';
  var search = EVO.qs('[data-ac-search]', root);
  function applyFilter() {
    var q = (search ? search.value : '').trim().toLowerCase();
    var shown = 0;
    EVO.qsa('[data-ac-row]', root).forEach(function (row) {
      var ok = (!activeCat || row.getAttribute('data-cat') === activeCat) && (!q || row.getAttribute('data-text').indexOf(q) >= 0);
      row.hidden = !ok;
      if (ok) shown++;
    });
    var none = EVO.qs('[data-ac-none]', root);
    if (none) none.hidden = shown > 0 || !EVO.qsa('[data-ac-row]', root).length;
  }
  if (search) search.addEventListener('input', applyFilter);

  /* ---------------- The material form ---------------- */
  var kindLabels = { video: 'Video file', document: 'Document file', audio: 'Audio file' };

  // Show the fields the chosen kind needs.
  function renderKind() {
    var kind = f.kind.value;
    var blocks = { video: ['file', 'videoUrl'], document: ['file'], audio: ['file'], article: ['body'], link: ['url'] }[kind] || [];
    EVO.qsa('[data-ac-block]', form).forEach(function (b) {
      b.hidden = blocks.indexOf(b.getAttribute('data-ac-block')) < 0;
    });
    var exts = (data.extensions || {})[kind] || [];
    f.file.accept = exts.map(function (e) {
      return '.' + e;
    }).join(',');
    var keeps = current && current.file && current.kind === kind;
    EVO.qs('[data-ac-file-label]', form).textContent = (kindLabels[kind] || 'File') + (keeps || kind === 'video' ? '' : ' *');
    EVO.qs('[data-ac-file-help]', form).textContent = exts.map(function (e) { return '.' + e; }).join(', ') + ', up to ' + data.limitText + '.' +
      (kind === 'document' ? ' PDFs and pictures open in the page; Word, Excel and PowerPoint files download.' : '');
    var cur = EVO.qs('[data-ac-current-file]', form);
    cur.hidden = !keeps;
    cur.textContent = keeps ? 'Current file: ' + current.file.name + ' (' + current.file.sizeText + '). Choose another to replace it.' : '';
  }
  f.kind.addEventListener('change', renderKind);

  function showCover(src) {
    coverPreview.hidden = !src;
    coverNone.hidden = !!src;
    if (src) coverPreview.src = src;
    else coverPreview.removeAttribute('src');
  }

  function openForm(m) {
    current = m || null;
    form.reset();
    showError(errEl, '');
    EVO.qs('[data-ac-progress]', form).hidden = true;
    f.id.value = m ? m.id : '';
    f.title.value = m ? m.title : '';
    if (m) f.categoryId.value = m.categoryId;
    else if (activeCat) f.categoryId.value = activeCat;
    f.kind.value = m ? m.kind : 'video';
    f.videoUrl.value = m ? m.videoUrl : '';
    f.url.value = m ? m.url : '';
    f.body.value = m ? m.body : '';
    f.description.value = m ? m.description : '';
    f.minutes.value = m && m.minutes ? m.minutes : '';
    f.sortOrder.value = m ? m.sortOrder : '';
    f.published.checked = m ? m.published : true;
    EVO.qs('[data-ac-title]', root).textContent = m ? 'Edit ' + m.title : 'Add material';
    EVO.qs('[data-ac-cover-remove]', form).hidden = !(m && m.coverPath);
    showCover(m ? m.cover : '');
    renderKind();
    EVO.openModal('academy-material');
    setTimeout(function () {
      f.title.focus();
    }, 50);
  }

  f.cover.addEventListener('change', function () {
    var file = this.files && this.files[0];
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
    if (!file) {
      showCover(current ? current.cover : '');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      showError(errEl, 'That picture is too big (5 MB at most).');
      this.value = '';
      return;
    }
    showError(errEl, '');
    objectUrl = URL.createObjectURL(file);
    showCover(objectUrl);
  });

  f.file.addEventListener('change', function () {
    var file = this.files && this.files[0];
    showError(errEl, '');
    if (file && file.size > data.limit) {
      showError(errEl, 'That file is ' + sizeText(file.size) + '; this server takes up to ' + data.limitText + '.' + (f.kind.value === 'video' ? ' Upload the video to YouTube (Unlisted) or Vimeo and paste the link instead.' : ''));
      this.value = '';
      return;
    }
    // A title from the file's name, when there is none yet.
    if (file && !f.title.value.trim()) {
      f.title.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').trim();
    }
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    showError(errEl, '');
    if (!f.title.value.trim()) {
      showError(errEl, 'Please enter a title.');
      f.title.focus();
      return;
    }
    var kind = f.kind.value;
    var file = f.file.files && f.file.files[0];
    var keeps = current && current.file && current.kind === kind;
    if ((kind === 'document' || kind === 'audio') && !file && !keeps) {
      showError(errEl, 'Please choose the ' + (kind === 'audio' ? 'audio' : 'document') + ' file.');
      return;
    }
    if (kind === 'video' && !file && !keeps && !f.videoUrl.value.trim()) {
      showError(errEl, 'Choose the video file, or paste a YouTube or Vimeo link.');
      return;
    }
    if (kind === 'article' && !f.body.value.trim()) {
      showError(errEl, 'Please write the article.');
      return;
    }
    if (kind === 'link' && !/^https?:\/\/\S+$/i.test(f.url.value.trim())) {
      showError(errEl, 'Please enter the web address, starting with https://.');
      return;
    }
    var fd = new FormData(form);
    fd.set('published', f.published.checked ? '1' : '0');
    if (!['video', 'document', 'audio'].includes(kind)) fd.delete('file'); // only send the file the kind uses
    var cover = f.cover.files && f.cover.files[0];
    var total = (file && fd.has('file') ? file.size : 0) + (cover ? cover.size : 0);
    if (total > data.limit) {
      showError(errEl, 'The file and the cover picture together are ' + sizeText(total) + '; this server takes up to ' + data.limitText + ' at once. Choose a smaller picture, or none.');
      return;
    }
    var btn = form.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>Saving…</span>');
    var progress = EVO.qs('[data-ac-progress]', form);
    var bar = EVO.qs('[data-ac-progress-bar]', form);
    var barText = EVO.qs('[data-ac-progress-text]', form);
    var sending = file && fd.has('file');

    // XMLHttpRequest rather than fetch(): it reports upload progress, which matters for videos.
    var xhr = new XMLHttpRequest();
    xhr.open('POST', EVO.base + '/api.php?action=admin.academySave');
    xhr.setRequestHeader('X-CSRF-Token', EVO.csrf);
    xhr.setRequestHeader('Accept', 'application/json');
    if (sending) {
      progress.hidden = false;
      bar.style.width = '0%';
      barText.textContent = 'Uploading ' + file.name + '…';
      xhr.upload.addEventListener('progress', function (e) {
        if (!e.lengthComputable) return;
        var pct = Math.round((e.loaded / e.total) * 100);
        bar.style.width = pct + '%';
        barText.textContent = pct < 100 ? 'Uploading ' + file.name + ': ' + pct + '% of ' + sizeText(e.total) : 'Checking the file…';
      });
    }
    xhr.onload = function () {
      var res;
      try {
        res = JSON.parse(xhr.responseText);
      } catch (e) {
        res = { ok: false, error: xhr.status === 413 ? 'That file is too big for this server.' : 'The server did not answer properly. Please try again.' };
      }
      if (res.ok) {
        EVO.reload(res.message);
        return;
      }
      restore();
      progress.hidden = true;
      showError(errEl, res.error || 'Could not save the material.');
    };
    xhr.onerror = function () {
      restore();
      progress.hidden = true;
      showError(errEl, 'The upload stopped. Check your connection and try again.');
    };
    xhr.send(fd);
  });

  /* ---------------- The category form ---------------- */
  var catErr = EVO.qs('[data-ac-cat-error]', root);
  function openCategory(c) {
    catForm.reset();
    showError(catErr, '');
    catForm.elements.id.value = c ? c.id : '';
    catForm.elements.name.value = c ? c.name : '';
    catForm.elements.description.value = c ? c.description : '';
    EVO.qs('[data-ac-cat-title]', root).textContent = c ? 'Rename ' + c.name : 'Add category';
    EVO.openModal('academy-category');
    setTimeout(function () {
      catForm.elements.name.focus();
    }, 50);
  }
  catForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    showError(catErr, '');
    var e = catForm.elements;
    if (!e.name.value.trim()) {
      showError(catErr, 'Please enter the category name.');
      return;
    }
    var restore = EVO.busy(catForm.querySelector('button[type="submit"]'), 'Saving…');
    EVO.api('admin.academyCategorySave', { id: e.id.value, name: e.name.value, description: e.description.value })
      .then(function (res) {
        EVO.reload(res.message);
      })
      .catch(function (err) {
        restore();
        showError(catErr, err.message);
      });
  });

  /* ---------------- Buttons ---------------- */
  function act(btn, action, payload, confirmText) {
    if (confirmText && !window.confirm(confirmText)) return;
    var restore = EVO.busy(btn);
    EVO.api(action, payload)
      .then(function (res) {
        EVO.reload(res.message);
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  }

  root.addEventListener('click', function (ev) {
    var t = ev.target;
    var b;
    if ((b = t.closest('[data-ac-filter]'))) {
      activeCat = b.getAttribute('data-ac-filter');
      EVO.qsa('[data-ac-filter]', root).forEach(function (x) {
        var on = x === b;
        x.classList.toggle('is-on', on);
        x.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      applyFilter();
    } else if (t.closest('[data-ac-add]')) {
      openForm(null);
    } else if ((b = t.closest('[data-ac-edit]'))) {
      openForm(byId(data.materials, b.getAttribute('data-ac-edit')));
    } else if ((b = t.closest('[data-ac-publish]'))) {
      act(b, 'admin.academyPublish', { id: b.getAttribute('data-ac-publish'), published: b.getAttribute('data-to') === '1' });
    } else if ((b = t.closest('[data-ac-delete]'))) {
      var done = parseInt(b.getAttribute('data-done'), 10) || 0;
      act(b, 'admin.academyDelete', { id: b.getAttribute('data-ac-delete') },
        'Delete ' + b.getAttribute('data-name') + '?\n\nIts file and members\' progress on it' + (done ? ' (' + done + ' completed)' : '') + ' are removed for good. To take it away from members for now, use Hide instead.');
    } else if (t.closest('[data-ac-cat-add]')) {
      openCategory(null);
    } else if ((b = t.closest('[data-ac-cat-edit]'))) {
      openCategory(byId(data.categories, b.getAttribute('data-ac-cat-edit')));
    } else if ((b = t.closest('[data-ac-cat-move]'))) {
      act(b, 'admin.academyCategoryMove', { id: b.getAttribute('data-ac-cat-move'), dir: parseInt(b.getAttribute('data-dir'), 10) });
    } else if ((b = t.closest('[data-ac-cat-delete]'))) {
      var n = parseInt(b.getAttribute('data-count'), 10) || 0;
      if (n > 0) {
        EVO.toast(b.getAttribute('data-name') + ' still has ' + n + ' material' + (n === 1 ? '' : 's') + '. Move or delete ' + (n === 1 ? 'it' : 'them') + ' first.', 'error');
        return;
      }
      act(b, 'admin.academyCategoryDelete', { id: b.getAttribute('data-ac-cat-delete') }, 'Delete the category ' + b.getAttribute('data-name') + '?');
    }
  });
})();
