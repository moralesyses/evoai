/*
 * Member Backoffice viewed by a superadmin (?member=usr_x, from Management
 * Admin > Users): read-only. Loaded before the other backoffice scripts.
 * Every change is refused before it reaches the server; switching heads opens
 * that head of the member instead of changing the admin's own session.
 */
(function () {
  'use strict';
  var ro = ((window.EVO_PAGE || {}).backoffice || {}).readOnly;
  if (!ro || !window.EVO) return;

  var send = EVO.api;
  // The app's own housekeeping (sign-out, account switcher, chat key) still runs.
  // Reading the genealogy tree deeper, or a contact's details, changes nothing, so they are allowed too.
  var allowed = { 'analytics.track': 1, 'keys.publish': 1, 'auth.logout': 1, 'auth.switch': 1, 'mlm.genealogyChildren': 1, 'crm.contactDetail': 1 };
  EVO.api = function (action, data) {
    if (allowed[action]) return send(action, data);
    return Promise.reject(new Error('Read-only: this is ' + ro.name + '’s backoffice, so nothing can be changed here.'));
  };

  document.addEventListener(
    'click',
    function (ev) {
      var b = ev.target.closest('[data-set-account], [data-ma-switch]');
      if (!b) return;
      ev.preventDefault();
      ev.stopPropagation();
      var params = new URLSearchParams(window.location.search);
      params.set('head', b.getAttribute('data-set-account') || b.getAttribute('data-ma-switch'));
      window.location.href = EVO.url('backoffice', Object.fromEntries(params));
    },
    true
  );
})();
