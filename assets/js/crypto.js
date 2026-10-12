/*
 * Client-side end-to-end encryption helpers (Web Crypto API).
 *  - ECDH P-256 identity keypair per member, kept in this browser's localStorage
 *  - AES-GCM-256 message encryption (12-byte random IV)
 *  - Group rooms: AES key derived with PBKDF2 from the room id
 *  - Direct rooms: AES key derived with ECDH from both members' keys
 * The server only ever receives ciphertext, IVs and public keys.
 */
(function () {
  'use strict';
  var subtle = window.crypto && window.crypto.subtle;
  var enc = new TextEncoder();
  var dec = new TextDecoder();
  var cache = new Map();

  function b64(buf) {
    var bytes = new Uint8Array(buf);
    var s = '';
    for (var i = 0; i < bytes.byteLength; i++) s += String.fromCharCode(bytes[i]);
    return window.btoa(s);
  }
  function unb64(str) {
    var bin = window.atob(str);
    var out = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out.buffer;
  }

  /** SHA-256 fingerprint of a public JWK, formatted XXXX:XXXX:… (8 groups). */
  async function fingerprint(jwk) {
    var digest = await subtle.digest('SHA-256', enc.encode(JSON.stringify(jwk)));
    var hex = Array.from(new Uint8Array(digest))
      .map(function (b) {
        return b.toString(16).padStart(2, '0');
      })
      .join('');
    var groups = hex.match(/.{1,4}/g);
    return groups ? groups.slice(0, 8).join(':').toUpperCase() : hex.slice(0, 32).toUpperCase();
  }

  /** 12 groups of 5 digits derived from both public keys (order independent). */
  async function safetyNumbers(jwkA, jwkB) {
    var joined = [JSON.stringify(jwkA), JSON.stringify(jwkB)].sort().join('::88MMM_E2EE::');
    var bytes = new Uint8Array(await subtle.digest('SHA-256', enc.encode(joined)));
    var out = [];
    for (var i = 0; i < 12; i++) {
      var v = ((bytes[(i * 2) % bytes.length] << 8) | bytes[(i * 2 + 1) % bytes.length]) % 100000;
      out.push(String(v).padStart(5, '0'));
    }
    return out;
  }

  async function generateKeyPair() {
    var kp = await subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveKey', 'deriveBits']);
    var pub = await subtle.exportKey('jwk', kp.publicKey);
    return {
      publicKeyJwk: pub,
      privateKeyJwk: await subtle.exportKey('jwk', kp.privateKey),
      fingerprint: await fingerprint(pub),
    };
  }

  /** Load (or create on first use) this browser's identity key for a member.
      Single-flight per user so concurrent callers never generate two different keys. */
  var keyPending = new Map();
  function getKeyPair(userId) {
    if (!keyPending.has(userId)) {
      keyPending.set(
        userId,
        loadOrCreateKeyPair(userId).catch(function (e) {
          keyPending.delete(userId);
          throw e;
        })
      );
    }
    return keyPending.get(userId);
  }

  async function loadOrCreateKeyPair(userId) {
    var storageKey = 'neo_e2ee_key_' + userId;
    try {
      var stored = localStorage.getItem(storageKey);
      if (stored) {
        var parsed = JSON.parse(stored);
        if (parsed && parsed.publicKeyJwk && parsed.privateKeyJwk) {
          if (!parsed.fingerprint) parsed.fingerprint = await fingerprint(parsed.publicKeyJwk);
          return parsed;
        }
      }
    } catch (e) {
      console.warn('Could not parse stored key, generating fresh one');
    }
    var data = await generateKeyPair();
    try {
      localStorage.setItem(storageKey, JSON.stringify(data));
    } catch (e) {}
    return data;
  }

  async function ecdhKey(privateJwk, publicJwk) {
    var id = 'ecdh:' + JSON.stringify(privateJwk.d || '') + ':' + JSON.stringify(publicJwk.x || '');
    if (cache.has(id)) return cache.get(id);
    var priv = await subtle.importKey('jwk', privateJwk, { name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveKey']);
    var pub = await subtle.importKey('jwk', publicJwk, { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    var key = await subtle.deriveKey({ name: 'ECDH', public: pub }, priv, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
    cache.set(id, key);
    return key;
  }

  async function roomKey(roomId) {
    var id = 'room:' + roomId;
    if (cache.has(id)) return cache.get(id);
    var base = await subtle.importKey('raw', enc.encode('88MMM_Circle_Room_Salt_' + roomId), { name: 'PBKDF2' }, false, ['deriveKey']);
    var key = await subtle.deriveKey(
      { name: 'PBKDF2', salt: enc.encode('MMM_EXECUTIVE_SALT_2026'), iterations: 100000, hash: 'SHA-256' },
      base,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );
    cache.set(id, key);
    return key;
  }

  async function encrypt(text, key) {
    var iv = window.crypto.getRandomValues(new Uint8Array(12));
    var ct = await subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, enc.encode(text));
    return { ciphertext: b64(ct), iv: b64(iv.buffer) };
  }

  async function decrypt(ciphertext, iv, key) {
    try {
      var pt = await subtle.decrypt({ name: 'AES-GCM', iv: new Uint8Array(unb64(iv)) }, key, unb64(ciphertext));
      return dec.decode(pt);
    } catch (e) {
      console.error('Decryption failed:', e);
      return '[Decryption error: Invalid key or message integrity failed]';
    }
  }

  window.EvoCrypto = {
    supported: !!subtle,
    b64: b64,
    unb64: unb64,
    fingerprint: fingerprint,
    safetyNumbers: safetyNumbers,
    getKeyPair: getKeyPair,
    ecdhKey: ecdhKey,
    roomKey: roomKey,
    encrypt: encrypt,
    decrypt: decrypt,
  };
})();
