/*
 * Admin › Portal CMS & Pricing (reference component "Nn").
 *
 * Keeps an edited copy of the CMS in memory (like the reference's local state),
 * lets the admin filter sections, add/remove list items client-side, then publishes
 * the whole object with admin.cmsUpdate. "Reset Defaults" needs a second click within
 * 4 seconds (admin.cmsReset). After a publish/reset the reference's "Published Live!"
 * state is shown for 3 seconds, then the page reloads with the stored values.
 */
(function () {
  'use strict';

  var root = document.getElementById('cms-editor');
  var form = document.getElementById('cms-form');
  if (!root || !form || !window.EVO) return;

  var page = (window.EVO_PAGE && window.EVO_PAGE.admin) || {};
  var state = clone(page.cms || {});

  // Tag lists: industries/events are de-duplicated and removed by value (as in the reference).
  var BY_VALUE = { industries: true, eventsList: true };
  var NEW_CARD = {
    pillars: { icon: '⭐', title: 'New Pillar', desc: 'Description of pillar value.' },
    meetingsList: { title: 'New Executive Gathering', desc: 'Meaningful connections and syndicate agenda.' },
  };

  var saving = false;
  var saved = false;
  var armed = false;
  var armTimer = null;
  var savedTimer = null;
  var editedAfterSave = false;

  var resetBtn = document.getElementById('cms-reset');
  var publishBtn = document.getElementById('cms-publish');
  var submitBtn = document.getElementById('cms-submit');

  function clone(o) {
    return JSON.parse(JSON.stringify(o || {}));
  }
  function list(key) {
    if (!Array.isArray(state[key])) state[key] = [];
    return state[key];
  }
  function attr(s) {
    return EVO.esc(s);
  }

  /* ------------------------------------------------------------------
   * Rendering of list editors (same markup as views/admin/global_cms.php)
   * ---------------------------------------------------------------- */
  function renderChips(key) {
    var box = root.querySelector('[data-cms-list="' + key + '"]');
    if (!box) return;
    var chipClass = box.getAttribute('data-chip-class') || '';
    box.innerHTML = list(key)
      .map(function (item, i) {
        return (
          '<span class="' + attr(chipClass) + '"><span>' + EVO.esc(item) + '</span>' +
          '<button type="button" data-cms-remove="' + attr(key) + '" data-index="' + i + '" aria-label="Remove ' + attr(item) + '" class="text-zinc-500 hover:text-red-400">' +
          EVO.icon('trash-2', 'w-3 h-3') + '</button></span>'
        );
      })
      .join('');
    renderCount(key);
  }

  function itemInput(listKey, i, key, value, extra, cls) {
    return '<input type="text" data-cms-item="' + listKey + '" data-index="' + i + '" data-key="' + key + '" value="' + attr(value == null ? '' : value) + '" maxlength="200"' + extra + ' class="' + cls + '">';
  }

  function pillarCard(p, i) {
    return (
      '<div class="p-3 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2 relative">' +
      '<button type="button" data-cms-remove="pillars" data-index="' + i + '" class="absolute top-2.5 right-2.5 text-zinc-500 hover:text-red-400 p-1" title="Remove pillar">' + EVO.icon('trash-2', 'w-3.5 h-3.5') + '</button>' +
      '<div class="flex items-center gap-2">' +
      itemInput('pillars', i, 'icon', p.icon, ' placeholder="Icon" aria-label="Pillar icon"', 'w-12 px-2 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-xs text-center text-white focus:outline-none focus:border-[#f3bd35]') +
      itemInput('pillars', i, 'title', p.title, ' required placeholder="Pillar Title" aria-label="Pillar title"', 'flex-1 px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-xs text-white font-bold focus:outline-none focus:border-[#f3bd35]') +
      '</div>' +
      itemInput('pillars', i, 'desc', p.desc, ' placeholder="Short description" aria-label="Pillar description"', 'w-full px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-[11px] text-zinc-300 focus:outline-none focus:border-[#f3bd35]') +
      '</div>'
    );
  }

  function meetingCard(m, i) {
    return (
      '<div class="p-3 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2 relative">' +
      '<button type="button" data-cms-remove="meetingsList" data-index="' + i + '" class="absolute top-2.5 right-2.5 text-zinc-500 hover:text-red-400 p-1" title="Remove meeting">' + EVO.icon('trash-2', 'w-3.5 h-3.5') + '</button>' +
      itemInput('meetingsList', i, 'title', m.title, ' required placeholder="Session Title" aria-label="Session title"', 'w-[85%] px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-xs text-white font-bold focus:outline-none focus:border-[#f3bd35]') +
      itemInput('meetingsList', i, 'desc', m.desc, ' placeholder="Brief session description" aria-label="Session description"', 'w-full px-2.5 py-1.5 rounded-lg bg-zinc-900 border border-zinc-700 text-[11px] text-zinc-300 focus:outline-none focus:border-[#f3bd35]') +
      '</div>'
    );
  }

  function renderCards(key) {
    var box = root.querySelector('[data-cms-cards="' + key + '"]');
    if (!box) return;
    var tpl = key === 'pillars' ? pillarCard : meetingCard;
    box.innerHTML = list(key)
      .map(function (item, i) {
        return tpl(item || {}, i);
      })
      .join('');
    renderCount(key);
  }

  function renderCount(key) {
    var c = root.querySelector('[data-cms-count="' + key + '"]');
    if (c) c.textContent = String(list(key).length);
  }

  /** Push a full CMS object into every control (after save/reset). */
  function applyState(cms) {
    state = clone(cms);
    EVO.qsa('[data-cms-field]', form).forEach(function (el) {
      var v = state[el.getAttribute('data-cms-field')];
      el.value = v == null ? '' : String(v);
    });
    var socials = state.socials || {};
    EVO.qsa('[data-cms-social]', form).forEach(function (el) {
      el.value = socials[el.getAttribute('data-cms-social')] || '';
    });
    EVO.qsa('[data-cms-list]', form).forEach(function (box) {
      renderChips(box.getAttribute('data-cms-list'));
    });
    EVO.qsa('[data-cms-cards]', form).forEach(function (box) {
      renderCards(box.getAttribute('data-cms-cards'));
    });
  }

  /** Current form values merged into the edited copy (the reference sends the full object). */
  function collect() {
    var out = clone(state);
    EVO.qsa('[data-cms-field]', form).forEach(function (el) {
      var key = el.getAttribute('data-cms-field');
      out[key] = el.hasAttribute('data-cms-number') ? Number(el.value) || 0 : el.value;
    });
    var socials = Object.assign({}, out.socials || {});
    EVO.qsa('[data-cms-social]', form).forEach(function (el) {
      socials[el.getAttribute('data-cms-social')] = el.value;
    });
    out.socials = socials;
    return out;
  }

  /* ------------------------------------------------------------------
   * Section filter (reference state "a": all | brand | hero | ...)
   * ---------------------------------------------------------------- */
  function setFilter(id) {
    EVO.qsa('[data-cms-filter]', root).forEach(function (b) {
      EVO.setActive(b, b.getAttribute('data-cms-filter') === id);
    });
    EVO.qsa('[data-cms-section]', form).forEach(function (s) {
      s.hidden = !(id === 'all' || s.getAttribute('data-cms-section') === id);
    });
    EVO.setParam('section', id === 'all' ? null : id);
  }

  /* ------------------------------------------------------------------
   * Button states (saving "l", saved "s", reset armed "d")
   * ---------------------------------------------------------------- */
  function swap(el, on) {
    var onC = (el.getAttribute('data-on') || '').split(/\s+/).filter(Boolean);
    var offC = (el.getAttribute('data-off') || '').split(/\s+/).filter(Boolean);
    el.classList.remove.apply(el.classList, on ? offC : onC);
    el.classList.add.apply(el.classList, on ? onC : offC);
  }

  function renderButtons() {
    swap(resetBtn, armed);
    resetBtn.querySelector('[data-cms-reset-label]').textContent = armed ? 'Confirm Reset?' : 'Reset Defaults';

    publishBtn.disabled = saving;
    publishBtn.innerHTML = saved
      ? EVO.icon('check', 'w-4 h-4 text-black') + '<span>Published Live!</span>'
      : EVO.icon('save', 'w-4 h-4 text-black') + '<span>' + (saving ? 'Publishing...' : 'Save &amp; Publish Live') + '</span>';

    submitBtn.disabled = saving;
    submitBtn.innerHTML = EVO.icon('save', 'w-4 h-4 text-black') + '<span>' + (saving ? 'Publishing All Changes...' : 'Save &amp; Publish Live to Card') + '</span>';

    root.querySelector('[data-cms-saved]').hidden = !saved;
    root.querySelector('[data-cms-ready]').hidden = saved;
  }

  /** Reference shows the saved state for 3s; we then reload so every view reads the stored CMS. */
  function showSaved() {
    saved = true;
    editedAfterSave = false;
    renderButtons();
    clearTimeout(savedTimer);
    savedTimer = setTimeout(function () {
      saved = false;
      renderButtons();
      if (!editedAfterSave) EVO.reload();
    }, 3000);
  }

  /* ------------------------------------------------------------------
   * Validation (server sanitizes too: numbers >= 0, titles required)
   * ---------------------------------------------------------------- */
  function validate() {
    EVO.qsa('[data-cms-number]', form).forEach(function (el) {
      var n = Number(el.value);
      el.setCustomValidity(el.value === '' || !isFinite(n) || n < 0 ? 'Enter a fee of 0 or more.' : '');
    });
    if (form.checkValidity()) return true;
    var bad = form.querySelector(':invalid');
    if (bad) {
      var section = bad.closest('[data-cms-section]');
      if (section && section.hidden) setFilter('all');
      bad.reportValidity();
      bad.focus();
    }
    return false;
  }

  /* ------------------------------------------------------------------
   * Publish / reset
   * ---------------------------------------------------------------- */
  function publish() {
    if (saving || !validate()) return;
    saving = true;
    renderButtons();
    EVO.api('admin.cmsUpdate', { cms: collect() })
      .then(function (res) {
        saving = false;
        if (res.cms) applyState(res.cms);
        showSaved();
      })
      .catch(function (e) {
        saving = false;
        renderButtons();
        EVO.toast(e.message, 'error');
      });
  }

  function disarm() {
    armed = false;
    clearTimeout(armTimer);
    renderButtons();
  }

  function reset() {
    if (saving) return;
    if (!armed) {
      armed = true;
      renderButtons();
      clearTimeout(armTimer);
      armTimer = setTimeout(disarm, 4000);
      return;
    }
    clearTimeout(armTimer);
    saving = true;
    renderButtons();
    EVO.api('admin.cmsReset', {})
      .then(function (res) {
        saving = false;
        armed = false;
        if (res.cms) applyState(res.cms);
        showSaved();
      })
      .catch(function (e) {
        saving = false;
        armed = false;
        renderButtons();
        EVO.toast(e.message, 'error');
      });
  }

  /* ------------------------------------------------------------------
   * List editing
   * ---------------------------------------------------------------- */
  function addTag(key) {
    var input = root.querySelector('[data-cms-new="' + key + '"]');
    if (!input) return;
    var v = input.value.trim();
    if (!v) return;
    if (BY_VALUE[key] && list(key).indexOf(v) !== -1) return;
    list(key).push(v);
    input.value = '';
    renderChips(key);
  }

  function removeItem(key, index) {
    if (NEW_CARD[key]) {
      list(key).splice(index, 1);
      renderCards(key);
      return;
    }
    if (BY_VALUE[key]) {
      var value = list(key)[index];
      state[key] = list(key).filter(function (x) {
        return x !== value;
      });
    } else {
      list(key).splice(index, 1);
    }
    renderChips(key);
  }

  root.addEventListener('click', function (ev) {
    var t = ev.target;
    var f = t.closest('[data-cms-filter]');
    if (f) {
      setFilter(f.getAttribute('data-cms-filter'));
      return;
    }
    if (t.closest('#cms-reset')) {
      reset();
      return;
    }
    if (t.closest('#cms-publish')) {
      publish();
      return;
    }
    var add = t.closest('[data-cms-add]');
    if (add) {
      addTag(add.getAttribute('data-cms-add'));
      editedAfterSave = true;
      return;
    }
    var addCard = t.closest('[data-cms-add-card]');
    if (addCard) {
      var key = addCard.getAttribute('data-cms-add-card');
      list(key).push(clone(NEW_CARD[key]));
      renderCards(key);
      editedAfterSave = true;
      return;
    }
    var rm = t.closest('[data-cms-remove]');
    if (rm) {
      removeItem(rm.getAttribute('data-cms-remove'), parseInt(rm.getAttribute('data-index'), 10));
      editedAfterSave = true;
    }
  });

  form.addEventListener('input', function (ev) {
    editedAfterSave = true;
    var el = ev.target;
    if (el.hasAttribute('data-cms-number')) el.setCustomValidity('');
    if (!el.hasAttribute('data-cms-item')) return;
    var item = list(el.getAttribute('data-cms-item'))[parseInt(el.getAttribute('data-index'), 10)];
    if (item) item[el.getAttribute('data-key')] = el.value;
  });

  // Enter inside a "new item" box adds the item instead of publishing the whole form.
  form.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    var input = ev.target.closest && ev.target.closest('[data-cms-new]');
    if (!input) return;
    ev.preventDefault();
    addTag(input.getAttribute('data-cms-new'));
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    publish();
  });

  // A section kept in the URL (?section=pricing) after a publish reload: bring its filter chip into view.
  var activeFilter = root.querySelector('[data-cms-filter][aria-selected="true"]');
  if (activeFilter && activeFilter.getAttribute('data-cms-filter') !== 'all') {
    activeFilter.parentNode.scrollLeft = activeFilter.offsetLeft - activeFilter.parentNode.offsetLeft - 6;
  }
})();
