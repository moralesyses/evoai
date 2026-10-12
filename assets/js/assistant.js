/*
 * 88MMM Assistant (views/partials/assistant.php): the chat button and window
 * that answer questions about the compensation plan and the portal
 * (API assistant.ask, includes/assistant.php).
 * - The conversation stays in this browser tab (sessionStorage) and the last
 *   12 messages are sent with each question; nothing is stored on the server.
 * - Answers are escaped, then **bold** and "- " list lines are formatted.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-assistant]');
  if (!root || !window.EVO) return;

  // AI off (administrators only): the window just says which API key is missing.
  if (root.hasAttribute('data-assistant-off')) {
    var offToggle = root.querySelector('[data-assistant-toggle]');
    var offPanel = root.querySelector('[data-assistant-panel]');
    var offShow = function (show) {
      offPanel.hidden = !show;
      offToggle.setAttribute('aria-expanded', show ? 'true' : 'false');
    };
    offToggle.addEventListener('click', function () {
      offShow(offPanel.hidden);
    });
    root.querySelector('[data-assistant-close]').addEventListener('click', function () {
      offShow(false);
    });
    root.querySelector('[data-assistant-reset]').hidden = true;
    return;
  }

  var STORE = 'evo_assistant_chat';
  var MAX_SENT = 12;
  var toggle = root.querySelector('[data-assistant-toggle]');
  var panel = root.querySelector('[data-assistant-panel]');
  var log = root.querySelector('[data-assistant-log]');
  var suggest = root.querySelector('[data-assistant-suggest]');
  var form = root.querySelector('[data-assistant-form]');
  var input = root.querySelector('[data-assistant-input]');
  var sendBtn = root.querySelector('[data-assistant-send]');
  var suggestions = [];
  try {
    suggestions = JSON.parse(root.getAttribute('data-suggestions') || '[]');
  } catch (e) {}

  var messages = [];
  try {
    messages = JSON.parse(sessionStorage.getItem(STORE) || '[]') || [];
  } catch (e) {}
  var busy = false;
  var failed = null; // the last error, shown under the conversation (not saved)

  function save() {
    try {
      sessionStorage.setItem(STORE, JSON.stringify(messages.slice(-40)));
    } catch (e) {}
  }

  // Escaped text with **bold**, "- " list items and paragraphs.
  function format(text) {
    var html = [];
    var list = [];
    function flush() {
      if (list.length) html.push('<ul>' + list.join('') + '</ul>');
      list = [];
    }
    String(text)
      .split(/\n/)
      .forEach(function (raw) {
        var line = EVO.esc(raw.trim()).replace(/\*\*(.+?)\*\*/g, '<strong class="text-white">$1</strong>');
        var item = /^(?:[-*•]|\d+\.)\s+(.*)$/.exec(line);
        if (item) {
          list.push('<li>' + item[1] + '</li>');
        } else {
          flush();
          if (line) html.push('<p>' + line + '</p>');
        }
      });
    flush();
    return html.join('');
  }

  function bubble(role, inner, extra) {
    var mine = role === 'user';
    return (
      '<div class="flex' + (mine ? ' justify-end' : '') + '">' +
      '<div class="px-3 py-2 rounded-2xl leading-relaxed ' +
      (mine ? 'bg-[#f3bd35] text-black' : 'bg-zinc-900 border border-zinc-800 text-zinc-200') +
      (extra ? ' ' + extra : '') + '" style="max-width:85%">' + inner + '</div></div>'
    );
  }

  function render() {
    var html = bubble('assistant', format("Hi! I'm the 88MMM Assistant. Ask me about the compensation plan, joining, activation codes, cashing out or anything in the portal."));
    messages.forEach(function (m) {
      html += bubble(m.role, m.role === 'user' ? EVO.esc(m.content).replace(/\n/g, '<br>') : format(m.content));
    });
    if (busy) {
      html += bubble('assistant', '<span class="text-zinc-400" data-assistant-status>' + EVO.esc(waitText || 'Thinking…') + '</span>');
    } else if (failed) {
      html += bubble('assistant', EVO.esc(failed), 'border-rose-500/40 text-rose-300');
    }
    log.innerHTML = html;
    log.scrollTop = log.scrollHeight;
    suggest.innerHTML = messages.length || busy
      ? ''
      : suggestions
          .map(function (q) {
            return '<button type="button" data-assistant-ask class="px-2.5 py-1.5 rounded-full border border-[#f3bd35]/40 text-[#ffda72] text-[11px] hover:bg-[#f3bd35]/10 text-left">' + EVO.esc(q) + '</button>';
          })
          .join('');
    suggest.hidden = !suggest.innerHTML;
    sendBtn.disabled = busy;
    input.disabled = busy;
  }

  // The AI service can ask us to wait (free-tier limits): count down, then ask again, at most twice.
  var MAX_AUTO_RETRIES = 2;
  var waitText = '';

  function waitThen(seconds, next) {
    var left = seconds;
    (function tick() {
      waitText = 'Lots of questions right now. Trying again in ' + left + 's…';
      var status = log.querySelector('[data-assistant-status]');
      if (status) status.textContent = waitText;
      if (left-- <= 0) {
        waitText = '';
        next();
        return;
      }
      setTimeout(tick, 1000);
    })();
  }

  function giveUp(question, message) {
    // The question goes back in the box, so sending again asks it again.
    failed = message || 'The assistant could not answer. Please try again.';
    messages.pop();
    input.value = question;
    save();
    busy = false;
    render();
    input.focus();
  }

  function send(question, retries) {
    EVO.api('assistant.ask', { messages: messages.slice(-MAX_SENT) })
      .then(function (res) {
        if (!res.reply && res.retryAfter) {
          if (retries >= MAX_AUTO_RETRIES) {
            giveUp(question, 'The assistant is busy right now. Please try again in a minute.');
            return;
          }
          render();
          waitThen(res.retryAfter, function () {
            send(question, retries + 1);
          });
          return;
        }
        messages.push({ role: 'assistant', content: res.reply });
        save();
        busy = false;
        render();
        input.focus();
      })
      .catch(function (e) {
        giveUp(question, e && e.message);
      });
  }

  function ask(question) {
    question = String(question || '').trim();
    if (!question || busy) return;
    failed = null;
    waitText = '';
    messages.push({ role: 'user', content: question.slice(0, 1000) });
    save();
    busy = true;
    render();
    send(question, 0);
  }

  function open(show) {
    panel.hidden = !show;
    toggle.setAttribute('aria-expanded', show ? 'true' : 'false');
    if (show) {
      render();
      setTimeout(function () {
        input.focus();
      }, 30);
    }
  }

  toggle.addEventListener('click', function () {
    open(panel.hidden);
  });
  root.querySelector('[data-assistant-close]').addEventListener('click', function () {
    open(false);
    toggle.focus();
  });
  root.querySelector('[data-assistant-reset]').addEventListener('click', function () {
    if (busy) return;
    messages = [];
    failed = null;
    save();
    render();
    input.focus();
  });
  suggest.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-assistant-ask]');
    if (b) ask(b.textContent);
  });
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var q = input.value;
    input.value = '';
    input.style.height = '';
    ask(q);
  });
  // Enter sends; Shift+Enter starts a new line. The box grows with the text.
  input.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && !ev.shiftKey) {
      ev.preventDefault();
      form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });
  input.addEventListener('input', function () {
    input.style.height = '';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !panel.hidden && !document.querySelector('[data-modal]:not([hidden])')) {
      open(false);
      toggle.focus();
    }
  });
})();
