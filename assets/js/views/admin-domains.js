/*
 * Management Admin > .com Domains (views/admin/domains.php): refresh the
 * .com market price, test GoDaddy, take a member's website offline or
 * restore it, retry a paid order's registration, re-check an order's
 * payment, retry a domain's DNS.
 */
(function () {
  'use strict';
  var root = document.getElementById('admin-domains');
  if (!root) return;

  function run(btn, action, data, done) {
    var restore = EVO.busy(btn, 'Working…');
    EVO.api(action, data)
      .then(function (res) {
        EVO.reload(done(res));
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  }

  root.addEventListener('click', function (ev) {
    var off = ev.target.closest('[data-adom-site-suspend]');
    if (off) {
      var note = window.prompt('Why is this website being taken offline? The member sees this.');
      if (note === null) return;
      run(off, 'site.suspend', { userId: off.getAttribute('data-adom-site-suspend'), note: note }, function (res) {
        return res.message;
      });
      return;
    }
    var on = ev.target.closest('[data-adom-site-restore]');
    if (on) {
      run(on, 'site.restore', { userId: on.getAttribute('data-adom-site-restore') }, function (res) {
        return res.message;
      });
      return;
    }
    var gd = ev.target.closest('[data-adom-test-godaddy]');
    if (gd) {
      var undo = EVO.busy(gd, 'Testing…');
      EVO.api('domains.testGoDaddy', { domain: 'example.com' })
        .then(function (res) {
          EVO.toast(res.message);
        })
        .catch(function (e) {
          EVO.toast(e.message, 'error');
        })
        .then(undo);
      return;
    }
    var price = ev.target.closest('[data-adom-refresh-price]');
    if (price) {
      run(price, 'domains.refreshPrice', {}, function (res) {
        return res.message;
      });
      return;
    }
    var retry = ev.target.closest('[data-adom-retry]');
    if (retry) {
      run(retry, 'domains.retry', { order: retry.getAttribute('data-adom-retry') }, function (res) {
        return res.status === 'completed' ? res.domain + ' is registered.' : 'Still not registered: see the error on the order.';
      });
      return;
    }
    var recheck = ev.target.closest('[data-adom-recheck]');
    if (recheck) {
      run(recheck, 'domains.recheck', { order: recheck.getAttribute('data-adom-recheck') }, function (res) {
        return 'Payment status: ' + (res.paymentStatus || 'no answer yet') + ' · order ' + res.statusText.toLowerCase() + '.';
      });
      return;
    }
    var dns = ev.target.closest('[data-adom-dns]');
    if (dns) {
      run(dns, 'domains.retryDns', { id: Number(dns.getAttribute('data-adom-dns')) }, function (res) {
        return res.dnsOk ? 'DNS records set.' : 'DNS failed: ' + res.dnsNote;
      });
    }
  });
})();
