/*
 * Admin › Security & Audit Log (reference component "In").
 *
 * Shows the signed-in member's browser-held E2EE identity key (fingerprint + public
 * JWK), copies the JWK and exports the key backup JSON exactly like the reference.
 */
(function () {
  'use strict';

  var panel = document.getElementById('security-panel');
  if (!panel || !window.EVO || !EVO.user || !window.EvoCrypto || !EvoCrypto.supported) return;

  var fpEl = document.getElementById('sec-fingerprint');
  var jwkEl = document.getElementById('sec-jwk');
  var copyBtn = document.getElementById('sec-copy-jwk');
  var exportBtn = document.getElementById('sec-export');
  var keyPair = null;
  var copyTimer = null;
  var storageKey = 'neo_e2ee_key_' + EVO.user.id;

  function hasStoredKey() {
    try {
      return !!localStorage.getItem(storageKey);
    } catch (e) {
      return true; // storage blocked: nothing to wait for
    }
  }

  /*
   * app.js creates the identity key on first visit (EVO.ensureKey). Wait briefly for
   * it so both callers do not generate two different keys at the same time.
   */
  function whenKeyReady() {
    return new Promise(function (resolve) {
      var tries = 0;
      (function check() {
        if (hasStoredKey() || tries++ >= 30) return resolve();
        setTimeout(check, 100);
      })();
    });
  }

  function load() {
    whenKeyReady()
      .then(function () {
        return EvoCrypto.getKeyPair(EVO.user.id);
      })
      .then(function (kp) {
        keyPair = kp;
        fpEl.textContent = kp.fingerprint || 'GENERATING_KEY...';
        jwkEl.textContent = JSON.stringify(kp.publicKeyJwk, null, 2);
      })
      .catch(function (e) {
        EVO.toast('Could not load your encryption key: ' + e.message, 'error');
      });
  }

  copyBtn.addEventListener('click', function () {
    if (!keyPair) return;
    EVO.copy(JSON.stringify(keyPair.publicKeyJwk, null, 2)).then(function () {
      copyBtn.innerHTML = EVO.icon('check', 'w-3 h-3') + '<span>Copied</span>';
      clearTimeout(copyTimer);
      copyTimer = setTimeout(function () {
        copyBtn.innerHTML = EVO.icon('copy', 'w-3 h-3') + '<span>Copy JWK</span>';
      }, 2000);
    });
  });

  exportBtn.addEventListener('click', function () {
    if (!keyPair) return;
    var backup = {
      user: EVO.user.email,
      generatedAt: new Date().toISOString(),
      algorithm: 'ECDH-P256-AES-GCM-256',
      keyPair: keyPair,
    };
    EVO.download('88MMM_E2EE_Keys_' + EVO.user.id + '.json', JSON.stringify(backup, null, 2), 'application/json');
  });

  load();
})();
