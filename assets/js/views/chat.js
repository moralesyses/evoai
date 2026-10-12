/*
 * E2EE executive chat. Messages are encrypted in the browser with AES-GCM-256:
 *  - group room "circle-vip": key derived with PBKDF2 from the room id
 *  - direct rooms: ECDH P-256 shared key (falls back to the room key when the
 *    peer has not published a public key yet), exactly like the reference.
 * The server stores ciphertext + IV only; new messages arrive by polling.
 */
(function () {
  'use strict';
  var page = (window.EVO_PAGE || {}).chat;
  if (!page || !EVO.user || !window.EvoCrypto || !EvoCrypto.supported) {
    var st = document.getElementById('chat-status-text');
    if (st && window.EvoCrypto && !EvoCrypto.supported) st.textContent = 'Secure context required';
    return;
  }
  var me = EVO.user;
  var rooms = page.rooms;
  var users = page.users || {};
  var state = {
    room: page.activeRoom,
    since: 0,
    messages: [], // decrypted, ordered by seq/timestamp
    reactions: {},
    inspect: {},
    typing: [],
    peerKey: null,
    connected: false,
    sound: localStorage.getItem('evo_chat_sound') !== 'off',
    keys: null,
    pollTimer: null,
    generation: 0,
  };

  var listEl = document.getElementById('chat-messages');
  var scrollEl = document.getElementById('chat-scroll');
  var typingEl = document.getElementById('chat-typing');
  var input = document.getElementById('chat-input');
  var sendBtn = document.getElementById('chat-send');
  var burn = document.getElementById('chat-burn');

  /* ---------------- Sounds (same chimes as the reference) ---------------- */
  var audioCtx = null;
  function ctx() {
    if (!audioCtx) {
      var A = window.AudioContext || window.webkitAudioContext;
      if (A) audioCtx = new A();
    }
    if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume().catch(function () {});
    return audioCtx;
  }
  function chime(f1, f2, gain, dur) {
    try {
      var c = ctx();
      if (!c) return;
      var o = c.createOscillator();
      var g = c.createGain();
      o.type = 'sine';
      o.frequency.setValueAtTime(f1, c.currentTime);
      o.frequency.exponentialRampToValueAtTime(f2, c.currentTime + dur - 0.03);
      g.gain.setValueAtTime(gain, c.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, c.currentTime + dur);
      o.connect(g);
      g.connect(c.destination);
      o.start();
      o.stop(c.currentTime + dur);
    } catch (e) {}
  }
  var sentChime = function () { chime(587.33, 880, 0.08, 0.15); };
  var recvChime = function () { chime(783.99, 659.25, 0.09, 0.16); };

  function renderSound() {
    var b = document.getElementById('chat-sound');
    b.title = state.sound ? 'Mute Chimes' : 'Enable Chimes';
    b.querySelector('[data-sound-on]').hidden = !state.sound;
    b.querySelector('[data-sound-off]').hidden = state.sound;
  }

  /* ---------------- Keys ---------------- */
  function parseJwk(s) {
    if (!s) return null;
    try {
      return typeof s === 'string' ? JSON.parse(s) : s;
    } catch (e) {
      return null;
    }
  }

  function keyForMessage(m) {
    if (m.keyScheme === 'ecdh-p256') {
      var other = parseJwk(m.senderId === me.id ? m.peerPub : m.senderPub);
      if (!other || !state.keys) return Promise.reject(new Error('missing key'));
      return EvoCrypto.ecdhKey(state.keys.privateKeyJwk, other);
    }
    return EvoCrypto.roomKey(m.roomId);
  }

  function decryptMessage(m) {
    return keyForMessage(m)
      .then(function (key) {
        return EvoCrypto.decrypt(m.ciphertext, m.iv, key);
      })
      .then(function (text) {
        m.cleartext = text;
        m.decryptionError = text.indexOf('[Decryption error') === 0;
        return m;
      })
      .catch(function () {
        m.cleartext = '[Decryption Error: Key mismatch]';
        m.decryptionError = true;
        return m;
      });
  }

  /* ---------------- Rendering ---------------- */
  function currentRoom() {
    for (var i = 0; i < rooms.length; i++) if (rooms[i].id === state.room) return rooms[i];
    return rooms[0];
  }

  function renderMessage(m) {
    var mine = m.senderId === me.id;
    var open = !!state.inspect[m.id];
    var reacts = state.reactions[m.id] || {};
    var reactHtml = '';
    var keys = Object.keys(reacts).filter(function (k) {
      return reacts[k] && reacts[k].length;
    });
    if (keys.length) {
      reactHtml =
        '<div class="flex flex-wrap gap-1 mt-2 pt-1 border-t border-zinc-800/60">' +
        keys
          .map(function (emoji) {
            var ids = reacts[emoji];
            var on = ids.indexOf(me.id) > -1;
            return (
              '<button type="button" data-react="' + EVO.esc(emoji) + '" data-mid="' + EVO.esc(m.id) + '" class="flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] ' +
              (on ? 'bg-[#f3bd35]/30 border border-[#f3bd35] text-[#ffda72]' : 'bg-zinc-800/80 border border-zinc-700 text-zinc-300') +
              '"><span>' + EVO.esc(emoji) + '</span><span class="font-semibold tabular-nums">' + ids.length + '</span></button>'
            );
          })
          .join('') +
        '</div>';
    }
    return (
      '<div class="flex flex-col ' + (mine ? 'items-end' : 'items-start') + ' max-w-xl ' + (mine ? 'ml-auto' : 'mr-auto') + '" data-msg="' + EVO.esc(m.id) + '">' +
      '<div class="flex items-center gap-2 mb-1 px-1">' +
      '<span class="text-[11px] font-semibold text-zinc-400">' + EVO.esc(m.senderName) + '</span>' +
      '<span class="text-[9px] uppercase px-1.5 py-0.2 rounded-md bg-zinc-800 text-[#f3bd35] font-mono">' + EVO.esc(m.senderRole) + '</span>' +
      '<span class="text-[10px] text-zinc-500 tabular-nums">' + EVO.esc(EVO.fmt.hm(m.timestamp)) + '</span>' +
      (m.ephemeralTimer ? '<span class="text-[10px] text-amber-400 flex items-center gap-0.5">' + EVO.icon('clock', 'w-3 h-3') + m.ephemeralTimer + 's</span>' : '') +
      '</div>' +
      '<div class="relative p-3 rounded-2xl text-xs leading-relaxed shadow-md group ' +
      (mine ? 'bg-gradient-to-br from-[#2a220a] to-[#1a1506] border border-[#f3bd35]/40 text-white rounded-tr-none' : 'bg-[#151515] border border-zinc-800 text-zinc-200 rounded-tl-none') +
      '">' +
      '<p class="break-words">' + EVO.esc(m.cleartext == null ? '…' : m.cleartext) + '</p>' +
      reactHtml +
      '<div class="mt-2 flex items-center justify-between pt-1 border-t border-zinc-800/40 text-[10px]">' +
      '<div class="flex items-center gap-1 text-zinc-500">' + EVO.icon('lock', 'w-3 h-3 text-[#f3bd35]') + '<span>' + EVO.esc(m.algorithm) + '</span>' +
      (mine ? EVO.icon('check-check', 'w-3.5 h-3.5 text-[#f3bd35] ml-1') : '') + '</div>' +
      '<div class="flex items-center gap-2">' +
      '<button type="button" data-react="🤝" data-mid="' + EVO.esc(m.id) + '" class="hover:scale-125 transition-transform" title="React Deal handshake">🤝</button>' +
      '<button type="button" data-react="💼" data-mid="' + EVO.esc(m.id) + '" class="hover:scale-125 transition-transform" title="React Business">💼</button>' +
      '<button type="button" data-react="👏" data-mid="' + EVO.esc(m.id) + '" class="hover:scale-125 transition-transform" title="React Applause">👏</button>' +
      '<button type="button" data-inspect="' + EVO.esc(m.id) + '" class="text-[#f3bd35] hover:underline flex items-center gap-0.5 ml-1" title="Inspect Raw Ciphertext and IV stored on server">' +
      (open ? EVO.icon('eye-off', 'w-3 h-3') : EVO.icon('eye', 'w-3 h-3')) + '<span>' + (open ? 'Hide Cipher' : 'Inspect') + '</span></button>' +
      '</div></div>' +
      (open
        ? '<div class="mt-2 p-2 rounded-xl bg-black/90 border border-[#f3bd35]/30 text-[10px] font-mono text-zinc-300 space-y-1">' +
          '<div class="text-[#ffda72] font-bold">AES-256-GCM Raw Server Payload:</div>' +
          '<div class="break-all"><strong class="text-zinc-500">IV (12B):</strong> ' + EVO.esc(m.iv) + '</div>' +
          '<div class="break-all"><strong class="text-zinc-500">Ciphertext:</strong> ' + EVO.esc(m.ciphertext) + '</div></div>'
        : '') +
      '</div></div>'
    );
  }

  function render(scroll) {
    listEl.innerHTML = state.messages.map(renderMessage).join('');
    typingEl.hidden = state.typing.length === 0;
    typingEl.querySelector('[data-typing-text]').textContent = state.typing.join(', ') + ' typing...';
    if (scroll) document.getElementById('chat-end').scrollIntoView({ behavior: 'smooth' });
  }

  function setConnected(on) {
    if (state.connected === on) return;
    state.connected = on;
    var dot = document.getElementById('chat-status-dot');
    dot.className = 'w-2 h-2 rounded-full ' + (on ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500');
    document.getElementById('chat-status-text').textContent = on ? 'E2EE Live' : 'Connecting';
  }

  /* ---------------- Polling ---------------- */
  function poll() {
    var gen = state.generation;
    var room = state.room;
    return EVO.api('chat.poll', { roomId: room, since: state.since })
      .then(function (res) {
        if (gen !== state.generation) return;
        setConnected(true);
        state.peerKey = parseJwk(res.peerPublicKey);
        state.reactions = res.reactions || {};
        var alive = {};
        (res.alive || []).forEach(function (id) {
          alive[id] = true;
        });
        var typingChanged = JSON.stringify(state.typing) !== JSON.stringify(res.typing || []);
        state.typing = res.typing || [];
        var known = {};
        state.messages.forEach(function (m) {
          known[m.id] = true;
        });
        var fresh = (res.messages || []).filter(function (m) {
          return !known[m.id];
        });
        // Replace optimistic copies of our own messages with the stored version (keeps seq).
        (res.messages || []).forEach(function (m) {
          state.messages.forEach(function (x, i) {
            if (x.id === m.id && !x.seq) state.messages[i].seq = m.seq;
          });
        });
        var before = state.messages.length;
        state.messages = state.messages.filter(function (m) {
          return alive[m.id] || !m.seq;
        });
        state.since = Math.max(state.since, res.latestSeq || 0);
        return Promise.all(fresh.map(decryptMessage)).then(function (decrypted) {
          if (gen !== state.generation) return;
          var firstLoad = before === 0 && state.since > 0 && !state.loaded;
          decrypted.forEach(function (m) {
            state.messages.push(m);
            if (state.loaded && state.sound) (m.senderId === me.id ? sentChime : recvChime)();
          });
          state.messages.sort(function (a, b) {
            return (a.seq || 1e15) - (b.seq || 1e15) || a.timestamp - b.timestamp;
          });
          state.loaded = true;
          render(decrypted.length > 0 || typingChanged || firstLoad);
        });
      })
      .catch(function () {
        if (gen === state.generation) setConnected(false);
      })
      .finally(function () {
        if (gen !== state.generation) return;
        clearTimeout(state.pollTimer);
        state.pollTimer = setTimeout(poll, document.hidden ? 6000 : page.pollMs || 2000);
      });
  }

  function switchRoom(id) {
    if (id === state.room) return;
    state.generation++;
    clearTimeout(state.pollTimer);
    state.room = id;
    state.since = 0;
    state.messages = [];
    state.reactions = {};
    state.typing = [];
    state.inspect = {};
    state.loaded = false;
    state.peerKey = null;
    EVO.qsa('[data-room]').forEach(function (b) {
      var on = b.getAttribute('data-room') === id;
      EVO.setActive(b, on);
      EVO.qsa('[data-on]', b).forEach(function (c) {
        EVO.setActive(c, on);
      });
    });
    document.getElementById('chat-room-name').textContent = currentRoom().name || 'Direct Channel';
    EVO.setParam('room', id === 'circle-vip' ? '' : id);
    render(false);
    poll();
  }

  /* ---------------- Sending ---------------- */
  function send(text, timer) {
    text = String(text || '').trim();
    if (!text) return Promise.resolve();
    var room = currentRoom();
    var id = 'msg_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
    var useEcdh = !!(room.peerId && state.peerKey && state.keys);
    var keyPromise = useEcdh ? EvoCrypto.ecdhKey(state.keys.privateKeyJwk, state.peerKey) : EvoCrypto.roomKey(room.id);
    return keyPromise
      .then(function (key) {
        return EvoCrypto.encrypt(text, key);
      })
      .then(function (enc) {
        var msg = {
          id: id,
          roomId: room.id,
          senderId: me.id,
          senderName: me.name,
          senderRole: me.role,
          ciphertext: enc.ciphertext,
          iv: enc.iv,
          ephemeralTimer: Number(timer) || 0,
          timestamp: Date.now(),
          algorithm: 'AES-GCM-256',
          keyScheme: useEcdh ? 'ecdh-p256' : 'room-pbkdf2',
          senderPub: useEcdh ? JSON.stringify(state.keys.publicKeyJwk) : null,
          peerPub: useEcdh ? JSON.stringify(state.peerKey) : null,
          cleartext: text,
        };
        state.messages.push(msg);
        if (state.sound) sentChime();
        render(true);
        return EVO.api('chat.send', {
          id: msg.id,
          roomId: msg.roomId,
          ciphertext: msg.ciphertext,
          iv: msg.iv,
          ephemeralTimer: msg.ephemeralTimer,
          keyScheme: msg.keyScheme,
          senderPub: msg.senderPub,
          peerPub: msg.peerPub,
        }).then(function (res) {
          msg.id = res.message.id;
          msg.seq = res.message.seq;
          msg.timestamp = res.message.timestamp;
        });
      })
      .catch(function (e) {
        console.error('Failed to send encrypted message:', e);
        EVO.toast(e.message || 'Message could not be sent.', 'error');
      });
  }

  var typingSentAt = 0;
  function sendTyping(on) {
    var now = Date.now();
    if (on && now - typingSentAt < 2500) return;
    typingSentAt = on ? now : 0;
    EVO.api('chat.typing', { roomId: state.room, typing: on }).catch(function () {});
  }

  document.getElementById('chat-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var text = input.value;
    if (!text.trim()) return;
    input.value = '';
    sendBtn.disabled = true;
    sendTyping(false);
    send(text, burn.value);
  });

  input.addEventListener('input', function () {
    sendBtn.disabled = !input.value.trim();
    sendTyping(input.value.length > 0);
  });

  document.addEventListener('click', function (ev) {
    var roomBtn = ev.target.closest('[data-room]');
    if (roomBtn) {
      switchRoom(roomBtn.getAttribute('data-room'));
      return;
    }
    var q = ev.target.closest('[data-quick]');
    if (q) {
      send(q.getAttribute('data-quick'), burn.value);
      return;
    }
    var r = ev.target.closest('[data-react]');
    if (r) {
      var mid = r.getAttribute('data-mid');
      var emoji = r.getAttribute('data-react');
      var map = state.reactions[mid] || (state.reactions[mid] = {});
      var list = map[emoji] || (map[emoji] = []);
      var i = list.indexOf(me.id);
      if (i > -1) list.splice(i, 1);
      else list.push(me.id);
      render(false);
      EVO.api('chat.react', { messageId: mid, emoji: emoji }).catch(function (e) {
        EVO.toast(e.message, 'error');
      });
      return;
    }
    var ins = ev.target.closest('[data-inspect]');
    if (ins) {
      var id = ins.getAttribute('data-inspect');
      var wasOpen = !!state.inspect[id];
      state.inspect = {};
      if (!wasOpen) state.inspect[id] = true;
      render(false);
    }
  });

  document.getElementById('chat-sound').addEventListener('click', function () {
    state.sound = !state.sound;
    try {
      localStorage.setItem('evo_chat_sound', state.sound ? 'on' : 'off');
    } catch (e) {}
    renderSound();
  });

  document.getElementById('chat-safety').addEventListener('click', function () {
    var room = currentRoom();
    var peerId = room.peerId || 'usr_mike';
    var peerName = room.name || 'Executive Contact';
    var holder = document.getElementById('safety-numbers');
    var peerKey = (room.peerId && state.peerKey) || parseJwk((users[peerId] || {}).publicKey);
    var mine = state.keys ? state.keys.publicKeyJwk : null;
    var p = mine ? EvoCrypto.safetyNumbers(mine, peerKey || mine) : Promise.resolve(new Array(12).fill('00000'));
    p.then(function (nums) {
      holder.innerHTML = nums
        .map(function (n) {
          return '<span class="tabular-nums">' + EVO.esc(n) + '</span>';
        })
        .join('');
      document.querySelector('[data-safety-peer]').textContent = peerName;
      EVO.openModal('chat-safety');
    });
  });

  // Self-destructing messages disappear when their burn timer elapses.
  setInterval(function () {
    var now = Date.now();
    var before = state.messages.length;
    state.messages = state.messages.filter(function (m) {
      return !m.ephemeralTimer || now < m.timestamp + m.ephemeralTimer * 1000;
    });
    if (state.messages.length !== before) render(false);
  }, 1000);

  window.addEventListener('beforeunload', function () {
    if (typingSentAt) navigator.sendBeacon && sendTyping(false);
  });

  /* ---------------- Boot ---------------- */
  renderSound();
  EVO.ensureKey().then(function (kp) {
    state.keys = kp;
    if (kp) document.getElementById('chat-fingerprint').textContent = kp.fingerprint;
    poll();
  });
})();
