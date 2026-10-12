/* Member Backoffice shell: active head (account) switching. */
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-set-account]');
    if (!b) return;
    ev.preventDefault();
    EVO.api('mlm.setActive', { accountId: b.getAttribute('data-set-account') })
      .then(function () {
        window.location.reload();
      })
      .catch(function (e) {
        EVO.toast(e.message, 'error');
      });
  });
})();
