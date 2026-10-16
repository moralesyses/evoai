/*
 * Members' websites (views/site.php): the menu button on phones, the
 * contact form (api.php?action=site.contact; the message goes to the
 * member's Contacts) and the Shop (api.php?action=card.buy: the buyer pays
 * on Maya's page and comes back here with ?maya=<rrn>). No other script
 * runs on members' sites.
 */
(function () {
  'use strict';
  var body = document.body;
  var meta = document.querySelector('meta[name="csrf-token"]');

  /** POST to api.php; resolves with the reply, rejects with an Error to show. */
  function api(action, data) {
    return fetch(body.getAttribute('data-api') + '?action=' + action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': meta ? meta.content : '' },
      body: JSON.stringify(data),
    })
      .then(function (res) {
        return res.json().catch(function () {
          return { ok: false, error: 'Something went wrong. Please try again.' };
        });
      })
      .then(function (res) {
        if (res.csrf && meta) meta.content = res.csrf;
        if (!res.ok) throw new Error(res.error || 'Something went wrong. Please try again.');
        return res;
      });
  }

  /** ₱42,800 for whole amounts, ₱99.50 otherwise (like peso() in PHP). */
  function peso(n) {
    n = Number(n || 0);
    var whole = Math.abs(n - Math.round(n)) < 0.005;
    return '₱' + (whole ? Math.round(n) : n).toLocaleString('en-US', whole ? {} : { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /* ---------------- Menu ---------------- */
  var toggle = document.querySelector('[data-site-menu-toggle]');
  var menu = document.getElementById('site-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var open = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    menu.addEventListener('click', function (ev) {
      if (ev.target.closest('a')) {
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------------- Contact form ---------------- */
  var form = document.querySelector('[data-site-contact]');
  if (form) {
    var status = form.querySelector('[data-site-status]');
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      var data = { site: body.getAttribute('data-site') };
      ['name', 'email', 'phone', 'message', 'company_website'].forEach(function (k) {
        data[k] = form.elements[k].value.trim();
      });
      status.className = 'site-form-status';
      if (!data.name || !data.message || (!data.email && !data.phone)) {
        status.textContent = 'Please enter your name, a message, and your email or phone number.';
        status.classList.add('is-err');
        return;
      }
      btn.disabled = true;
      status.textContent = 'Sending…';
      api('site.contact', data)
        .then(function (res) {
          status.textContent = res.message;
          status.classList.add('is-ok');
          form.reset();
        })
        .catch(function (e) {
          status.textContent = e.message;
          status.classList.add('is-err');
        })
        .then(function () {
          btn.disabled = false;
        });
    });
  }

  /* ---------------- Shop: buy with Maya ---------------- */
  var dialog = document.getElementById('site-buy');
  var buyForm = dialog && dialog.querySelector('[data-site-buy-form]');
  if (buyForm) {
    var preview = body.hasAttribute('data-preview');
    var f = buyForm.elements;
    var buyStatus = buyForm.querySelector('[data-buy-status]');
    var submit = buyForm.querySelector('[data-buy-submit]');
    var current = null;
    var maxQty = function () {
      return current && current.stock !== null ? Math.min(99, current.stock) : 99;
    };
    var renderTotal = function () {
      var q = Math.max(1, Math.min(maxQty(), parseInt(f.quantity.value, 10) || 1));
      f.quantity.value = q;
      var total = Math.round(current.price * q * 100) / 100;
      buyForm.querySelector('[data-buy-total]').textContent = peso(total);
      submit.textContent = 'Pay ' + peso(total) + ' with Maya';
    };
    var setStatus = function (text, err) {
      buyStatus.textContent = text;
      buyStatus.className = 'site-form-status' + (err ? ' is-err' : '');
    };

    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-site-buy]');
      if (!b || b.disabled) return;
      var stock = b.getAttribute('data-stock');
      current = { id: b.getAttribute('data-site-buy'), name: b.getAttribute('data-name'), price: Number(b.getAttribute('data-price')), stock: stock === '' ? null : Number(stock) };
      f.productId.value = current.id;
      buyForm.querySelector('[data-buy-image]').src = b.getAttribute('data-image');
      buyForm.querySelector('[data-buy-name]').textContent = current.name;
      buyForm.querySelector('[data-buy-price]').textContent = peso(current.price) + ' each';
      f.quantity.value = 1;
      f.quantity.max = maxQty();
      setStatus(preview ? 'Preview: visitors can buy once your website is published.' : '', preview);
      renderTotal();
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', '');
    });
    dialog.querySelector('[data-site-buy-close]').addEventListener('click', function () {
      dialog.close ? dialog.close() : dialog.removeAttribute('open');
    });
    // A click on the dimmed backdrop (outside the form) closes it too.
    dialog.addEventListener('click', function (ev) {
      if (ev.target === dialog && dialog.close) dialog.close();
    });
    buyForm.addEventListener('click', function (ev) {
      var step = ev.target.closest('[data-buy-step]');
      if (!step) return;
      f.quantity.value = (parseInt(f.quantity.value, 10) || 1) + parseInt(step.getAttribute('data-buy-step'), 10);
      renderTotal();
    });
    f.quantity.addEventListener('change', renderTotal);
    // Typing the missing details clears the "please enter" message.
    buyForm.addEventListener('input', function () {
      if (!preview && buyStatus.classList.contains('is-err')) setStatus('');
    });

    buyForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (preview || !current) return;
      var data = { from: 'site' };
      ['productId', 'quantity', 'name', 'email', 'phone', 'delivery', 'company_website'].forEach(function (k) {
        data[k] = f[k].value.trim();
      });
      if (!data.name || !data.email || !data.phone) {
        setStatus('Please enter your name, email and mobile number.', true);
        return;
      }
      submit.disabled = true;
      setStatus('Opening Maya…');
      api('card.buy', data)
        .then(function (res) {
          window.location.assign(res.redirectUrl);
        })
        .catch(function (e) {
          submit.disabled = false;
          setStatus(e.message, true);
        });
    });
    // Coming back with the browser's Back button from Maya: the button works again.
    window.addEventListener('pageshow', function (ev) {
      if (ev.persisted) {
        submit.disabled = preview;
        if (current) renderTotal();
        setStatus('');
      }
    });
  }

  /* ---------------- Back from Maya, payment not confirmed yet ---------------- */
  var wait = document.querySelector('[data-site-wait]');
  if (wait) {
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      api('payments.mayaStatus', { rrn: wait.getAttribute('data-site-wait') })
        .then(function (res) {
          if (res.state !== 'pending') {
            clearInterval(timer);
            window.location.reload();
          }
        })
        .catch(function () {});
      if (tries >= 40) clearInterval(timer);
    }, 3000);
  }
})();
