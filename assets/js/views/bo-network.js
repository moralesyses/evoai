/*
 * Backoffice tab 3 — Network (reference component "Dn").
 * - summary shortcuts switch the "net" sub-tab
 * - tree cards open / fold the member's downlines below them (deeper levels
 *   load on demand, mlm.genealogyChildren); the eye icon and list rows open
 *   the details modal
 * - "Register Downline" (under the head) and free tree slots (under that
 *   member) open the registration modal, where the member picks Auto (the
 *   leg with fewer downlines), Left or Right (mlm.registerDownline)
 * - "Sign-up Placement" saves where sign-ups with the head's ID go
 *   (mlm.setSignupPlacement); "Register Downline" starts on that choice
 * - network list: search + leg filter (client-side)
 * - unilevel: expand / collapse generation-1 folders (client-side)
 */
(function () {
  'use strict';

  var wrap = document.querySelector('[data-bo-network]');
  if (!wrap) return;

  var nodes = {};
  try {
    nodes = JSON.parse((document.getElementById('bo-net-nodes') || {}).textContent || '{}');
  } catch (e) {}

  /* ---------------- Member details modal ---------------- */
  var detail = document.querySelector('[data-modal="bo-net-node"]');
  function showNode(id) {
    var n = nodes[id];
    if (!n || !detail) return;
    var set = function (key, text) {
      var el = detail.querySelector('[data-nd="' + key + '"]');
      if (el) el.textContent = text;
    };
    set('initial', String(n.fullName || '').charAt(0));
    set('fullName', n.fullName);
    set('handle', '@' + n.username + ' · ' + n.id);
    set('package', n.package);
    set('position', n.position || 'ROOT');
    set('leftPoints', n.leftPoints + ' PV');
    set('rightPoints', n.rightPoints + ' PV');
    set('entryDate', n.entryDate);
    set('status', n.status);
    EVO.openModal('bo-net-node');
  }

  /* ---------------- Genealogy tree: open a member's downlines below them ---------------- */
  // Same markup as views/backoffice/network.php draws for the second level.
  var esc = EVO.esc;
  function line(color, style) {
    return '<span aria-hidden="true" style="position:absolute;background:' + color + ';' + style + '"></span>';
  }
  function firstName(name) {
    return String(name || '').split(' ')[0];
  }
  function slotHtml(node, leg, parentId) {
    var isLeft = leg === 'left';
    var html =
      '<div class="relative flex flex-col items-center px-1" style="padding-top:14px">' +
      line('#3f3f46', (isLeft ? 'left:50%;right:0' : 'left:0;right:50%') + ';top:0;height:2px') +
      line('#3f3f46', 'left:50%;top:0;width:2px;height:14px;margin-left:-1px');
    if (!node) {
      return (
        html +
        '<button type="button" data-net-place="' + esc(parentId) + '" data-net-leg="' + leg + '" class="p-2 rounded-xl bg-zinc-950 border border-dashed border-emerald-500/40 text-emerald-400 text-[10px] font-bold w-24 hover:bg-emerald-500/10">' +
        (isLeft ? '+ Empty L' : '+ Empty R') +
        '</button></div>'
      );
    }
    var below = (node.leftCount || 0) + (node.rightCount || 0);
    return (
      html +
      '<div data-net-node="' + esc(node.id) + '" data-net-expand="' + esc(node.id) + '" aria-expanded="false" role="button" tabindex="0" title="' +
      (below ? 'Show ' + esc(firstName(node.fullName)) + '’s downlines' : 'No downlines yet') +
      '" class="relative cursor-pointer p-2.5 rounded-xl bg-zinc-950 border border-zinc-800 hover:border-zinc-700 text-center w-24 text-[10px]">' +
      '<button type="button" data-net-info="' + esc(node.id) + '" title="Member details" aria-label="Details of ' + esc(node.fullName) + '" class="absolute top-0 right-1 p-0.5 text-zinc-600 hover:text-white">' + EVO.icon('eye', 'w-3 h-3') + '</button>' +
      '<div class="font-bold text-white truncate">' + esc(firstName(node.fullName)) + '</div>' +
      '<div class="text-[8px] text-zinc-500 font-mono">' + esc(node.id) + '</div>' +
      '<div class="text-[8px] mt-0.5 ' + (below ? 'text-[#f3bd35]' : 'text-zinc-600') + '" data-net-below>' + (below ? '▾ ' + below + ' below' : 'No downlines') + '</div>' +
      '</div>' +
      '<div data-net-sub="' + esc(node.id) + '" class="relative flex justify-center" style="padding-top:14px" hidden></div>' +
      '</div>'
    );
  }

  // Click a member: show their two slots below (loading them the first time), click again to fold.
  function toggleBranch(card) {
    var id = card.getAttribute('data-net-expand');
    var sub = card.parentNode.querySelector('[data-net-sub="' + id + '"]');
    if (!sub || card.getAttribute('aria-busy') === 'true') return;
    if (sub.childElementCount > 0) {
      sub.hidden = !sub.hidden;
      card.setAttribute('aria-expanded', sub.hidden ? 'false' : 'true');
      return;
    }
    var below = card.querySelector('[data-net-below]');
    var label = below ? below.textContent : '';
    card.setAttribute('aria-busy', 'true');
    if (below) below.textContent = 'Loading…';
    EVO.api('mlm.genealogyChildren', { id: id })
      .then(function (res) {
        [res.left, res.right].forEach(function (n) {
          if (n) nodes[n.id] = n;
        });
        sub.innerHTML = line('#3f3f46', 'left:50%;top:0;width:2px;height:14px;margin-left:-1px') + slotHtml(res.left, 'left', id) + slotHtml(res.right, 'right', id);
        sub.hidden = false;
        card.setAttribute('aria-expanded', 'true');
      })
      .catch(function (e) {
        EVO.toast(e.message, 'error');
      })
      .then(function () {
        card.removeAttribute('aria-busy');
        if (below) below.textContent = label;
      });
  }

  /* ---------------- Registration modal ---------------- */
  var reg = document.querySelector('[data-modal="bo-net-register"]');
  var form = reg && reg.querySelector('[data-reg-form]');
  var submit = form && form.querySelector('[data-reg-submit]');
  var errBox = form && form.querySelector('[data-reg-error]');
  var okBox = reg && reg.querySelector('[data-reg-success]');
  var codeSel = form && form.querySelector('select[name="activationCode"]');
  var choiceSel = form && form.querySelector('select[name="placement"]');
  // id: the member placed under; choice: 'auto' | 'left' | 'right' (one of that member's legs).
  var placement = { id: '', choice: 'auto' };
  var busy = false;

  function setError(msg) {
    if (!errBox) return;
    errBox.textContent = msg || '';
    errBox.hidden = !msg;
  }

  function refreshSubmit() {
    if (!form || !submit || busy) return;
    var hasCodes = submit.getAttribute('data-has-codes') === '1';
    submit.disabled = !hasCodes || !form.elements.fullName.value || !form.elements.username.value;
  }

  // Downlines in a leg of the member placed under (the counts the tree shows).
  function legCount(leg) {
    var n = nodes[placement.id] || {};
    return Number(leg === 'left' ? n.leftCount : n.rightCount) || 0;
  }
  // Same rule as mlm_leg_placement(): fewer downlines, left on a tie.
  function autoLeg() {
    return legCount('left') <= legCount('right') ? 'left' : 'right';
  }

  function renderChoice() {
    var name = (nodes[placement.id] || {}).fullName || placement.id;
    var auto = placement.choice === 'auto';
    var leg = auto ? autoLeg() : placement.choice;
    var downlines = function (side) {
      var count = legCount(side);
      return count ? count + (count === 1 ? ' downline' : ' downlines') : 'empty';
    };
    EVO.qsa('option', choiceSel).forEach(function (opt) {
      opt.textContent =
        opt.value === 'auto'
          ? 'Auto: ' + autoLeg() + ' leg (' + (legCount('left') === legCount('right') ? 'tie, left first' : 'fewer downlines') + ')'
          : (opt.value === 'left' ? 'Left' : 'Right') + ' leg · ' + downlines(opt.value);
    });
    choiceSel.value = placement.choice;
    reg.querySelector('[data-reg-node]').textContent = name + ' (' + placement.id + ')';
    reg.querySelector('[data-reg-leg]').textContent = leg + ' leg' + (auto ? ' (auto)' : '');
    var hint = legCount(leg)
      ? 'Goes to the first free spot in ' + name + '’s ' + leg + ' leg, level by level, left first.'
      : 'Goes straight into ' + name + '’s empty ' + leg + ' slot.';
    if (auto) hint = 'Auto picks the leg with fewer downlines (left on a tie), now the ' + leg + ' leg. ' + hint;
    form.querySelector('[data-reg-hint]').textContent = hint;
  }

  function openRegister(placementId, choice) {
    if (!reg || !form) return;
    placement = { id: placementId, choice: choice === 'left' || choice === 'right' ? choice : 'auto' };
    renderChoice();
    form.elements.fullName.value = '';
    form.elements.username.value = '';
    if (codeSel) codeSel.selectedIndex = 0;
    setError('');
    if (okBox) okBox.hidden = true;
    form.hidden = false;
    refreshSubmit();
    EVO.openModal('bo-net-register');
  }

  if (form) {
    form.addEventListener('input', refreshSubmit);
    form.addEventListener('change', function (ev) {
      if (ev.target.name !== 'placement') return;
      placement.choice = ev.target.value;
      renderChoice();
    });
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (busy) return;
      var opt = codeSel && codeSel.options[codeSel.selectedIndex];
      var code = opt ? opt.value : '';
      var pin = opt ? opt.getAttribute('data-pin') || '' : '';
      if (!code || !pin) {
        setError('Please select an active activation code and PIN.');
        return;
      }
      busy = true;
      setError('');
      var restore = EVO.busy(submit, 'Enrolling &amp; Awarding Commissions...');
      EVO.api('mlm.registerDownline', {
        placementId: placement.id,
        position: placement.choice,
        username: form.elements.username.value.toLowerCase().trim(),
        fullName: form.elements.fullName.value.trim(),
        activationCode: code,
        activationPin: pin,
      })
        .then(function (res) {
          if (res.success) {
            reg.querySelector('[data-reg-success-msg]').textContent = res.message;
            form.hidden = true;
            okBox.hidden = false;
            // The message says where the member landed; it stays as a toast after the reload.
            setTimeout(function () {
              EVO.closeModal(reg);
              EVO.reload(res.message);
            }, 2200);
          } else {
            setError(res.message);
          }
        })
        .catch(function (e) {
          setError((e && e.message) || 'Registration failed');
        })
        .then(function () {
          busy = false;
          restore();
          refreshSubmit();
        });
    });
  }

  /* ---------------- Sign-up placement (saved as soon as it changes) ---------------- */
  var signupSel = wrap.querySelector('[data-signup-placement]');
  if (signupSel) {
    signupSel.addEventListener('change', function () {
      var choice = signupSel.value;
      var saved = signupSel.getAttribute('data-saved');
      signupSel.disabled = true;
      EVO.api('mlm.setSignupPlacement', { placement: choice })
        .then(function (res) {
          if (!res.success) throw new Error(res.message);
          signupSel.setAttribute('data-saved', choice);
          EVO.toast(res.message);
        })
        .catch(function (e) {
          signupSel.value = saved;
          EVO.toast((e && e.message) || 'Could not save the sign-up placement.', 'error');
        })
        .then(function () {
          signupSel.disabled = false;
        });
    });
  }

  /* ---------------- Network list filters ---------------- */
  var tbody = wrap.querySelector('[data-net-rows]');
  var rows = tbody ? EVO.qsa('tr[data-net-node]', tbody) : [];
  var search = wrap.querySelector('[data-net-search]');
  var legFilter = 'all';

  function applyList() {
    var raw = search ? search.value : '';
    var q = raw.trim() ? raw.toLowerCase() : '';
    var shown = [];
    var hidden = [];
    rows.forEach(function (r) {
      var tag = r.getAttribute('data-leg');
      var ok = !((legFilter === 'left' && tag !== 'left') || (legFilter === 'right' && tag !== 'right'));
      if (ok && q) {
        ok = (r.getAttribute('data-search') || '').split('\n').some(function (f) {
          return f.indexOf(q) !== -1;
        });
      }
      r.hidden = !ok;
      (ok ? shown : hidden).push(r);
    });
    // Hidden rows first so the last visible row stays :last-child (divide-y borders).
    hidden.concat(shown).forEach(function (r) {
      tbody.appendChild(r);
    });
  }
  if (search) search.addEventListener('input', applyList);

  /* ---------------- Unilevel folders ---------------- */
  function toggleUni(row) {
    var item = row.closest('[data-uni-item]');
    if (!item) return;
    var open = item.querySelector(':scope > [data-uni-children]');
    var tpl = item.querySelector(':scope > template[data-uni-tpl]');
    if (!open && !tpl && !item._uniKids) return; // no generation-2 members
    if (open) {
      item._uniKids = open;
      open.remove();
    } else {
      var kids = item._uniKids;
      if (!kids && tpl) kids = tpl.content.firstElementChild.cloneNode(true);
      if (kids) item.appendChild(kids);
    }
    var nowOpen = !open;
    row.setAttribute('aria-expanded', nowOpen ? 'true' : 'false');
    var down = row.querySelector('[data-uni-open]');
    var right = row.querySelector('[data-uni-closed]');
    // SVG elements have no `hidden` IDL property: toggle the attribute itself.
    if (down) down.toggleAttribute('hidden', !nowOpen);
    if (right) right.toggleAttribute('hidden', nowOpen);
  }

  /* ---------------- Delegated events ---------------- */
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t.closest) return;

    var go = t.closest('[data-net-goto]');
    if (go && wrap.contains(go)) {
      EVO.activateTab('net', go.getAttribute('data-net-goto'));
      return;
    }

    var register = t.closest('[data-net-register]');
    if (register && wrap.contains(register)) {
      openRegister(register.getAttribute('data-net-register'), signupSel ? signupSel.getAttribute('data-saved') : 'auto');
      return;
    }

    var place = t.closest('[data-net-place]');
    if (place && wrap.contains(place)) {
      openRegister(place.getAttribute('data-net-place'), place.getAttribute('data-net-leg'));
      return;
    }

    var info = t.closest('[data-net-info]');
    if (info && wrap.contains(info)) {
      showNode(info.getAttribute('data-net-info'));
      return;
    }

    var expand = t.closest('[data-net-expand]');
    if (expand && wrap.contains(expand)) {
      toggleBranch(expand);
      return;
    }

    var node = t.closest('[data-net-node]');
    if (node && wrap.contains(node)) {
      showNode(node.getAttribute('data-net-node'));
      return;
    }

    var lf = t.closest('[data-net-leg-filter]');
    if (lf && wrap.contains(lf)) {
      legFilter = lf.getAttribute('data-net-leg-filter');
      EVO.qsa('[data-net-leg-filter]', wrap).forEach(function (b) {
        var on = b === lf;
        EVO.setActive(b, on);
        b.removeAttribute('aria-selected');
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      applyList();
      return;
    }

    var uni = t.closest('[data-uni-toggle]');
    if (uni && wrap.contains(uni)) toggleUni(uni);
  });

  // Keyboard access for the clickable cards / rows (Enter or Space).
  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    var t = ev.target;
    if (!t || !t.matches || !wrap.contains(t)) return;
    if (t.matches('[data-net-node]') || t.matches('[data-uni-toggle]')) {
      ev.preventDefault();
      t.click();
    }
  });
})();
