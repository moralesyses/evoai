/* 1EVOAI public card: QR code, share, vCard send, membership application, NFC tap, language, keynote video,
   and buying the member's own products with Maya (card.buy). */
(function () {
  'use strict';
  var page = (window.EVO_PAGE || {}).card || {};
  var shareText = 'Welcome to ' + page.name + ', here is my digital executive card: ' + page.shareUrl + ' (Powered by 88MMM Circle / 1EVOAI)';

  function track(action) {
    return EVO.api('analytics.track', { action: action, profileId: page.profileId }).catch(function () {});
  }

  /* ---------------- Broken image fallback ---------------- */
  EVO.qsa('img[data-fallback]').forEach(function (img) {
    img.addEventListener('error', function onErr() {
      img.removeEventListener('error', onErr);
      img.src = img.getAttribute('data-fallback');
    });
  });
  // A member's photo that fails to load shows their initials instead.
  EVO.qsa('img[data-initials]').forEach(function (img) {
    img.addEventListener('error', function () {
      var div = document.createElement('div');
      div.className = 'w-full h-full rounded-full bg-[#090909] flex items-center justify-center text-[#f3bd35] font-black text-3xl sm:text-4xl';
      div.setAttribute('role', 'img');
      div.setAttribute('aria-label', img.alt);
      div.textContent = img.getAttribute('data-initials');
      img.replaceWith(div);
    });
  });

  /* ---------------- QR code (real, scannable; reference styling) ---------------- */
  function drawQr() {
    var svg = document.getElementById('public-qr-svg');
    if (!svg || svg.getAttribute('data-ready') || typeof window.qrcode !== 'function') return;
    var qr = window.qrcode(0, 'H');
    qr.addData(page.qrUrl || page.shareUrl); // the 1EVOAI card address, not the member's own domain
    qr.make();
    var count = qr.getModuleCount();
    var margin = 12;
    var size = (200 - margin * 2) / count;
    var parts = ['<rect width="200" height="200" fill="#ffffff"/>'];
    var inFinder = function (r, c) {
      return (r < 7 && c < 7) || (r < 7 && c >= count - 7) || (r >= count - 7 && c < 7);
    };
    // Finder patterns as rounded squares (like the reference artwork).
    [[0, 0], [0, count - 7], [count - 7, 0]].forEach(function (p) {
      var x = margin + p[1] * size;
      var y = margin + p[0] * size;
      parts.push('<rect x="' + x + '" y="' + y + '" width="' + 7 * size + '" height="' + 7 * size + '" rx="' + size * 1.3 + '" fill="#090909"/>');
      parts.push('<rect x="' + (x + size) + '" y="' + (y + size) + '" width="' + 5 * size + '" height="' + 5 * size + '" rx="' + size * 0.9 + '" fill="#ffffff"/>');
      parts.push('<rect x="' + (x + 2 * size) + '" y="' + (y + 2 * size) + '" width="' + 3 * size + '" height="' + 3 * size + '" rx="' + size * 0.7 + '" fill="#090909"/>');
    });
    var center = count / 2;
    var logoR = count * 0.16;
    var dots = [];
    for (var r = 0; r < count; r++) {
      for (var c = 0; c < count; c++) {
        if (!qr.isDark(r, c) || inFinder(r, c)) continue;
        var dr = r + 0.5 - center;
        var dc = c + 0.5 - center;
        if (Math.sqrt(dr * dr + dc * dc) < logoR + 0.6) continue; // keep the "88" badge clear
        dots.push('<rect x="' + (margin + c * size + size * 0.06) + '" y="' + (margin + r * size + size * 0.06) + '" width="' + size * 0.88 + '" height="' + size * 0.88 + '" rx="' + size * 0.25 + '"/>');
      }
    }
    parts.push('<g fill="#181818">' + dots.join('') + '</g>');
    parts.push('<circle cx="100" cy="100" r="18" fill="#090909" stroke="#f3bd35" stroke-width="2"/>');
    parts.push('<text x="100" y="105" font-size="11" font-weight="900" fill="#f3bd35" text-anchor="middle" font-family="sans-serif">88</text>');
    svg.innerHTML = parts.join('');
    svg.setAttribute('data-ready', '1');
  }

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-card-qr]')) return;
    ev.preventDefault();
    track('qr_scan');
    drawQr();
    EVO.openModal('card-qr');
  });

  var dl = document.getElementById('qr-download');
  if (dl)
    dl.addEventListener('click', function () {
      var svg = document.getElementById('public-qr-svg');
      if (!svg) return;
      var xml = new XMLSerializer().serializeToString(svg);
      var canvas = document.createElement('canvas');
      canvas.width = 500;
      canvas.height = 500;
      var ctx = canvas.getContext('2d');
      var img = new Image();
      img.onload = function () {
        ctx.drawImage(img, 0, 0, 500, 500);
        var a = document.createElement('a');
        a.download = String(page.name || 'Card').replace(/\s+/g, '_') + '_88MMM_QR.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
      };
      img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)));
    });

  /* ---------------- Share ---------------- */
  var shareLinks = {
    whatsapp: function () {
      return 'https://api.whatsapp.com/send?text=' + encodeURIComponent(shareText);
    },
    telegram: function () {
      return 'https://telegram.me/share/url?url=' + encodeURIComponent(page.shareUrl) + '&text=' + encodeURIComponent(shareText);
    },
    linkedin: function () {
      return 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(page.shareUrl);
    },
    twitter: function () {
      return 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText);
    },
    facebook: function () {
      return 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(page.shareUrl);
    },
  };
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-share]');
    if (!b) return;
    var fn = shareLinks[b.getAttribute('data-share')];
    if (fn) window.open(fn(), '_blank', 'noopener');
  });

  /* ---------------- Send vCard via WhatsApp ---------------- */
  var vForm = document.getElementById('vcard-send-form');
  if (vForm)
    vForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var digits = vForm.elements.phone.value.replace(/[^0-9]/g, '');
      if (!digits) return;
      var text = encodeURIComponent('Welcome to ' + page.name + ', Here is my digital vCard: ' + page.shareUrl + '\nPowered by 88MMM Circle');
      window.open('https://api.whatsapp.com/send/?phone=' + digits + '&text=' + text, '_blank', 'noopener');
      EVO.closeModal('card-vcard');
    });

  /* ---------------- vCard download tracking ----------------
     vcard.php records the download itself; nothing to do client-side. */

  /* ---------------- NFC tap simulation ---------------- */
  var tap = document.getElementById('card-nfc-tap');
  if (tap)
    tap.addEventListener('click', function () {
      track('nfc_tap');
      if ('vibrate' in navigator) navigator.vibrate([40, 60, 40]);
    });

  /* ---------------- Language (headline + join button) ---------------- */
  var lang = document.getElementById('card-lang');
  if (lang)
    lang.addEventListener('change', function () {
      var l = lang.value;
      var t = (page.i18n || {})[l] || page.i18n.en;
      var en = l === 'en';
      var parts = { connect: en ? page.headlineEn[0] : t.connect, grow: en ? page.headlineEn[1] : t.grow, prosper: en ? page.headlineEn[2] : t.prosper };
      Object.keys(parts).forEach(function (k) {
        var el = document.querySelector('[data-hl="' + k + '"]');
        if (el) el.textContent = parts[k];
      });
      document.documentElement.lang = l;
      var jb = EVO.qs('[data-join-label]');
      if (jb && !page.heroCtaText) jb.textContent = t.joinBtn;
    });

  /* ---------------- Keynote video ---------------- */
  var video = document.getElementById('card-video');
  if (video)
    video.addEventListener('click', function (ev) {
      if (!ev.target.closest('[data-play-video]')) return;
      var src = page.videoUrl + (page.videoUrl.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1&mute=0';
      video.innerHTML =
        '<iframe src="' + EVO.esc(src) + '" title="Featured Video Keynote" class="w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    });

  /* ---------------- Scroll to top (mobile bar) ---------------- */
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-scroll-top]')) return;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  /* ---------------- Membership application (lead capture) ---------------- */
  var joinModal = document.querySelector('[data-modal="card-join"]');
  var joinForm = document.getElementById('join-form');
  var tiers = {
    executive: {
      label: 'Executive Member',
      price: (document.querySelector('[data-join-tier="executive"]') || {}).textContent,
      desc: 'Includes 1 Smart NFC Executive Card, One-Tap Digital Identity, and Weekly Masterminds.',
      item: 'membership_executive',
    },
    chairmans: {
      label: "Chairman's Circle (VIP)",
      price: (document.querySelector('[data-join-tier="chairmans"]') || {}).textContent,
      desc: 'Includes 7 NFC Cards, 7 Custom Portals, Mastermind Dinners, and VIP introductions.',
      item: 'membership_chairmans',
    },
  };
  function priceOf(tier) {
    var m = /₱[\d,]+/.exec(tiers[tier].price || '');
    return m ? m[0] : '';
  }
  var joinTier = 'executive';
  function setTier(tier) {
    joinTier = tier;
    joinForm.elements.tier.value = tier;
    EVO.qsa('[data-join-tier]', joinModal).forEach(function (b) {
      EVO.setActive(b, b.getAttribute('data-join-tier') === tier);
    });
    joinModal.querySelector('[data-join-label]').textContent = tiers[tier].label;
    joinModal.querySelector('[data-join-desc]').textContent = tiers[tier].desc;
    EVO.qsa('[data-join-price]', joinModal).forEach(function (el) {
      el.textContent = priceOf(tier);
    });
    joinModal.querySelector('[data-join-submit]').textContent = 'Submit Application (' + priceOf(tier) + ')';
    joinModal.querySelector('[data-join-tier-name]').textContent = tier === 'chairmans' ? "Chairman's Circle" : 'Executive Member';
  }

  document.addEventListener('click', function (ev) {
    var j = ev.target.closest('[data-join]');
    if (j && joinModal) {
      ev.preventDefault();
      document.getElementById('join-done').hidden = true;
      document.getElementById('join-form-wrap').hidden = false;
      document.getElementById('join-error').hidden = true;
      setTier(j.getAttribute('data-join'));
      EVO.openModal('card-join');
      return;
    }
    var t = ev.target.closest('[data-join-tier]');
    if (t) {
      setTier(t.getAttribute('data-join-tier'));
      return;
    }
    var pay = ev.target.closest('[data-join-pay]');
    if (pay) {
      // Signing up happens on the sign-up form (Maya Checkout or an activation code and PIN), with the
      // card owner's Referrer ID and this package filled in (page.payUrls, views/card.php).
      EVO.closeModal('card-join');
      window.location.href = (page.payUrls || {})[tiers[joinTier].item] || EVO.url('main', { package: tiers[joinTier].item }) + '#register';
    }
  });

  if (joinForm)
    joinForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var err = document.getElementById('join-error');
      err.hidden = true;
      var d = EVO.formData(joinForm);
      var interest = joinTier === 'chairmans' ? "Chairman's Circle" : 'Executive Member';
      var btn = joinForm.querySelector('button[type=submit]');
      var label = btn.querySelector('[data-join-submit]');
      var prev = label.textContent;
      btn.disabled = true;
      label.textContent = 'Submitting Application...';
      EVO.api('leads.create', {
        name: d.name,
        email: d.email,
        phone: d.phone,
        company: d.company,
        interest: interest,
        message: d.message || 'Applied for ' + interest + ' tier.',
        website_url: d.website_url,
      })
        .then(function () {
          joinForm.reset();
          document.getElementById('join-form-wrap').hidden = true;
          document.getElementById('join-done').hidden = false;
        })
        .catch(function (e) {
          err.textContent = e.message;
          err.hidden = false;
        })
        .finally(function () {
          btn.disabled = false;
          label.textContent = prev;
        });
    });

  /* ---------------- The member's products: buy with Maya ---------------- */
  var buyForm = document.getElementById('card-buy-form');
  if (buyForm) {
    var products = page.products || [];
    var current = null;
    var buyErr = buyForm.querySelector('[data-buy-error]');
    var qty = buyForm.elements.quantity;
    var peso = function (n) {
      return EVO.fmt.peso(n);
    };
    var maxQty = function () {
      return current && current.stock !== null ? Math.min(99, current.stock) : 99;
    };
    var renderTotal = function () {
      var q = Math.max(1, Math.min(maxQty(), parseInt(qty.value, 10) || 1));
      qty.value = q;
      var total = Math.round(current.price * q * 100) / 100;
      buyForm.querySelector('[data-buy-total]').textContent = peso(total);
      buyForm.querySelector('[data-buy-submit]').textContent = 'Pay ' + peso(total) + ' with Maya';
    };
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-card-buy]');
      if (!b) return;
      current = products.filter(function (p) {
        return p.id === b.getAttribute('data-card-buy');
      })[0];
      if (!current) return;
      buyForm.elements.productId.value = current.id;
      buyForm.querySelector('[data-buy-image]').src = current.image;
      buyForm.querySelector('[data-buy-name]').textContent = current.name;
      buyForm.querySelector('[data-buy-price]').textContent = peso(current.price) + ' each';
      qty.value = 1;
      qty.max = maxQty();
      buyErr.hidden = true;
      renderTotal();
      EVO.openModal('card-buy');
    });
    buyForm.addEventListener('click', function (ev) {
      var step = ev.target.closest('[data-buy-step]');
      if (!step) return;
      qty.value = (parseInt(qty.value, 10) || 1) + parseInt(step.getAttribute('data-buy-step'), 10);
      renderTotal();
    });
    qty.addEventListener('change', renderTotal);
    buyForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      buyErr.hidden = true;
      if (!buyForm.reportValidity()) return;
      var f = buyForm.elements;
      var btn = buyForm.querySelector('button[type="submit"]');
      var restore = EVO.busy(btn, 'Opening Maya…');
      EVO.api('card.buy', {
        productId: f.productId.value,
        quantity: f.quantity.value,
        name: f.name.value,
        email: f.email.value,
        phone: f.phone.value,
        delivery: f.delivery.value,
        company_website: f.company_website.value,
      })
        .then(function (res) {
          window.location.assign(res.redirectUrl);
        })
        .catch(function (e) {
          restore();
          buyErr.textContent = e.message;
          buyErr.hidden = false;
        });
    });
    // Coming back with the browser's Back button from Maya: the button works again.
    window.addEventListener('pageshow', function (ev) {
      if (ev.persisted) {
        var btn = buyForm.querySelector('button[type="submit"]');
        btn.disabled = false;
        if (current) renderTotal();
      }
    });
  }

  // Back from Maya before it confirmed the payment: ask again every few seconds, then show the outcome.
  if (page.orderWait) {
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      EVO.api('payments.mayaStatus', { rrn: page.orderWait })
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
