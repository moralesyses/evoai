/*
 * 88MMM Circle / 1EVOAI portal — shared front-end runtime (vanilla JS).
 *
 * Conventions used by every view:
 *  - EVO.api(action, data)          POST JSON to api.php (CSRF header included)
 *  - [data-tab-group] / [data-tab] / [data-tab-panel]   client-side tabs
 *  - [data-modal="id"] (hidden)     modals, opened with [data-open-modal="id"]
 *  - [data-dropdown]                dropdown wrapper with toggle + menu
 *  - data-on / data-off             class lists for active / inactive states
 */
(function () {
  'use strict';

  var cfg = window.EVO_CONFIG || {};
  var EVO = (window.EVO = window.EVO || {});
  EVO.csrf = cfg.csrf || '';
  EVO.base = cfg.base || '';
  EVO.user = cfg.user || null;
  EVO.demo = !!cfg.demo;
  EVO.view = cfg.view || 'main';

  /* ------------------------------------------------------------------
   * Utilities
   * ---------------------------------------------------------------- */
  EVO.esc = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  EVO.icon = function (name, cls) {
    var inner = (window.EVO_ICONS || {})[name] || '';
    return (
      '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-' +
      name + (cls ? ' ' + cls : '') + '" aria-hidden="true">' + inner + '</svg>'
    );
  };

  EVO.fmt = {
    num: function (n) {
      return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 3 });
    },
    peso: function (n) {
      return '₱' + EVO.fmt.num(n);
    },
    datetime: function (ms) {
      return new Date(ms).toLocaleString('en-US');
    },
    date: function (ms) {
      return new Date(ms).toLocaleDateString('en-US');
    },
    time: function (ms) {
      return new Date(ms).toLocaleTimeString('en-US');
    },
    hm: function (ms) {
      return new Date(ms).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
  };

  EVO.qs = function (sel, root) {
    return (root || document).querySelector(sel);
  };
  EVO.qsa = function (sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  };

  EVO.sleep = function (ms) {
    return new Promise(function (r) {
      setTimeout(r, ms);
    });
  };

  /** Read a form into a plain object (checkboxes => booleans). */
  EVO.formData = function (form) {
    var out = {};
    EVO.qsa('input, select, textarea', form).forEach(function (el) {
      if (!el.name || el.disabled) return;
      if (el.type === 'checkbox') out[el.name] = el.checked;
      else if (el.type === 'radio') {
        if (el.checked) out[el.name] = el.value;
      } else out[el.name] = el.value;
    });
    return out;
  };

  /* ------------------------------------------------------------------
   * API
   * ---------------------------------------------------------------- */
  EVO.api = function (action, data) {
    return fetch(EVO.base + '/api.php?action=' + encodeURIComponent(action), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': EVO.csrf, Accept: 'application/json' },
      body: JSON.stringify(data || {}),
    })
      .then(function (res) {
        return res
          .json()
          .catch(function () {
            return { ok: false, error: 'Unexpected server response (' + res.status + ').' };
          });
      })
      .then(function (json) {
        if (json && json.csrf) EVO.csrf = json.csrf;
        if (!json || !json.ok) {
          var err = new Error((json && json.error) || 'Request failed.');
          err.response = json;
          throw err;
        }
        return json;
      });
  };

  /* ------------------------------------------------------------------
   * Navigation helpers
   * ---------------------------------------------------------------- */
  EVO.url = function (view, params) {
    var q = new URLSearchParams(Object.assign({ view: view }, params || {}));
    if (view === 'main' && !params) return EVO.base + '/';
    return EVO.base + '/index.php?' + q.toString();
  };

  EVO.go = function (view, params) {
    window.location.href = EVO.url(view, params);
  };

  /** Update a query parameter without reloading (keeps tab state on refresh). */
  EVO.setParam = function (key, value) {
    try {
      var u = new URL(window.location.href);
      if (value == null || value === '') u.searchParams.delete(key);
      else u.searchParams.set(key, value);
      if (!u.searchParams.get('view')) u.searchParams.set('view', EVO.view);
      history.replaceState(null, '', u.pathname.replace(/\/$/, '/index.php') + '?' + u.searchParams.toString() + u.hash);
    } catch (e) {}
  };

  /** Reload the page (optionally after storing a flash message). */
  EVO.reload = function (flash, type) {
    if (flash) EVO.flash(flash, type);
    window.location.reload();
  };

  /* ------------------------------------------------------------------
   * Toasts / flash messages
   * ---------------------------------------------------------------- */
  EVO.flash = function (message, type) {
    try {
      sessionStorage.setItem('evo_flash', JSON.stringify({ message: message, type: type || 'success' }));
    } catch (e) {}
  };

  EVO.toast = function (message, type) {
    var wrap = document.getElementById('evo-toasts');
    if (!wrap) return;
    var ok = type !== 'error';
    var el = document.createElement('div');
    el.setAttribute('role', 'status');
    el.className = 'evo-toast mb-2 p-3 rounded-2xl text-xs flex items-center gap-2 shadow-2xl border ' + (ok ? 'evo-toast-ok border-emerald-500/40 text-emerald-300' : 'evo-toast-err border-rose-500/40 text-rose-300');
    el.innerHTML = EVO.icon(ok ? 'circle-check' : 'circle-alert', 'w-4 h-4 shrink-0 ' + (ok ? 'text-emerald-400' : 'text-rose-400')) + '<span class="font-semibold">' + EVO.esc(message) + '</span>';
    wrap.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .3s';
      el.style.opacity = '0';
      setTimeout(function () {
        el.remove();
      }, 320);
    }, 3200);
  };

  function showFlash() {
    try {
      var raw = sessionStorage.getItem('evo_flash');
      if (!raw) return;
      sessionStorage.removeItem('evo_flash');
      var f = JSON.parse(raw);
      EVO.toast(f.message, f.type);
    } catch (e) {}
  }

  /* ------------------------------------------------------------------
   * Two-state class toggling (data-on / data-off)
   * ---------------------------------------------------------------- */
  function split(s) {
    return (s || '').split(/\s+/).filter(Boolean);
  }
  EVO.setActive = function (el, on) {
    var onC = split(el.getAttribute('data-on'));
    var offC = split(el.getAttribute('data-off'));
    var add = on ? onC : offC;
    var remove = (on ? offC : onC).filter(function (c) {
      return add.indexOf(c) === -1;
    });
    if (remove.length) el.classList.remove.apply(el.classList, remove);
    if (add.length) el.classList.add.apply(el.classList, add);
    el.setAttribute('aria-selected', on ? 'true' : 'false');
  };

  /* ------------------------------------------------------------------
   * Tabs: <div data-tab-group="g" [data-param="tab"]> buttons [data-tab=id]
   *       panels [data-tab-panel=id][data-group=g]
   * ---------------------------------------------------------------- */
  EVO.activateTab = function (group, id) {
    var container = document.querySelector('[data-tab-group="' + group + '"]');
    EVO.qsa('[data-tab][data-group="' + group + '"]').forEach(function (b) {
      var on = b.getAttribute('data-tab') === id;
      EVO.setActive(b, on);
      // Nested elements with their own active/inactive classes (badges, icons).
      EVO.qsa('[data-on]', b).forEach(function (child) {
        EVO.setActive(child, on);
      });
    });
    EVO.qsa('[data-tab-panel][data-group="' + group + '"]').forEach(function (p) {
      p.hidden = p.getAttribute('data-tab-panel') !== id;
    });
    if (container && container.getAttribute('data-param')) EVO.setParam(container.getAttribute('data-param'), id);
    document.dispatchEvent(new CustomEvent('evo:tab', { detail: { group: group, id: id } }));
  };

  /* ------------------------------------------------------------------
   * Modals
   * ---------------------------------------------------------------- */
  EVO.openModal = function (id) {
    var m = document.querySelector('[data-modal="' + id + '"]');
    if (!m) return null;
    m.hidden = false;
    document.dispatchEvent(new CustomEvent('evo:modal-open', { detail: { id: id, el: m } }));
    var focusable = m.querySelector('input:not([type=hidden]):not([readonly]), select, textarea');
    if (focusable && window.matchMedia('(min-width: 768px)').matches) setTimeout(function () { focusable.focus(); }, 30);
    return m;
  };
  EVO.closeModal = function (id) {
    var m = typeof id === 'string' ? document.querySelector('[data-modal="' + id + '"]') : id;
    if (!m) return;
    m.hidden = true;
    document.dispatchEvent(new CustomEvent('evo:modal-close', { detail: { id: m.getAttribute('data-modal'), el: m } }));
  };

  /* ------------------------------------------------------------------
   * Clipboard
   * ---------------------------------------------------------------- */
  EVO.copy = function (text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
      } catch (e) {}
      ta.remove();
      resolve();
    });
  };

  /** Download a text payload as a file. */
  EVO.download = function (filename, content, mime) {
    var blob = new Blob([content], { type: mime || 'text/plain;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () {
      URL.revokeObjectURL(a.href);
      a.remove();
    }, 100);
  };

  /** Button busy state: swaps innerHTML and disables, returns a restore fn. */
  EVO.busy = function (btn, html) {
    if (!btn) return function () {};
    var prev = btn.innerHTML;
    btn.disabled = true;
    if (html) btn.innerHTML = html;
    return function () {
      btn.disabled = false;
      btn.innerHTML = prev;
    };
  };

  /* ------------------------------------------------------------------
   * Global delegated handlers
   * ---------------------------------------------------------------- */
  document.addEventListener('click', function (ev) {
    var t = ev.target;

    var tab = t.closest('[data-tab]');
    if (tab && tab.getAttribute('data-group')) {
      ev.preventDefault();
      EVO.activateTab(tab.getAttribute('data-group'), tab.getAttribute('data-tab'));
      return;
    }

    var opener = t.closest('[data-open-modal]');
    if (opener) {
      ev.preventDefault();
      EVO.openModal(opener.getAttribute('data-open-modal'));
      return;
    }

    var closer = t.closest('[data-close-modal]');
    if (closer) {
      ev.preventDefault();
      EVO.closeModal(closer.closest('[data-modal]'));
      return;
    }

    var copyBtn = t.closest('[data-copy]');
    if (copyBtn) {
      ev.preventDefault();
      var original = copyBtn.innerHTML;
      EVO.copy(copyBtn.getAttribute('data-copy')).then(function () {
        if (copyBtn.hasAttribute('data-copied-html')) {
          copyBtn.innerHTML = copyBtn.getAttribute('data-copied-html');
          setTimeout(function () {
            copyBtn.innerHTML = original;
          }, 2000);
        }
      });
      return;
    }

    var pw = t.closest('[data-toggle-password]');
    if (pw) {
      ev.preventDefault();
      var ids = pw.getAttribute('data-toggle-password').split(',');
      var show = false;
      ids.forEach(function (id) {
        var input = document.getElementById(id.trim());
        if (!input) return;
        show = input.type === 'password';
        input.type = show ? 'text' : 'password';
      });
      var onEl = pw.querySelector('[data-when-shown]');
      var offEl = pw.querySelector('[data-when-hidden]');
      if (onEl) onEl.hidden = !show;
      if (offEl) offEl.hidden = show;
      if (pw.hasAttribute('data-title-shown')) pw.title = show ? pw.getAttribute('data-title-shown') : pw.getAttribute('data-title-hidden');
      return;
    }

    var ddToggle = t.closest('[data-dropdown-toggle]');
    if (ddToggle) {
      ev.preventDefault();
      var dd = ddToggle.closest('[data-dropdown]');
      var menu = dd && dd.querySelector('[data-dropdown-menu]');
      if (menu) {
        var willOpen = menu.hidden;
        closeDropdowns();
        menu.hidden = !willOpen;
        ddToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      }
      return;
    }
    if (!t.closest('[data-dropdown-menu]')) closeDropdowns();

    // Switch the signed-in account (header dropdown & admin "Log in as").
    var sw = t.closest('[data-switch-user]');
    if (sw) {
      ev.preventDefault();
      var target = sw.getAttribute('data-switch-user');
      EVO.api('auth.switch', { userId: target })
        .then(function () {
          var dest = sw.getAttribute('data-switch-dest');
          if (dest) EVO.go(dest);
          else window.location.reload();
        })
        .catch(function (e) {
          EVO.toast(e.message, 'error');
        });
      return;
    }

    var out = t.closest('[data-logout]');
    if (out) {
      ev.preventDefault();
      EVO.api('auth.logout').finally(function () {
        try {
          localStorage.setItem('neo_is_authenticated', 'false');
        } catch (e) {}
        window.location.href = EVO.url('main');
      });
    }
  });

  function closeDropdowns() {
    EVO.qsa('[data-dropdown-menu]').forEach(function (m) {
      if (!m.hidden) m.hidden = true;
    });
  }

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    closeDropdowns();
    var open = EVO.qsa('[data-modal]').filter(function (m) {
      return !m.hidden;
    });
    if (open.length) EVO.closeModal(open[open.length - 1]);
  });

  /* ------------------------------------------------------------------
   * PWA install button (beforeinstallprompt / iOS instructions)
   * ---------------------------------------------------------------- */
  var deferredPrompt = null;
  function setupInstall() {
    var installBtn = document.getElementById('pwa-install');
    var iosBtn = document.getElementById('pwa-ios');
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (standalone) return;
    var isIOS = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
    if (isIOS && iosBtn) iosBtn.hidden = false;
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
      if (installBtn) installBtn.hidden = false;
      if (iosBtn) iosBtn.hidden = true;
    });
    window.addEventListener('appinstalled', function () {
      deferredPrompt = null;
      if (installBtn) installBtn.hidden = true;
    });
    if (installBtn)
      installBtn.addEventListener('click', function () {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (choice) {
          if (choice.outcome === 'accepted') installBtn.hidden = true;
          deferredPrompt = null;
        });
      });
  }

  /* ------------------------------------------------------------------
   * E2EE identity key upkeep for signed-in members
   * ---------------------------------------------------------------- */
  var ensureKeyPromise = null;
  var ensureKeyUser = null;
  EVO.ensureKey = function () {
    if (!EVO.user || !window.EvoCrypto || !window.EvoCrypto.supported) return Promise.resolve(null);
    if (ensureKeyPromise && ensureKeyUser === EVO.user.id) return ensureKeyPromise;
    ensureKeyUser = EVO.user.id;
    ensureKeyPromise = window.EvoCrypto.getKeyPair(EVO.user.id).then(function (kp) {
      var pub = JSON.stringify(kp.publicKeyJwk);
      var server = null;
      try {
        server = EVO.user.publicKey ? JSON.parse(EVO.user.publicKey) : null;
      } catch (e) {}
      if (!server || server.x !== kp.publicKeyJwk.x || server.y !== kp.publicKeyJwk.y) {
        return EVO.api('keys.publish', { publicKey: pub })
          .then(function () {
            EVO.user.publicKey = pub;
            return kp;
          })
          .catch(function () {
            return kp;
          });
      }
      return kp;
    });
    return ensureKeyPromise;
  };

  /* ------------------------------------------------------------------
   * Boot
   * ---------------------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    // The theme and phone-frame toggles were removed: forget choices saved by them.
    try {
      localStorage.removeItem('neo_theme');
      localStorage.removeItem('evo_device_mode');
    } catch (e) {}
    setupInstall();
    showFlash();
    if (EVO.user) {
      try {
        localStorage.setItem('neo_is_authenticated', 'true');
      } catch (e) {}
      EVO.ensureKey();
    }
    if ('serviceWorker' in navigator && window.isSecureContext) {
      navigator.serviceWorker.register(EVO.base + '/sw.js').catch(function () {});
    }
  });
})();
