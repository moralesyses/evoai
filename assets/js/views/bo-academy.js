/*
 * Backoffice > Mentor Academy (views/backoffice/academy.php): filter the
 * lessons by category (also kept in the address as ?cat=), kind and progress,
 * search them, open one in the viewer (video, PDF, audio, picture, article,
 * web link or download), go to the previous / next lesson, and mark lessons
 * completed (academy.view, academy.complete). Progress bars update in place.
 */
(function () {
  'use strict';

  var root = document.getElementById('bo-academy');
  if (!root || !window.EVO) return;
  var page = (window.EVO_PAGE || {}).academy || { materials: [], categories: [] };
  var materials = page.materials || [];
  var catName = {};
  (page.categories || []).forEach(function (c) {
    catName[c.id] = c.name;
  });
  var esc = EVO.esc;
  var viewer = EVO.qs('[data-modal="academy-viewer"]', root);
  var vContent = EVO.qs('[data-ac-v-content]', viewer);
  var vDesc = EVO.qs('[data-ac-v-desc]', viewer);
  var completeBtn = EVO.qs('[data-ac-complete]', viewer);
  var current = null;

  function byId(id) {
    return materials.filter(function (m) {
      return m.id === id;
    })[0];
  }

  function cardOf(id) {
    return EVO.qs('[data-ac-card][data-ac-open="' + id + '"]', root);
  }

  /* ---------------- Filters ---------------- */
  var filter = { cat: '', q: '', kind: '', state: '' };
  var search = EVO.qs('[data-ac-search]', root);
  var kindSel = EVO.qs('[data-ac-kind]', root);
  var stateSel = EVO.qs('[data-ac-state]', root);
  var empty = EVO.qs('[data-ac-empty]', root);

  function apply() {
    var any = false;
    EVO.qsa('[data-ac-section]', root).forEach(function (sec) {
      var catOk = !filter.cat || sec.getAttribute('data-ac-section') === filter.cat;
      var shown = 0;
      EVO.qsa('[data-ac-card]', sec).forEach(function (card) {
        var done = card.getAttribute('data-done') === '1';
        var ok = catOk &&
          (!filter.kind || card.getAttribute('data-kind') === filter.kind) &&
          (!filter.state || (filter.state === 'done') === done) &&
          (!filter.q || card.getAttribute('data-text').indexOf(filter.q) >= 0);
        card.hidden = !ok;
        if (ok) shown++;
      });
      sec.hidden = shown === 0;
      if (shown) any = true;
    });
    if (empty) empty.hidden = any;
  }

  function setCat(id, keepUrl) {
    var chip = EVO.qsa('[data-ac-cat]', root).filter(function (c) {
      return c.getAttribute('data-ac-cat') === id;
    })[0];
    if (!chip) id = '';
    filter.cat = id;
    EVO.qsa('[data-ac-cat]', root).forEach(function (c) {
      var on = c.getAttribute('data-ac-cat') === id;
      c.classList.toggle('is-on', on);
      c.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    apply();
    if (!keepUrl) {
      var params = new URLSearchParams(window.location.search);
      if (id) params.set('cat', id);
      else params.delete('cat');
      window.history.replaceState(null, '', window.location.pathname + '?' + params.toString() + window.location.hash);
    }
  }

  if (search) {
    search.addEventListener('input', function () {
      filter.q = search.value.trim().toLowerCase();
      apply();
    });
  }
  if (kindSel) {
    kindSel.addEventListener('change', function () {
      filter.kind = kindSel.value;
      apply();
    });
  }
  if (stateSel) {
    stateSel.addEventListener('change', function () {
      filter.state = stateSel.value;
      apply();
    });
  }

  /* ---------------- Progress ---------------- */
  function width(done, total) {
    return (total ? Math.round((done * 100) / total) : 0) + '%';
  }

  function renderProgress() {
    var done = materials.filter(function (m) {
      return m.completedAt;
    }).length;
    var el = EVO.qs('[data-ac-done]', root);
    if (el) el.textContent = done;
    el = EVO.qs('[data-ac-pct]', root);
    if (el) el.textContent = width(done, materials.length);
    el = EVO.qs('[data-ac-bar]', root);
    if (el) el.style.width = width(done, materials.length);
    EVO.qsa('[data-ac-section]', root).forEach(function (sec) {
      var id = sec.getAttribute('data-ac-section');
      var list = materials.filter(function (m) {
        return m.categoryId === id;
      });
      var d = list.filter(function (m) {
        return m.completedAt;
      }).length;
      EVO.qs('[data-ac-sec-done]', sec).textContent = d;
      EVO.qs('[data-ac-sec-bar]', sec).style.width = width(d, list.length);
    });
  }

  function renderCard(m) {
    var card = cardOf(m.id);
    if (!card) return;
    card.setAttribute('data-done', m.completedAt ? '1' : '0');
    EVO.qs('[data-ac-done-badge]', card).hidden = !m.completedAt;
    var isNew = EVO.qs('[data-ac-new]', card);
    if (isNew && (m.completedAt || m.viewedAt)) isNew.remove();
  }

  /* ---------------- The viewer ---------------- */

  // An article's text: blank lines split paragraphs, lines starting with "- " make a list.
  function article(text) {
    var html = '';
    String(text || '').split(/\n\s*\n/).forEach(function (block) {
      var para = [];
      var list = [];
      var flush = function () {
        if (para.length) html += '<p>' + para.join('<br>') + '</p>';
        if (list.length) html += '<ul>' + list.join('') + '</ul>';
        para = [];
        list = [];
      };
      block.split('\n').forEach(function (line) {
        var item = line.match(/^\s*[-*•]\s+(.*)$/);
        if (item) {
          if (para.length) flush();
          list.push('<li>' + esc(item[1]) + '</li>');
        } else if (line.trim()) {
          if (list.length) flush();
          para.push(esc(line.trim()));
        }
      });
      flush();
    });
    return '<div class="evo-ac-article">' + html + '</div>';
  }

  function fileLine(m, open) {
    var f = m.file;
    return '<div class="flex flex-wrap items-center gap-2 mt-3 text-[11px] text-zinc-400">' +
      '<span class="break-all">' + esc(f.name) + ' · ' + esc(f.sizeText) + '</span>' +
      (open ? '<a href="' + esc(f.url) + '" target="_blank" rel="noopener" class="text-[#ffda72] hover:text-white font-semibold flex items-center gap-1">' + EVO.icon('external-link', 'w-3 h-3') + 'Open in a new tab</a>' : '') +
      '<a href="' + esc(f.download) + '" class="text-[#ffda72] hover:text-white font-semibold flex items-center gap-1">' + EVO.icon('download', 'w-3 h-3') + 'Download</a></div>';
  }

  function body(m) {
    var f = m.file;
    var t = esc(m.title);
    if (m.kind === 'article') return article(m.body);
    if (m.kind === 'link') {
      var host = '';
      try {
        host = new URL(m.url).hostname;
      } catch (e) {}
      return '<div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 text-center">' +
        '<div class="w-12 h-12 mx-auto rounded-2xl bg-[#f3bd35]/10 text-[#f3bd35] flex items-center justify-center">' + EVO.icon('link', 'w-6 h-6') + '</div>' +
        '<p class="text-sm font-bold text-white mt-3">' + esc(host) + '</p><p class="text-[11px] text-zinc-500 break-all mt-1">' + esc(m.url) + '</p>' +
        '<a href="' + esc(m.url) + '" target="_blank" rel="noopener noreferrer" data-ac-link class="inline-flex items-center gap-1.5 mt-4 px-5 py-2.5 rounded-xl text-xs font-extrabold bg-[#f3bd35] hover:bg-[#ffda72] text-black">' + EVO.icon('external-link', 'w-3.5 h-3.5') + 'Open the link</a></div>';
    }
    if (m.kind === 'video' && !f && m.embed) {
      return '<div class="relative aspect-video w-full rounded-2xl overflow-hidden bg-black"><iframe src="' + esc(m.embed) + '" title="' + t + '" class="absolute inset-0 w-full h-full" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
    }
    if (!f) return '';
    if (f.view === 'video') return '<video class="evo-ac-media" src="' + esc(f.url) + '" controls preload="metadata" playsinline' + (m.cover ? ' poster="' + esc(m.cover) + '"' : '') + '></video>' + fileLine(m, false);
    if (f.view === 'audio') {
      return (m.cover ? '<img src="' + esc(m.cover) + '" alt="" class="w-full aspect-video object-cover rounded-2xl mb-3">' : '') +
        '<audio class="w-full" src="' + esc(f.url) + '" controls preload="metadata"></audio>' + fileLine(m, false);
    }
    if (f.view === 'pdf') return '<iframe class="evo-ac-frame" src="' + esc(f.url) + '#view=FitH" title="' + t + '"></iframe>' + fileLine(m, true);
    if (f.view === 'image') return '<img class="evo-ac-media" src="' + esc(f.url) + '" alt="' + t + '">' + fileLine(m, true);
    var app = { doc: 'Word', docx: 'Word', xls: 'Excel', xlsx: 'Excel', ppt: 'PowerPoint', pptx: 'PowerPoint' }[f.ext] || 'its own app';
    return '<div class="p-6 rounded-2xl bg-zinc-900 border border-zinc-800 text-center">' +
      '<div class="w-12 h-12 mx-auto rounded-2xl bg-[#f3bd35]/10 text-[#f3bd35] flex items-center justify-center">' + EVO.icon('file-text', 'w-6 h-6') + '</div>' +
      '<p class="text-sm font-bold text-white mt-3 break-all">' + esc(f.name) + '</p><p class="text-[11px] text-zinc-500 mt-1">' + esc(f.ext.toUpperCase()) + ' · ' + esc(f.sizeText) + ' · opens in ' + esc(app) + '</p>' +
      '<a href="' + esc(f.download) + '" data-ac-download class="inline-flex items-center gap-1.5 mt-4 px-5 py-2.5 rounded-xl text-xs font-extrabold bg-[#f3bd35] hover:bg-[#ffda72] text-black">' + EVO.icon('download', 'w-3.5 h-3.5') + 'Download</a></div>';
  }

  function renderComplete(m) {
    var base = 'px-5 py-2.5 rounded-xl text-xs font-extrabold flex items-center justify-center gap-1.5 disabled:opacity-60 ';
    if (m.completedAt) {
      completeBtn.className = base + 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40';
      completeBtn.innerHTML = EVO.icon('circle-check-big', 'w-3.5 h-3.5') + '<span>Completed</span>';
      completeBtn.title = 'Press to mark it as not completed';
    } else {
      completeBtn.className = base + 'bg-[#f3bd35] hover:bg-[#ffda72] text-black';
      completeBtn.innerHTML = EVO.icon('check', 'w-3.5 h-3.5') + '<span>Mark as completed</span>';
      completeBtn.title = '';
    }
  }

  // Previous / next follow the lessons as listed (the ones the filters show).
  function sequence() {
    var cards = EVO.qsa('[data-ac-card]', root);
    var shown = cards.filter(function (c) {
      return !c.hidden && !c.closest('[data-ac-section]').hidden;
    });
    var list = (current && shown.some(function (c) { return c.getAttribute('data-ac-open') === current.id; })) ? shown : cards;
    return list.map(function (c) {
      return c.getAttribute('data-ac-open');
    });
  }

  function renderSteps() {
    var ids = sequence();
    var i = ids.indexOf(current.id);
    EVO.qsa('[data-ac-step]', viewer).forEach(function (b) {
      var j = i + parseInt(b.getAttribute('data-ac-step'), 10);
      b.disabled = i < 0 || j < 0 || j >= ids.length;
      b.setAttribute('data-to', b.disabled ? '' : ids[j]);
    });
  }

  function open(id) {
    var m = byId(id);
    if (!m) return;
    current = m;
    EVO.qs('[data-ac-v-meta]', viewer).textContent = [catName[m.categoryId], m.kindLabel, m.minutes ? m.minutes + ' min' : ''].filter(Boolean).join(' · ');
    EVO.qs('[data-ac-v-title]', viewer).textContent = m.title;
    vContent.innerHTML = body(m);
    vDesc.textContent = m.kind === 'article' ? '' : m.description;
    vDesc.hidden = !vDesc.textContent;
    renderComplete(m);
    renderSteps();
    if (viewer.hidden) EVO.openModal('academy-viewer');
    viewer.firstElementChild.scrollTop = 0;
    EVO.api('academy.view', { id: m.id })
      .then(function () {
        m.viewedAt = m.viewedAt || Date.now();
        renderCard(m);
      })
      .catch(function () {});
  }

  completeBtn.addEventListener('click', function () {
    var m = current;
    if (!m) return;
    var done = !m.completedAt;
    var restore = EVO.busy(completeBtn);
    EVO.api('academy.complete', { id: m.id, done: done })
      .then(function (res) {
        restore();
        m.completedAt = res.completedAt;
        renderComplete(m);
        renderCard(m);
        renderProgress();
        if (filter.state) apply();
        if (done) EVO.toast('Lesson completed. Well done!');
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });

  // Closing the viewer stops any video or audio.
  document.addEventListener('evo:modal-close', function (ev) {
    if (ev.detail && ev.detail.id === 'academy-viewer') vContent.innerHTML = '';
  });

  root.addEventListener('click', function (ev) {
    var t = ev.target;
    var b;
    if ((b = t.closest('[data-ac-cat]'))) {
      setCat(b.getAttribute('data-ac-cat'));
    } else if ((b = t.closest('[data-ac-step]'))) {
      if (b.getAttribute('data-to')) open(b.getAttribute('data-to'));
    } else if ((b = t.closest('[data-ac-open]'))) {
      open(b.getAttribute('data-ac-open'));
    } else if (t.closest('[data-ac-reset]')) {
      if (search) search.value = '';
      if (kindSel) kindSel.value = '';
      if (stateSel) stateSel.value = '';
      filter.q = filter.kind = filter.state = '';
      setCat('');
    }
  });

  var start = new URLSearchParams(window.location.search).get('cat');
  if (start) setCat(start, true);
})();
