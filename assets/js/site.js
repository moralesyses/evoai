/*
 * Members' websites (views/site.php): the menu button on phones, and the
 * contact form (api.php?action=site.contact; the message goes to the
 * member's Contacts). No other script runs on members' sites.
 */
(function () {
  'use strict';
  var body = document.body;

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

  var form = document.querySelector('[data-site-contact]');
  if (!form) return;
  var status = form.querySelector('[data-site-status]');
  var meta = document.querySelector('meta[name="csrf-token"]');
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
    fetch(body.getAttribute('data-api') + '?action=site.contact', {
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
})();
