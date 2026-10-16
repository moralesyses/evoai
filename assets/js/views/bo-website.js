/*
 * Backoffice > My Website (views/backoffice/website.php): the website
 * builder. The forms are drawn from the schema the server sends
 * (site_schema() in includes/website.php). Every change saves the draft a
 * moment later (site.save) and reloads the preview at the section being
 * edited. "Start from a design" replaces the draft with one of the starting
 * designs (site.design). Publish puts the draft live: at the member's portal address, and
 * on their .com domain when they own one (data.domain).
 */
(function () {
  'use strict';
  var data = (window.EVO_PAGE || {}).website;
  var root = document.getElementById('bo-website');
  var readOnly = !!((window.EVO_PAGE || {}).backoffice || {}).readOnly;
  var saveTimer = null;

  // Delete for good (also offered while the tab is locked, for a site saved earlier).
  var hasDomain = !!(data && data.domain);
  var deleteBtn = root && root.querySelector('[data-wb-delete]');
  if (deleteBtn && !readOnly) {
    deleteBtn.addEventListener('click', function () {
      var typed = window.prompt('Delete your website for good?\n\nIts sections, the published website and every image you uploaded are removed' + (hasDomain ? ', and your domain shows your digital card' : '') + '. Messages from your contact form stay in your Contacts.\n\nType DELETE to confirm.');
      if (typed === null) return;
      if (typed.trim().toUpperCase() !== 'DELETE') {
        EVO.toast('Not deleted: type DELETE to confirm.', 'error');
        return;
      }
      clearTimeout(saveTimer);
      var restore = EVO.busy(deleteBtn, 'Deleting…');
      EVO.api('site.delete', { confirm: 'DELETE' })
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (e) {
          restore();
          EVO.toast(e.message, 'error');
        });
    });
  }

  if (!data || !root || !root.querySelector('[data-wb-sections]')) return;
  var schema = data.schema;
  var site = JSON.parse(JSON.stringify(data.site));
  var open = null; // id of the section being edited
  var MAX_SECTIONS = data.maxSections || 30;
  var saving = false;
  var again = false;
  var savedEl = root.querySelector('[data-wb-saved]');
  var frame = root.querySelector('[data-wb-frame]');
  var sectionsEl = root.querySelector('[data-wb-sections]');

  function esc(s) {
    return EVO.esc(s == null ? '' : String(s));
  }
  function get(path) {
    return path.split('.').reduce(function (o, k) { return o == null ? undefined : o[k]; }, site);
  }
  function set(path, value) {
    var keys = path.split('.');
    var o = site;
    for (var i = 0; i < keys.length - 1; i++) o = o[keys[i]];
    o[keys[keys.length - 1]] = value;
  }
  function defaults(fields) {
    var d = {};
    Object.keys(fields).forEach(function (k) {
      var f = fields[k];
      d[k] = f.type === 'bool' ? false : f.type === 'list' ? [] : f.type === 'select' ? Object.keys(f.options)[0] : '';
    });
    return d;
  }
  // What a newly added section starts with (the rest of its fields are empty), so it shows in the preview.
  var STARTERS = {
    imagetext: { title: 'Title', text: 'Write a few lines here, then add an image.' },
    banner: { heading: 'Your heading' },
    pricing: { title: 'Packages', items: [{ name: 'Package name', price: 'Set your price', features: 'What is included\nOne item per line', buttonText: 'Get started', buttonLink: '#contact' }] },
    steps: { title: 'How it works', items: [{ title: 'First step', text: '' }, { title: 'Second step', text: '' }, { title: 'Third step', text: '' }] },
    stats: { title: 'In numbers', items: [{ value: '0', label: 'What it counts' }] },
    team: { title: 'Team', items: [{ name: 'Name', role: 'Role' }] },
    logos: { title: 'Partners', items: [{ name: 'Partner name' }] },
    location: { title: 'Location & hours', hours: [{ day: 'Monday – Friday', time: '9:00 AM – 6:00 PM' }] },
    links: { title: 'Links', items: [{ label: 'Button text', link: '' }] },
  };
  function starter(type) {
    var fields = schema.sections[type].fields;
    var d = defaults(fields);
    var s = STARTERS[type] || {};
    Object.keys(s).forEach(function (k) {
      d[k] = Array.isArray(s[k])
        ? s[k].map(function (item) { return Object.assign(defaults(fields[k].fields), item); })
        : s[k];
    });
    return d;
  }

  function newId(type) {
    return type + '-' + Math.random().toString(16).slice(2, 6);
  }
  function imageUrl(v) {
    if (!v) return '';
    return /^(https?:)?\/\//i.test(v) ? v : EVO.base + '/' + v.replace(/^\//, '');
  }

  /* ---------------- Fields ---------------- */

  function field(spec, path, value) {
    var id = 'wb-' + path.replace(/\./g, '-');
    var help = spec.help ? '<small>' + esc(spec.help) + '</small>' : '';
    var dis = readOnly ? ' disabled' : '';
    switch (spec.type) {
      case 'bool':
        return '<label class="evo-wb-check"><input type="checkbox" data-path="' + path + '"' + (value ? ' checked' : '') + dis + '><span>' + esc(spec.label) + '</span></label>';
      case 'textarea':
        return '<label class="evo-wb-field" for="' + id + '">' + esc(spec.label) + '<textarea id="' + id + '" data-path="' + path + '" maxlength="' + (spec.max || 1000) + '" rows="' + ((spec.max || 0) > 600 ? 6 : 3) + '"' + dis + '>' + esc(value) + '</textarea>' + help + '</label>';
      case 'select':
        return '<label class="evo-wb-field" for="' + id + '">' + esc(spec.label) + '<select id="' + id + '" data-path="' + path + '"' + dis + '>' +
          Object.keys(spec.options).map(function (k) { return '<option value="' + esc(k) + '"' + (k === value ? ' selected' : '') + '>' + esc(spec.options[k]) + '</option>'; }).join('') + '</select>' + help + '</label>';
      case 'image':
        return '<div class="evo-wb-field">' + esc(spec.label) +
          '<div class="evo-wb-image"><span class="evo-wb-thumb" data-thumb="' + path + '" style="background-image:' + (value ? 'url(&quot;' + esc(imageUrl(value)) + '&quot;)' : 'none') + '"></span>' +
          '<div><input type="text" id="' + id + '" data-path="' + path + '" value="' + esc(value) + '" placeholder="Upload, or paste an image address (https://…)"' + dis + '>' +
          '<div class="evo-wb-image-actions">' +
          '<label class="evo-wb-small" style="display:inline-block">Upload image<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-upload="' + path + '" hidden' + dis + '></label>' +
          (value ? '<button type="button" class="evo-wb-small" data-clear="' + path + '"' + dis + '>Remove</button>' : '') +
          '</div></div></div>' + help + '</div>';
      case 'list':
        var items = Array.isArray(value) ? value : [];
        return '<div class="evo-wb-field">' + esc(spec.label) + ' <small>(' + items.length + ' of ' + spec.maxItems + ')</small><div class="evo-wb-list">' +
          items.map(function (item, i) {
            return '<div class="evo-wb-item"><div class="evo-wb-item-head"><span>' + (i + 1) + '</span><span>' +
              '<button type="button" class="evo-wb-icon" title="Move up" data-item-move="' + path + '" data-i="' + i + '" data-dir="-1"' + (i === 0 || readOnly ? ' disabled' : '') + '>↑</button> ' +
              '<button type="button" class="evo-wb-icon" title="Move down" data-item-move="' + path + '" data-i="' + i + '" data-dir="1"' + (i === items.length - 1 || readOnly ? ' disabled' : '') + '>↓</button> ' +
              '<button type="button" class="evo-wb-icon is-danger" title="Remove" data-item-remove="' + path + '" data-i="' + i + '"' + dis + '>✕</button></span></div>' +
              Object.keys(spec.fields).map(function (k) { return field(spec.fields[k], path + '.' + i + '.' + k, item[k]); }).join('') + '</div>';
          }).join('') +
          '<button type="button" class="evo-wb-small" data-item-add="' + path + '"' + (items.length >= spec.maxItems || readOnly ? ' disabled' : '') + '>+ Add ' + esc(spec.label.replace(/s$/, '').toLowerCase()) + '</button></div></div>';
      case 'color':
        return '<div class="evo-wb-field">' + esc(spec.label) + '<div class="evo-wb-color"><label class="evo-wb-check"><input type="checkbox" data-color-on="' + path + '"' + (value ? ' checked' : '') + dis + '><span>Use my own colour</span></label>' +
          '<input type="color" data-color="' + path + '" value="' + esc(value || (data.themes[site.settings.theme] || {}).accent || '#f3bd35') + '"' + (value ? '' : ' disabled') + dis + '></div>' + help + '</div>';
      default: // text, link, video
        var ph = spec.type === 'link' ? 'https://…, #contact, mailto: or tel:' : spec.type === 'video' ? 'https://www.youtube.com/watch?v=…' : '';
        return '<label class="evo-wb-field" for="' + id + '">' + esc(spec.label) + '<input type="text" id="' + id + '" data-path="' + path + '" value="' + esc(value) + '"' + (spec.max ? ' maxlength="' + spec.max + '"' : '') + (ph ? ' placeholder="' + esc(ph) + '"' : '') + dis + '>' + help + '</label>';
    }
  }

  /* ---------------- Settings ---------------- */

  function renderSettings() {
    var s = schema.settings;
    var themes = '<div class="evo-wb-field">' + esc(s.theme.label) + '<div class="evo-wb-themes">' +
      Object.keys(data.themes).map(function (k) {
        var t = data.themes[k];
        return '<button type="button" class="evo-wb-theme' + (site.settings.theme === k ? ' is-on' : '') + '" data-theme="' + esc(k) + '" aria-pressed="' + (site.settings.theme === k) + '"' + (readOnly ? ' disabled' : '') + '>' +
          '<span class="evo-wb-swatch"><i style="background:' + esc(t.bg) + '"></i><i style="background:' + esc(t.surface) + '"></i><i style="background:' + esc(t.accent) + '"></i></span>' + esc(t.label) + '</button>';
      }).join('') + '</div></div>';
    root.querySelector('[data-wb-settings]').innerHTML =
      field(s.siteName, 'settings.siteName', site.settings.siteName) + themes +
      field(s.accent, 'settings.accent', site.settings.accent) + field(s.font, 'settings.font', site.settings.font) +
      field(s.menu, 'settings.menu', site.settings.menu) + field(s.cardButton, 'settings.cardButton', site.settings.cardButton) +
      field(s.footer, 'settings.footer', site.settings.footer) + field(s.seoTitle, 'settings.seoTitle', site.settings.seoTitle) +
      field(s.seoDescription, 'settings.seoDescription', site.settings.seoDescription);
  }

  /* ---------------- Sections ---------------- */

  function sectionLabel(sec) {
    var d = sec.data || {};
    return d.title || d.heading || '';
  }

  function renderSections() {
    var n = site.sections.length;
    root.querySelector('[data-wb-count]').textContent = n + ' of ' + MAX_SECTIONS;
    sectionsEl.innerHTML = site.sections.map(function (sec, i) {
      var type = schema.sections[sec.type];
      var isOpen = open === sec.id;
      var dis = readOnly ? ' disabled' : '';
      return '<div class="evo-wb-section' + (isOpen ? ' is-open' : '') + '" data-section="' + esc(sec.id) + '">' +
        '<div class="evo-wb-section-row' + (sec.hidden ? ' is-hidden' : '') + '">' +
        '<button type="button" class="evo-wb-section-title" data-open="' + esc(sec.id) + '" aria-expanded="' + isOpen + '">' + esc(type.label) + '<span>' + esc(sectionLabel(sec)) + '</span></button>' +
        '<button type="button" class="evo-wb-icon" title="Move up" data-move="' + i + '" data-dir="-1"' + (i === 0 || readOnly ? ' disabled' : '') + '>↑</button>' +
        '<button type="button" class="evo-wb-icon" title="Move down" data-move="' + i + '" data-dir="1"' + (i === n - 1 || readOnly ? ' disabled' : '') + '>↓</button>' +
        '<button type="button" class="evo-wb-icon" title="' + (sec.hidden ? 'Show on the website' : 'Hide from the website') + '" data-hide="' + i + '"' + dis + '>' + EVO.icon(sec.hidden ? 'eye-off' : 'eye', '') + '</button>' +
        '<button type="button" class="evo-wb-icon" title="Duplicate" data-dup="' + i + '"' + (n >= MAX_SECTIONS || readOnly ? ' disabled' : '') + '>' + EVO.icon('copy', '') + '</button>' +
        '<button type="button" class="evo-wb-icon is-danger" title="Delete" data-del="' + i + '"' + dis + '>' + EVO.icon('trash-2', '') + '</button>' +
        '</div>' +
        (isOpen ? '<div class="evo-wb-body">' + Object.keys(type.fields).map(function (k) { return field(type.fields[k], 'sections.' + i + '.data.' + k, sec.data[k]); }).join('') + '</div>' : '') +
        '</div>';
    }).join('') || '<p class="evo-wb-body" style="padding-top:12px;color:#71717a;font-size:12px">No sections yet. Add one below.</p>';
    root.querySelector('[data-wb-add]').disabled = n >= MAX_SECTIONS || readOnly;
  }

  function renderAll() {
    renderSettings();
    renderSections();
  }

  /* ---------------- Start from a design ---------------- */

  function renderDesigns() {
    var box = root.querySelector('[data-wb-designs]');
    if (!box) return;
    box.innerHTML = (data.designs || []).map(function (d) {
      return '<div class="evo-wb-design">' +
        '<span class="evo-wb-swatch evo-wb-design-swatch">' + d.colors.map(function (c) { return '<i style="background:' + esc(c) + '"></i>'; }).join('') + '</span>' +
        '<strong>' + esc(d.label) + '</strong><small>' + esc(d.description) + '</small>' +
        '<small class="evo-wb-design-meta">' + esc(d.font) + ' font · ' + d.sections.length + ' sections: ' + esc(d.sections.join(', ')) + '</small>' +
        '<button type="button" class="evo-wb-small" data-design="' + esc(d.key) + '" data-design-label="' + esc(d.label) + '"' + (readOnly ? ' disabled' : '') + '>Use this design</button></div>';
    }).join('');
  }

  function useDesign(btn) {
    if (!window.confirm('Start from the "' + btn.getAttribute('data-design-label') + '" design? It replaces your current draft. Your published website stays as it is until you publish again.')) return;
    clearTimeout(saveTimer);
    var restore = EVO.busy(btn, 'Working…');
    EVO.api('site.design', { design: btn.getAttribute('data-design') })
      .then(function (res) {
        EVO.reload(res.message);
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  }

  /* ---------------- Saving and the preview ---------------- */

  function preview() {
    var url = data.previewUrl + (data.previewUrl.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now() + (open ? '#' + encodeURIComponent(open) : '');
    frame.src = url;
  }

  // Fields the server didn't accept as typed (an invalid link or video address).
  function markRejected(server) {
    root.querySelectorAll('.evo-wb-field.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
      var m = el.querySelector('.evo-wb-error');
      if (m) m.remove();
    });
    root.querySelectorAll('[data-path]').forEach(function (input) {
      var path = input.getAttribute('data-path');
      var mine = get(path);
      var theirs = path.split('.').reduce(function (o, k) { return o == null ? undefined : o[k]; }, server);
      if (typeof mine === 'string' && mine.trim() !== '' && theirs === '') {
        var box = input.closest('.evo-wb-field');
        if (box) {
          box.classList.add('is-invalid');
          box.insertAdjacentHTML('beforeend', '<small class="evo-wb-error">Not saved: check this address.</small>');
        }
      }
    });
  }

  function save(delay) {
    if (readOnly) return;
    clearTimeout(saveTimer);
    savedEl.textContent = 'Saving…';
    saveTimer = setTimeout(function run() {
      if (saving) {
        again = true;
        return;
      }
      saving = true;
      EVO.api('site.save', { site: site })
        .then(function (res) {
          markRejected(res.site);
          savedEl.textContent = 'Draft saved ' + new Date(res.savedAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
          preview();
        })
        .catch(function (e) {
          savedEl.textContent = 'Not saved';
          EVO.toast(e.message, 'error');
        })
        .then(function () {
          saving = false;
          if (again) {
            again = false;
            run();
          }
        });
    }, delay == null ? 900 : delay);
  }

  /* ---------------- Events ---------------- */

  root.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t.matches('input[type=text][data-path], textarea[data-path]')) {
      set(t.getAttribute('data-path'), t.value);
      if (t.closest('.evo-wb-image')) {
        root.querySelector('[data-thumb="' + t.getAttribute('data-path') + '"]').style.backgroundImage = t.value ? 'url("' + imageUrl(t.value).replace(/"/g, '%22') + '")' : 'none';
      }
      if (/^sections\.\d+\.data\.(title|heading)$/.test(t.getAttribute('data-path'))) {
        var row = t.closest('[data-section]').querySelector('.evo-wb-section-title span');
        if (row) row.textContent = t.value;
      }
      save();
    } else if (t.matches('[data-color]')) {
      set(t.getAttribute('data-color'), t.value.toLowerCase());
      save();
    }
  });

  root.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t.matches('input[type=checkbox][data-path]')) {
      set(t.getAttribute('data-path'), t.checked);
      save(200);
    } else if (t.matches('select[data-path]')) {
      set(t.getAttribute('data-path'), t.value);
      save(200);
    } else if (t.matches('[data-color-on]')) {
      var picker = root.querySelector('[data-color="' + t.getAttribute('data-color-on') + '"]');
      picker.disabled = !t.checked;
      set(t.getAttribute('data-color-on'), t.checked ? picker.value.toLowerCase() : '');
      save(200);
    } else if (t.matches('[data-upload]')) {
      upload(t);
    }
  });

  function upload(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    var path = input.getAttribute('data-upload');
    var label = input.closest('label');
    label.firstChild.textContent = 'Uploading…';
    var fd = new FormData();
    fd.append('image', file);
    fd.append('_csrf', EVO.csrf);
    fetch(EVO.base + '/api.php?action=site.upload', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': EVO.csrf, Accept: 'application/json' }, body: fd })
      .then(function (res) {
        return res.json().catch(function () {
          return { ok: false, error: 'The upload failed (' + res.status + ').' };
        });
      })
      .then(function (res) {
        if (res.csrf) EVO.csrf = res.csrf;
        if (!res.ok) throw new Error(res.error || 'The upload failed.');
        set(path, res.path);
        renderAll();
        save(100);
      })
      .catch(function (e) {
        label.firstChild.textContent = 'Upload image';
        EVO.toast(e.message, 'error');
      });
  }

  root.addEventListener('click', function (ev) {
    var t = ev.target.closest('button');
    if (!t || readOnly) {
      if (t && t.hasAttribute('data-open')) toggleOpen(t.getAttribute('data-open'));
      return;
    }
    var i = Number(t.getAttribute('data-i') || t.getAttribute('data-move') || t.getAttribute('data-hide') || t.getAttribute('data-dup') || t.getAttribute('data-del'));
    if (t.hasAttribute('data-open')) return toggleOpen(t.getAttribute('data-open'));
    if (t.hasAttribute('data-design')) return useDesign(t);
    if (t.hasAttribute('data-theme')) {
      site.settings.theme = t.getAttribute('data-theme');
      renderSettings();
      return save(100);
    }
    if (t.hasAttribute('data-move')) {
      var j = i + Number(t.getAttribute('data-dir'));
      site.sections.splice(j, 0, site.sections.splice(i, 1)[0]);
    } else if (t.hasAttribute('data-hide')) {
      site.sections[i].hidden = !site.sections[i].hidden;
    } else if (t.hasAttribute('data-dup')) {
      var copy = JSON.parse(JSON.stringify(site.sections[i]));
      copy.id = newId(copy.type);
      site.sections.splice(i + 1, 0, copy);
      open = copy.id;
    } else if (t.hasAttribute('data-del')) {
      if (!window.confirm('Delete this ' + schema.sections[site.sections[i].type].label + ' section?')) return;
      site.sections.splice(i, 1);
    } else if (t.hasAttribute('data-item-add')) {
      var listPath = t.getAttribute('data-item-add');
      var spec = specFor(listPath);
      get(listPath).push(defaults(spec.fields));
    } else if (t.hasAttribute('data-item-remove')) {
      get(t.getAttribute('data-item-remove')).splice(i, 1);
    } else if (t.hasAttribute('data-item-move')) {
      var list = get(t.getAttribute('data-item-move'));
      var k = i + Number(t.getAttribute('data-dir'));
      list.splice(k, 0, list.splice(i, 1)[0]);
    } else if (t.hasAttribute('data-clear')) {
      set(t.getAttribute('data-clear'), '');
    } else {
      return;
    }
    renderAll();
    save(150);
  });

  // The schema entry for a list at e.g. "sections.3.data.items".
  function specFor(path) {
    var parts = path.split('.');
    return schema.sections[site.sections[Number(parts[1])].type].fields[parts[3]];
  }

  function toggleOpen(id) {
    open = open === id ? null : id;
    renderSections();
    preview();
  }

  var addType = root.querySelector('[data-wb-add-type]');
  // The section types, grouped ("Introduce yourself", "What you offer", …).
  addType.innerHTML = Object.keys(schema.groups || {}).map(function (g) {
    var types = Object.keys(schema.sections).filter(function (k) { return schema.sections[k].group === g; });
    return types.length ? '<optgroup label="' + esc(schema.groups[g]) + '">' + types.map(function (k) { return '<option value="' + esc(k) + '">' + esc(schema.sections[k].label) + '</option>'; }).join('') + '</optgroup>' : '';
  }).join('') + Object.keys(schema.sections).filter(function (k) { return !(schema.groups || {})[schema.sections[k].group]; }).map(function (k) { return '<option value="' + esc(k) + '">' + esc(schema.sections[k].label) + '</option>'; }).join('');
  root.querySelector('[data-wb-add]').addEventListener('click', function () {
    if (readOnly || site.sections.length >= MAX_SECTIONS) return;
    var type = addType.value;
    var d = starter(type);
    if (type === 'contact') {
      d.form = true;
      d.showEmail = true;
    }
    if (type === 'shop') d.title = 'Shop';
    var sec = { id: type === 'contact' && !site.sections.some(function (s) { return s.id === 'contact'; }) ? 'contact' : newId(type), type: type, hidden: false, data: d };
    site.sections.push(sec);
    open = sec.id;
    renderSections();
    save(100);
    var el = sectionsEl.querySelector('[data-section="' + sec.id + '"]');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  root.querySelectorAll('[data-wb-device]').forEach(function (b) {
    b.addEventListener('click', function () {
      root.querySelectorAll('[data-wb-device]').forEach(function (x) {
        x.classList.toggle('is-on', x === b);
        x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
      });
      root.querySelector('[data-wb-frame-wrap]').classList.toggle('is-phone', b.getAttribute('data-wb-device') === 'phone');
    });
  });

  function action(btn, name, confirmText) {
    if (!btn || readOnly) return;
    btn.addEventListener('click', function () {
      if (confirmText && !window.confirm(confirmText)) return;
      clearTimeout(saveTimer);
      var restore = EVO.busy(btn, 'Working…');
      EVO.api(name, name === 'site.publish' ? { site: site } : {})
        .then(function (res) {
          EVO.reload(res.message);
        })
        .catch(function (e) {
          restore();
          EVO.toast(e.message, 'error');
        });
    });
  }
  action(root.querySelector('[data-wb-publish]'), 'site.publish');
  action(root.querySelector('[data-wb-unpublish]'), 'site.unpublish', 'Take down your website? ' + (hasDomain ? 'Visitors of your domain will see your digital card again.' : 'Your card will no longer link to it.') + ' Your draft is kept, so you can publish it again any time.');
  action(root.querySelector('[data-wb-reset]'), 'site.reset', 'Start again from your card profile? Your current draft is replaced (the published website stays until you publish again).');

  renderDesigns();
  renderAll();
  if (readOnly) savedEl.textContent = 'Read-only';
})();
