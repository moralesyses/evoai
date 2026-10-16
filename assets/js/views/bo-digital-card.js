/* Backoffice tab 7 — Digital Card CMS: live preview, service tags, save, preview live card, QR modal. */
(function () {
  'use strict';

  var root = document.getElementById('bo-digital-card');
  if (!root) return;

  var data = {};
  try {
    data = JSON.parse(document.getElementById('dc-data').textContent);
  } catch (e) {}

  var form = document.getElementById('dc-form');
  var services = (data.services || []).slice();

  function pv(name) {
    return root.querySelector('[data-pv="' + name + '"]');
  }

  /** Resolve an avatar value like the server's media_url(): absolute URLs as-is, app paths under the base. */
  function mediaUrl(v) {
    v = String(v || '');
    if (!v || /^(https?:)?\/\//i.test(v) || v.indexOf('data:') === 0) return v;
    return EVO.base + '/' + v.replace(/^\/+/, '');
  }

  /* ------------------------------------------------------------------
   * Live preview (mirrors the reference card preview bound to state "s")
   * ---------------------------------------------------------------- */
  var avatarImg = pv('avatar');
  var initialEl = pv('initial');

  function hideBrokenAvatar() {
    avatarImg.style.display = 'none';
  }
  avatarImg.addEventListener('error', hideBrokenAvatar);
  if (!avatarImg.hidden && avatarImg.complete && avatarImg.naturalWidth === 0 && avatarImg.getAttribute('src')) hideBrokenAvatar();

  var avatarTimer = null;
  function renderAvatar() {
    var v = form.elements.avatar.value;
    var name = form.elements.name.value;
    initialEl.textContent = name.charAt(0);
    avatarImg.alt = name;
    clearTimeout(avatarTimer);
    if (v) {
      var src = mediaUrl(v);
      if (avatarImg.getAttribute('src') === src) {
        avatarImg.hidden = false;
        initialEl.hidden = true;
        return;
      }
      // Debounced so typing a URL does not request every partial path.
      avatarTimer = setTimeout(function () {
        avatarImg.style.display = '';
        avatarImg.setAttribute('src', src);
        avatarImg.hidden = false;
        initialEl.hidden = true;
      }, 400);
    } else {
      avatarImg.hidden = true;
      initialEl.hidden = false;
    }
  }

  /* ------------------------------------------------------------------
   * Profile picture: upload or remove it (saved at once, profile.avatar*).
   * ---------------------------------------------------------------- */
  var photoBox = root.querySelector('[data-dc-photo-box]');
  var photoReadOnly = !!((window.EVO_PAGE || {}).backoffice || {}).readOnly;
  function showPhoto(path) {
    var img = photoBox.querySelector('[data-dc-photo]');
    if (path) img.setAttribute('src', mediaUrl(path));
    img.hidden = !path;
    photoBox.querySelector('[data-dc-photo-initial]').hidden = !!path;
    photoBox.querySelector('[data-dc-photo-remove]').hidden = !path;
    form.elements.avatar.value = path || '';
    renderAvatar();
  }
  if (photoBox && photoReadOnly) {
    // An admin viewing the member's backoffice: nothing can be changed here.
    photoBox.querySelectorAll('[data-dc-photo-pick], [data-dc-photo-remove]').forEach(function (b) {
      b.disabled = true;
      b.title = 'Read-only: this is the member’s backoffice.';
    });
  }
  if (photoBox && !photoReadOnly) {
    var photoFile = photoBox.querySelector('[data-dc-photo-file]');
    var pickBtn = photoBox.querySelector('[data-dc-photo-pick]');
    pickBtn.addEventListener('click', function () {
      photoFile.click();
    });
    photoFile.addEventListener('change', function () {
      var file = photoFile.files && photoFile.files[0];
      if (!file) return;
      if (file.size > 5 * 1024 * 1024) {
        EVO.toast('That picture is too big (5 MB at most).', 'error');
        photoFile.value = '';
        return;
      }
      var fd = new FormData();
      fd.append('image', file);
      var restore = EVO.busy(pickBtn, 'Uploading…');
      fetch(EVO.base + '/api.php?action=profile.avatarUpload', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': EVO.csrf, Accept: 'application/json' }, body: fd })
        .then(function (r) {
          return r.json().catch(function () {
            return { ok: false, error: 'The server did not answer. Please try again.' };
          });
        })
        .then(function (res) {
          restore();
          photoFile.value = '';
          if (!res.ok) throw new Error(res.error || 'The picture could not be uploaded.');
          showPhoto(res.avatar);
          EVO.toast(res.message);
        })
        .catch(function (e) {
          restore();
          EVO.toast(e.message, 'error');
        });
    });
    photoBox.querySelector('[data-dc-photo-remove]').addEventListener('click', function () {
      var btn = this;
      if (!window.confirm('Remove your profile picture? Your card will show your initials instead.')) return;
      var restore = EVO.busy(btn, 'Removing…');
      EVO.api('profile.avatarRemove', {})
        .then(function (res) {
          restore();
          showPhoto('');
          EVO.toast(res.message);
        })
        .catch(function (e) {
          restore();
          EVO.toast(e.message, 'error');
        });
    });
    // A web address typed in instead also updates the small preview.
    form.elements.avatar.addEventListener('change', function () {
      var v = form.elements.avatar.value.trim();
      var img = photoBox.querySelector('[data-dc-photo]');
      if (v) img.setAttribute('src', mediaUrl(v));
      img.hidden = !v;
      photoBox.querySelector('[data-dc-photo-initial]').hidden = !!v;
    });
  }

  function renderServices() {
    var box = pv('services');
    box.innerHTML = services
      .slice(0, 3)
      .map(function (s) {
        return '<span class="px-2 py-0.5 bg-black/40 rounded-md text-[9px] text-zinc-300">' + EVO.esc(s) + '</span>';
      })
      .join('');
    box.hidden = services.length === 0;

    var tags = root.querySelector('[data-dc-tags]');
    tags.innerHTML = services
      .map(function (s) {
        return (
          '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-zinc-900 border border-zinc-700 text-xs text-zinc-200" data-tag="' + EVO.esc(s) + '">' +
          '<span>' + EVO.esc(s) + '</span>' +
          '<button type="button" data-tag-remove class="text-zinc-500 hover:text-red-400" aria-label="Remove ' + EVO.esc(s) + '">' + EVO.icon('trash-2', 'w-3 h-3') + '</button>' +
          '</span>'
        );
      })
      .join('');
  }

  function renderPreview() {
    var f = form.elements;
    pv('name').textContent = f.name.value;
    pv('title').textContent = f.title.value;
    pv('company').textContent = f.company.value;
    pv('location').textContent = f.location.value;
    pv('location-row').hidden = !f.location.value;
    pv('quote').textContent = '"' + f.quote.value + '"';
    pv('quote').hidden = !f.quote.value;
    var theme = f.cardTheme.value;
    var themes = data.themes || {};
    pv('card').className = data.cardBase + ' ' + (themes[theme] || themes.gold);
    pv('theme').textContent = theme || 'Gold';
    renderAvatar();
  }

  form.addEventListener('input', renderPreview);
  form.addEventListener('change', renderPreview);

  /* ------------------------------------------------------------------
   * Services & specialties tags
   * ---------------------------------------------------------------- */
  var tagInput = root.querySelector('[data-tag-input]');

  function addTag() {
    var v = tagInput.value.trim();
    if (!v) return;
    if (services.indexOf(v) === -1) {
      services.push(v);
      renderServices();
    }
    tagInput.value = '';
  }

  root.addEventListener('click', function (ev) {
    var t = ev.target;
    if (t.closest('[data-tag-add]')) {
      addTag();
      return;
    }
    var rm = t.closest('[data-tag-remove]');
    if (rm) {
      var tag = rm.closest('[data-tag]').getAttribute('data-tag');
      services = services.filter(function (s) {
        return s !== tag;
      });
      renderServices();
      return;
    }
    var prev = t.closest('[data-dc-preview]');
    if (prev) {
      openLiveCard(prev);
      return;
    }
    if (t.closest('[data-qr-download]')) downloadQr();
  });

  // Enter in the tag field adds the tag instead of submitting the whole card form.
  tagInput.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      addTag();
    }
  });

  /* ------------------------------------------------------------------
   * Save (profile.update) — "Saved & Live!" then reload
   * ---------------------------------------------------------------- */
  /** Social links must be http(s) URLs server-side: prefix bare domains, map Telegram handles to t.me. */
  function socialUrl(key, v) {
    v = String(v || '').trim();
    if (!v || /^https?:\/\//i.test(v)) return v;
    if (key === 'telegram' && /^@?[A-Za-z0-9_]{3,}$/.test(v)) return 'https://t.me/' + v.replace(/^@/, '');
    return 'https://' + v.replace(/^\/+/, '');
  }

  function buildPatch() {
    var f = form.elements;
    var socials = {};
    var existing = data.socials || {};
    Object.keys(existing).forEach(function (k) {
      socials[k] = existing[k];
    });
    ['linkedin', 'website', 'twitter', 'telegram'].forEach(function (k) {
      socials[k] = socialUrl(k, f['social_' + k].value);
    });
    return {
      name: f.name.value,
      title: f.title.value,
      company: f.company.value,
      location: f.location.value,
      customSlug: f.customSlug.value,
      avatar: f.avatar.value,
      quote: f.quote.value,
      bio: f.bio.value,
      headline: f.headline.value,
      subtitle: f.subtitle.value,
      phone: f.phone.value,
      email: f.email.value,
      whatsapp: f.whatsapp.value,
      socials: socials,
      services: services.slice(),
      cardTheme: f.cardTheme.value,
      nfcAction: f.nfcAction.value,
    };
  }

  function save() {
    return EVO.api('profile.update', { patch: buildPatch() });
  }

  var saveBtn = form.querySelector('button[type="submit"]');
  var saveLabel = form.querySelector('[data-dc-save-label]');
  var savedEl = root.querySelector('[data-dc-saved]');

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var started = Date.now();
    saveBtn.disabled = true;
    saveLabel.textContent = 'Updating My Card...';
    save()
      .then(function () {
        // Reference: 400ms "Updating" state, then "Saved & Live!" for 3s.
        setTimeout(function () {
          saveBtn.disabled = false;
          saveLabel.textContent = 'Save My Member Card Changes';
          savedEl.hidden = false;
          setTimeout(function () {
            savedEl.hidden = true;
            window.location.reload();
          }, 3000);
        }, Math.max(0, 400 - (Date.now() - started)));
      })
      .catch(function (e) {
        saveBtn.disabled = false;
        saveLabel.textContent = 'Save My Member Card Changes';
        EVO.toast(e.message, 'error');
      });
  });

  /* Preview Live: the reference saves the current edits, then opens the public card. */
  function openLiveCard(btn) {
    if (!form.reportValidity()) return;
    btn.disabled = true;
    save()
      .then(function () {
        window.location.href = data.previewUrl;
      })
      .catch(function (e) {
        btn.disabled = false;
        EVO.toast(e.message, 'error');
      });
  }

  /* ------------------------------------------------------------------
   * QR modal: real QR code for the share link + PNG download
   * ---------------------------------------------------------------- */
  var qrDrawn = false;
  function drawQr() {
    if (qrDrawn || typeof window.qrcode !== 'function' || !(data.qrUrl || data.shareUrl)) return;
    try {
      var qr = window.qrcode(0, 'H');
      qr.addData(data.qrUrl || data.shareUrl); // the 1EVOAI card address, not the member's own domain
      qr.make();
      var n = qr.getModuleCount();
      var size = 170;
      var cell = size / n;
      var off = (200 - size) / 2;
      var d = '';
      for (var r = 0; r < n; r++) {
        for (var c = 0; c < n; c++) {
          if (qr.isDark(r, c)) d += 'M' + (off + c * cell).toFixed(3) + ' ' + (off + r * cell).toFixed(3) + 'h' + cell.toFixed(3) + 'v' + cell.toFixed(3) + 'h-' + cell.toFixed(3) + 'z';
        }
      }
      var g = root.querySelector('#public-qr-svg [data-qr-modules]');
      g.innerHTML = '<path fill="#090909" shape-rendering="crispEdges" d="' + d + '"/>';
      qrDrawn = true;
    } catch (e) {}
  }
  document.addEventListener('evo:modal-open', function (ev) {
    if (ev.detail && ev.detail.id === 'dc-qr') drawQr();
  });

  function downloadQr() {
    var svg = document.getElementById('public-qr-svg');
    if (!svg) return;
    var clone = svg.cloneNode(true);
    clone.setAttribute('width', '500');
    clone.setAttribute('height', '500');
    var xml = new XMLSerializer().serializeToString(clone);
    var canvas = document.createElement('canvas');
    canvas.width = 500;
    canvas.height = 500;
    var ctx = canvas.getContext('2d');
    var img = new Image();
    img.onload = function () {
      ctx.drawImage(img, 0, 0, 500, 500);
      var a = document.createElement('a');
      a.download = data.qrFile || '88MMM_QR.png';
      a.href = canvas.toDataURL('image/png');
      document.body.appendChild(a);
      a.click();
      a.remove();
    };
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)));
  }
})();
