/* Main Portal: executive sign-in, registration, demo access, password recovery. */
(function () {
  'use strict';
  var page = window.EVO_PAGE || {};
  var demoUsers = page.demoUsers || [];

  var loginForm = document.getElementById('login-form');
  var registerForm = document.getElementById('register-form');
  var errBox = document.getElementById('login-error');
  var okBox = document.getElementById('login-success');
  var regErr = document.getElementById('register-error');

  function show(box, msg) {
    if (!box) return;
    box.querySelector('[data-msg]').textContent = msg;
    box.hidden = !msg;
  }
  function clearMessages() {
    show(errBox, '');
    show(okBox, '');
  }

  var submitIdle = loginForm ? loginForm.querySelector('button[type=submit]').innerHTML : '';
  var spinner =
    '<div class="flex items-center gap-2"><div class="w-4 h-4 border-2 border-black border-t-transparent rounded-full animate-spin"></div><span>Verifying Credentials...</span></div>';

  function setLoading(on) {
    var btn = loginForm.querySelector('button[type=submit]');
    btn.disabled = on;
    btn.innerHTML = on ? spinner : submitIdle;
    EVO.qsa('[data-demo-login]').forEach(function (b) {
      b.disabled = on;
    });
  }

  function afterLogin(user) {
    // Create / publish this browser's E2EE identity key before leaving the page.
    EVO.user = user;
    return EVO.ensureKey().catch(function () {});
  }

  function doLogin(email, password, remember, onSuccess) {
    clearMessages();
    setLoading(true);
    return EVO.api('auth.login', { email: email, password: password, remember: remember })
      .then(function (res) {
        return afterLogin(res.user).then(function () {
          onSuccess(res.user);
        });
      })
      .catch(function (e) {
        show(errBox, e.message || 'Authentication failed. Please verify credentials.');
        setLoading(false);
      });
  }

  if (loginForm) {
    loginForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var d = EVO.formData(loginForm);
      doLogin(d.email, d.password, d.remember, function () {
        show(okBox, 'Authentication verified. Redirecting to Executive Portal...');
        setTimeout(function () {
          EVO.go('backoffice');
        }, 600);
      });
    });
  }

  // One-click demo credentials and "Sign in as …" buttons.
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-demo-login]');
    if (!b) return;
    ev.preventDefault();
    var d = demoUsers[Number(b.getAttribute('data-demo-login'))];
    if (!d) return;
    EVO.activateTab('auth', 'login');
    document.getElementById('login-email').value = d.email;
    document.getElementById('login-password').value = d.password;
    doLogin(d.email, d.password, true, function () {
      show(okBox, 'Signed in as ' + d.name + ' (' + d.roleLabel + ').');
      setTimeout(function () {
        EVO.go(d.role === 'admin' ? 'admin' : 'backoffice');
      }, 500);
    });
  });

  // Hero "Executive Sign In" button.
  var heroSignin = document.getElementById('hero-signin');
  if (heroSignin)
    heroSignin.addEventListener('click', function () {
      EVO.activateTab('auth', 'login');
      var card = document.getElementById('auth-gateway-card');
      if (card) card.scrollIntoView({ behavior: 'smooth' });
    });

  // Clear errors when switching between sign-in and registration.
  document.addEventListener('evo:tab', function (ev) {
    if (ev.detail.group !== 'auth') return;
    if (ev.detail.id === 'login') show(errBox, '');
    else show(regErr, '');
  });

  /* ---------------- Registration ---------------- */
  EVO.qsa('[data-package]').forEach(function (el) {
    function pick() {
      document.getElementById('reg-package').value = el.getAttribute('data-package');
      var amount = registerForm && registerForm.querySelector('[data-reg-amount]');
      if (amount) amount.textContent = el.getAttribute('data-price');
      EVO.qsa('[data-package]').forEach(function (o) {
        EVO.setActive(o, o === el);
        o.setAttribute('aria-checked', o === el ? 'true' : 'false');
      });
    }
    el.addEventListener('click', pick);
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        pick();
      }
    });
  });

  var sponsorInput = document.getElementById('reg-sponsor');
  var sponsorStatus = document.getElementById('reg-sponsor-status');
  var sponsorTimer = null;
  var sponsorPlacement = document.querySelector('[data-sponsor-placement]');
  function checkSponsor() {
    var id = sponsorInput.value.trim();
    var label = sponsorStatus.querySelector('[data-sponsor-label]');
    if (!id) {
      sponsorStatus.hidden = true;
      if (sponsorPlacement) sponsorPlacement.textContent = '';
      return;
    }
    EVO.api('auth.sponsor', { sponsorId: id })
      .then(function (res) {
        sponsorStatus.hidden = false;
        sponsorStatus.classList.toggle('text-emerald-400', res.found);
        sponsorStatus.classList.toggle('text-rose-400', !res.found);
        label.textContent = res.found ? 'Verified (' + res.name + ')' : 'Not found';
        // Where the sponsor places sign-ups.
        if (sponsorPlacement) sponsorPlacement.textContent = res.placementText || '';
      })
      .catch(function () {});
  }
  if (sponsorInput)
    sponsorInput.addEventListener('input', function () {
      clearTimeout(sponsorTimer);
      sponsorTimer = setTimeout(checkSponsor, 400);
    });

  if (registerForm) {
    var regIdle = registerForm.querySelector('button[type=submit]').innerHTML;
    var payWith = document.getElementById('reg-pay-with');

    // Show the fields, note and button text for the chosen way to pay.
    var applyPayWith = function () {
      var method = payWith.value;
      EVO.qsa('[data-pay-with]', registerForm).forEach(function (o) {
        var on = o.getAttribute('data-pay-with') === method;
        EVO.setActive(o, on);
        o.setAttribute('aria-checked', on ? 'true' : 'false');
      });
      document.getElementById('reg-code-fields').hidden = method !== 'code';
      EVO.qsa('[data-pay-note], [data-pay-label]', registerForm).forEach(function (el) {
        el.hidden = (el.getAttribute('data-pay-note') || el.getAttribute('data-pay-label')) !== method;
      });
    };
    EVO.qsa('[data-pay-with]', registerForm).forEach(function (el) {
      function pick() {
        if (el.getAttribute('aria-disabled') === 'true') return;
        payWith.value = el.getAttribute('data-pay-with');
        applyPayWith();
        if (payWith.value === 'code') document.getElementById('reg-code').focus();
      }
      el.addEventListener('click', pick);
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          pick();
        }
      });
    });

    // Back to idle, showing the price of the package that is selected now.
    var restoreRegButton = function () {
      var btn = registerForm.querySelector('button[type=submit]');
      var picked = registerForm.querySelector('[data-package][aria-checked="true"]');
      btn.disabled = false;
      btn.innerHTML = regIdle;
      if (picked) btn.querySelector('[data-reg-amount]').textContent = picked.getAttribute('data-price');
      applyPayWith();
    };
    registerForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      show(regErr, '');
      var d = EVO.formData(registerForm);
      if (d.password !== d.confirm) {
        show(regErr, 'Passwords do not match. Please re-enter.');
        return;
      }
      if (!d.accepted) {
        show(regErr, 'Please accept the Executive Code of Conduct & NDA.');
        return;
      }
      var btn = registerForm.querySelector('button[type=submit]');
      btn.disabled = true;
      if (d.payWith === 'code') {
        if (!d.activationCode || !d.activationPin) {
          show(regErr, 'Please enter your activation code and its PIN.');
          restoreRegButton();
          return;
        }
        btn.innerHTML = '<span>Activating Account...</span>';
        // A valid code pays for the account: it is created and signed in now.
        EVO.api('auth.registerWithCode', d)
          .then(function (res) {
            return afterLogin(res.user).then(function () {
              EVO.go('backoffice');
            });
          })
          .catch(function (e) {
            show(regErr, e.message || 'Registration failed.');
            restoreRegButton();
          });
        return;
      }
      btn.innerHTML = '<span>Opening Maya Checkout...</span>';
      // Pay first: the account is created once Maya confirms the payment, and
      // this browser is signed in on the return page (views/pay.php).
      EVO.api('auth.registerStart', d)
        .then(function (res) {
          window.location.assign(res.redirectUrl);
        })
        .catch(function (e) {
          show(regErr, e.message || 'Registration failed.');
          restoreRegButton();
        });
    });
    // Back from Maya with the browser's Back button: the page is restored as it was left.
    window.addEventListener('pageshow', function (ev) {
      if (ev.persisted) restoreRegButton();
    });
    // "Try Again" after an unpaid sign-up links to #register.
    if (window.location.hash === '#register') EVO.activateTab('auth', 'register');
  }

  /* ---------------- Forgot password ---------------- */
  var forgotForm = document.getElementById('forgot-form');
  var forgotDone = document.getElementById('forgot-done');
  var forgotErr = document.getElementById('forgot-error');
  var forgotPass = null;

  function resetForgot() {
    forgotDone.hidden = true;
    forgotForm.hidden = false;
    forgotErr.hidden = true;
    forgotPass = null;
  }

  var forgotOpen = document.getElementById('forgot-open');
  if (forgotOpen)
    forgotOpen.addEventListener('click', function () {
      resetForgot();
      document.getElementById('forgot-email').value = document.getElementById('login-email').value;
      EVO.openModal('forgot');
    });

  document.addEventListener('evo:modal-close', function (ev) {
    if (ev.detail.id === 'forgot') resetForgot();
  });

  if (forgotForm)
    forgotForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var email = document.getElementById('forgot-email').value.trim();
      if (!email) return;
      forgotErr.hidden = true;
      var restore = EVO.busy(forgotForm.querySelector('button[type=submit]'));
      EVO.api('auth.forgot', { email: email })
        .then(function (res) {
          restore();
          forgotPass = res.demoPass || null;
          forgotDone.querySelector('[data-forgot-email]').textContent = email;
          var wrap = forgotDone.querySelector('[data-forgot-pass-wrap]');
          var useBtn = document.getElementById('forgot-use');
          if (forgotPass) {
            forgotDone.querySelector('[data-forgot-pass]').textContent = forgotPass;
            wrap.hidden = false;
            useBtn.hidden = false;
          } else {
            wrap.hidden = true;
            useBtn.hidden = true;
            if (res.known === false) {
              forgotDone.querySelector('[data-forgot-text]').textContent = 'No executive account is registered for ' + email + '.';
            } else if (res.known === null || res.known === undefined) {
              forgotDone.querySelector('[data-forgot-text]').textContent =
                'If an executive account exists for ' + email + ', a one-time recovery pass has been issued by the administrator.';
            }
          }
          forgotForm.hidden = true;
          forgotDone.hidden = false;
        })
        .catch(function (e) {
          restore();
          forgotErr.textContent = e.message;
          forgotErr.hidden = false;
        });
    });

  var useBtn = document.getElementById('forgot-use');
  if (useBtn)
    useBtn.addEventListener('click', function () {
      document.getElementById('login-email').value = document.getElementById('forgot-email').value;
      document.getElementById('login-password').value = forgotPass || '';
      EVO.closeModal('forgot');
      EVO.activateTab('auth', 'login');
    });
})();
