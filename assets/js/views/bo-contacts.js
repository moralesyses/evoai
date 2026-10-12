/*
 * Backoffice tab 8 — Contacts (views/backoffice/contacts.php).
 * - contacts: add / edit (crm.contactSave) and delete (crm.contactDelete); a
 *   contact's window loads their details and emails (crm.contactDetail),
 *   drafts a follow-up with AI (crm.aiFollowUp), sends it (crm.messageSend)
 *   or copies it, and cancels scheduled emails (crm.messageCancel)
 * - auto responder: add / edit (crm.responderSave), on / off
 *   (crm.responderActive), delete (crm.responderDelete). "AI writes it":
 *   the AI writes each contact's email when it's due, from the saved goal,
 *   tone, language and instructions (example: crm.aiResponderPreview);
 *   "I write it": one message with placeholders, a live preview and an AI
 *   draft (crm.aiResponder)
 * - outbox: cancel a scheduled email
 * - every backoffice visit sends the member's due auto responder emails
 *   (crm.sendDue)
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-bo-contacts]');
  var data = (window.EVO_PAGE || {}).contacts;
  if (!root || !data || !window.EVO) return;
  var esc = EVO.esc;
  // Superadmin's read-only view of a member: details load for that member; changes are blocked by bo-readonly.js.
  var viewing = data.readOnlyMember || null;

  function showError(box, msg) {
    box.textContent = msg || '';
    box.hidden = !msg;
  }
  function spinner(text) {
    return EVO.icon('refresh-cw', 'w-3.5 h-3.5 animate-spin') + '<span>' + text + '</span>';
  }

  /* ---------------- Due auto responder emails ---------------- */
  if (!viewing) {
    EVO.api('crm.sendDue', {})
      .then(function (res) {
        if (res.sent) EVO.toast(res.sent + ' scheduled email' + (res.sent === 1 ? ' was' : 's were') + ' sent.');
        if (res.failed) EVO.toast(res.failed + ' scheduled email' + (res.failed === 1 ? '' : 's') + " couldn't be sent. See the Outbox.", 'error');
      })
      .catch(function () {});
  }

  /* ---------------- Add / edit a contact ---------------- */
  var editModal = document.querySelector('[data-modal="bo-ct-edit"]');
  var form = editModal.querySelector('[data-ct-form]');
  var formErr = form.querySelector('[data-ct-form-error]');

  function openEdit(c) {
    form.reset();
    showError(formErr, '');
    var f = form.elements;
    f.id.value = c ? c.id : '';
    ['name', 'email', 'phone', 'company', 'tags', 'notes'].forEach(function (k) {
      f[k].value = c ? c[k] || '' : '';
    });
    f.stage.value = c ? c.stage : 'new';
    f.source.value = c ? c.source : 'manual';
    f.autoFollow.checked = c ? c.autoFollow : true;
    editModal.querySelector('[data-ct-unsub-note]').hidden = !(c && c.unsubscribed);
    editModal.querySelector('[data-ct-edit-title]').textContent = c ? 'Edit Contact' : 'Add Contact';
    EVO.openModal('bo-ct-edit');
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = EVO.formData(form);
    if (!d.name.trim()) {
      showError(formErr, "Enter the contact's name.");
      return;
    }
    var btn = form.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, spinner('Saving…'));
    EVO.api('crm.contactSave', d)
      .then(function (res) {
        EVO.reload(res.message);
      })
      .catch(function (e) {
        restore();
        showError(formErr, e.message);
      });
  });

  /* ---------------- One contact: details, emails, a new email ---------------- */
  var detail = document.querySelector('[data-modal="bo-ct-detail"]');
  var loading = detail.querySelector('[data-cd-loading]');
  var body = detail.querySelector('[data-cd-body]');
  var subjectEl = detail.querySelector('[data-cd-subject]');
  var messageEl = detail.querySelector('[data-cd-message]');
  var tip = detail.querySelector('[data-cd-tip]');
  var blocked = detail.querySelector('[data-cd-blocked]');
  var sendBtn = detail.querySelector('[data-cd-send]');
  var current = null;
  var changed = false; // reload the list when the window closes
  var statusText = { scheduled: 'Scheduled', sending: 'Sending', sent: 'Sent', failed: 'Failed', cancelled: 'Cancelled' };
  var statusClass = { scheduled: 'text-amber-300', sending: 'text-amber-300', sent: 'text-emerald-400', failed: 'text-rose-300', cancelled: 'text-zinc-500' };

  function cd(key) {
    return detail.querySelector('[data-cd="' + key + '"]');
  }
  function orDash(v) {
    return v ? v : '—';
  }

  function renderHistory(messages) {
    var ul = cd('history');
    if (!messages.length) {
      ul.innerHTML = '<li class="text-zinc-500">No emails yet.</li>';
      return;
    }
    ul.innerHTML = messages
      .map(function (m) {
        return (
          '<li class="p-2.5 rounded-xl bg-zinc-950 border border-zinc-800">' +
          '<div class="flex items-start justify-between gap-2"><span class="font-semibold text-zinc-200">' + esc(m.subject || '(no subject)') + '</span>' +
          '<span class="shrink-0 font-semibold ' + statusClass[m.status] + '">' + statusText[m.status] + '</span></div>' +
          '<div class="text-[10px] text-zinc-500 mt-0.5">' + (m.type === 'auto' ? 'Auto: ' + esc(m.responderName || '') : 'Personal') + ' · ' + esc(m.when) + '</div>' +
          (m.note ? '<div class="text-[10px] text-zinc-500">' + esc(m.note) + '</div>' : '') +
          (m.status === 'scheduled' && !viewing ? '<button type="button" data-cd-cancel="' + m.id + '" class="mt-1 text-[10px] text-zinc-400 hover:text-white underline">Cancel</button>' : '') +
          '</li>'
        );
      })
      .join('');
  }

  function loadDetail(id) {
    var payload = { id: id };
    if (viewing) payload.member = viewing;
    return EVO.api('crm.contactDetail', payload).then(function (res) {
      var c = (current = res.contact);
      cd('name').textContent = c.name;
      var stage = cd('stage');
      stage.className = 'text-[10px] px-2 py-0.5 rounded-full font-bold ' + (data.stagePill[c.stage] || '');
      stage.textContent = data.stages[c.stage] || c.stage;
      cd('meta').textContent = 'Added ' + c.added + ' · ' + (data.sources[c.source] || c.source) + ' · Last contacted: ' + (c.lastContacted || 'never');
      cd('email').textContent = orDash(c.email) + (c.unsubscribed ? ' (unsubscribed)' : '');
      cd('phone').textContent = orDash(c.phone);
      cd('company').textContent = orDash(c.company);
      cd('tags').textContent = orDash(c.tags);
      cd('notes').textContent = orDash(c.notes);
      cd('first').textContent = c.name.split(' ')[0];
      var why = c.unsubscribed ? 'They unsubscribed, so you can’t email them.' : !c.email ? 'Add an email address (Edit) to email this contact.' : '';
      blocked.textContent = why;
      blocked.hidden = !why;
      sendBtn.disabled = !!why;
      renderHistory(res.messages);
      loading.hidden = true;
      body.hidden = false;
    });
  }

  function openDetail(id) {
    loading.textContent = 'Loading…';
    loading.hidden = false;
    body.hidden = true;
    subjectEl.value = '';
    messageEl.value = '';
    tip.hidden = true;
    EVO.openModal('bo-ct-detail');
    loadDetail(id).catch(function (e) {
      loading.textContent = e.message;
    });
  }

  document.addEventListener('evo:modal-close', function (ev) {
    if (ev.detail.id === 'bo-ct-detail' && changed) {
      changed = false;
      EVO.reload();
    }
  });

  sendBtn.addEventListener('click', function () {
    if (!current) return;
    if (!subjectEl.value.trim() || !messageEl.value.trim()) {
      EVO.toast('Write a subject and a message.', 'error');
      return;
    }
    var restore = EVO.busy(sendBtn, spinner('Sending…'));
    EVO.api('crm.messageSend', { contactId: current.id, subject: subjectEl.value, body: messageEl.value })
      .then(function (res) {
        restore();
        EVO.toast(res.message, res.status === 'failed' ? 'error' : undefined);
        if (res.status !== 'failed') {
          subjectEl.value = '';
          messageEl.value = '';
          tip.hidden = true;
        }
        changed = true;
        return loadDetail(current.id);
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  });

  detail.querySelector('[data-cd-copy]').addEventListener('click', function () {
    var text = (subjectEl.value.trim() ? subjectEl.value.trim() + '\n\n' : '') + messageEl.value.trim();
    if (!text) {
      EVO.toast('Write or draft a message first.', 'error');
      return;
    }
    EVO.copy(text).then(function () {
      EVO.toast('Copied. Paste it into Messenger, Viber or SMS.');
    });
  });

  /* ---------------- Auto responder message editor ---------------- */
  var arModal = document.querySelector('[data-modal="bo-ct-responder"]');
  var arForm = arModal.querySelector('[data-ar-form]');
  var arErr = arForm.querySelector('[data-ar-error]');
  var lastField = arForm.elements.body;
  var sample = { name: 'Juan Dela Cruz' };

  function fillPlaceholders(text) {
    var m = data.member;
    var values = {
      '{first_name}': sample.name.split(' ')[0],
      '{full_name}': sample.name,
      '{my_name}': m.name,
      '{my_first_name}': m.first,
      '{my_email}': m.email,
      '{my_phone}': m.phone || '(your phone)',
      '{my_referral_id}': m.referralId,
      '{signup_link}': m.signupLink,
    };
    Object.keys(values).forEach(function (k) {
      text = text.split(k).join(values[k]);
    });
    return text;
  }

  var previewTitle = arModal.querySelector('[data-ar-preview-title]');
  var previewSubject = arModal.querySelector('[data-ar-preview-subject]');
  var previewBody = arModal.querySelector('[data-ar-preview-body]');
  var exampleShown = false; // "AI writes it": an example from the AI is on show

  // "AI writes it": the AI writes each contact's email, so there's no subject or message to fill in.
  function byAi() {
    return arForm.elements.writtenBy.value === 'ai';
  }

  function renderPreview() {
    if (byAi()) {
      if (exampleShown) return;
      previewTitle.textContent = 'Example';
      previewSubject.textContent = '';
      previewBody.textContent = data.aiEnabled
        ? 'Click Show an example to see an email AI would write, for a sample contact met at a business expo.'
        : 'AI assistance is off, so this message waits until it is turned on.';
      return;
    }
    previewTitle.textContent = 'Preview for a contact named Juan Dela Cruz';
    previewSubject.textContent = fillPlaceholders(arForm.elements.subject.value) || '(subject)';
    previewBody.textContent = fillPlaceholders(arForm.elements.body.value) || '(message)';
  }

  function applyWriter() {
    var ai = byAi();
    arModal.querySelector('[data-ar-mine]').hidden = ai;
    arModal.querySelector('[data-ar-by-ai]').hidden = !ai;
    var panel = arModal.querySelector('[data-ai-panel="ar-ai"]');
    var label = panel.querySelector('[data-ai-draft-label]');
    var hint = panel.querySelector('[data-ai-hint]');
    if (label) label.textContent = ai ? 'Show an example' : 'Draft with AI';
    if (hint) hint.textContent = ai ? 'AI writes an example for a sample contact, so you see what your contacts will get.' : 'AI writes a draft into the fields. Check it before you save it.';
    exampleShown = false;
    renderPreview();
  }

  function openResponder(r) {
    arForm.reset();
    showError(arErr, '');
    var f = arForm.elements;
    f.id.value = r ? r.id : '';
    f.name.value = r ? r.name : '';
    // A new message defaults to two days after the latest one (day 0 for the first).
    var days = Object.keys(data.responders || {}).map(function (k) {
      return data.responders[k].delayDays;
    });
    f.delayDays.value = r ? r.delayDays : days.length ? Math.min(365, Math.max.apply(null, days) + 2) : 0;
    f.source.value = r ? r.source : 'all';
    f.subject.value = r ? r.subject : '';
    f.body.value = r ? r.body : '';
    f.active.checked = r ? r.active : true;
    // New messages are AI-written when AI is on.
    f.writtenBy.value = r ? r.writtenBy : data.aiEnabled ? 'ai' : 'member';
    if (f.aiGoal) {
      ['aiGoal', 'aiTone', 'aiLanguage'].forEach(function (k) {
        f[k].value = r && r[k] ? r[k] : f[k].options[0].value;
      });
      f.aiInstructions.value = r ? r.aiInstructions : '';
    }
    lastField = f.body;
    arModal.querySelector('[data-ar-title]').textContent = r ? 'Edit Auto Responder Message' : 'New Auto Responder Message';
    applyWriter();
    EVO.openModal('bo-ct-responder');
  }

  arForm.addEventListener('input', renderPreview);
  arForm.addEventListener('change', function (ev) {
    if (ev.target.name === 'writtenBy') applyWriter();
  });
  arForm.addEventListener('focusin', function (ev) {
    if (ev.target.matches('[data-ar-field]')) lastField = ev.target;
  });

  arForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = EVO.formData(arForm);
    if (!byAi() && (!d.subject.trim() || !d.body.trim())) {
      showError(arErr, 'Write a subject and a message.');
      return;
    }
    if (d.delayDays === '') {
      showError(arErr, 'Choose the day to send it: 0 is right after you add a contact.');
      return;
    }
    var btn = arForm.querySelector('button[type="submit"]');
    var restore = EVO.busy(btn, spinner('Saving…'));
    EVO.api('crm.responderSave', d)
      .then(function (res) {
        EVO.reload(res.message);
      })
      .catch(function (e) {
        restore();
        showError(arErr, e.message);
      });
  });

  function insertPlaceholder(token) {
    var el = lastField;
    var start = typeof el.selectionStart === 'number' ? el.selectionStart : el.value.length;
    var end = typeof el.selectionEnd === 'number' ? el.selectionEnd : start;
    el.value = el.value.slice(0, start) + token + el.value.slice(end);
    el.focus();
    el.setSelectionRange(start + token.length, start + token.length);
    renderPreview();
  }

  /* ---------------- AI drafts ---------------- */
  function aiValues(panel) {
    var out = {};
    EVO.qsa('[data-ai]', panel).forEach(function (el) {
      out[el.getAttribute('data-ai')] = el.value;
    });
    return out;
  }

  function aiDraft(btn) {
    var which = btn.getAttribute('data-ai-draft');
    var payload = aiValues(btn.closest('[data-ai-panel]'));
    var action;
    if (which === 'cd-ai') {
      if (!current) return;
      action = 'crm.aiFollowUp';
      payload.contactId = current.id;
    } else {
      var f = arForm.elements;
      payload.delayDays = f.delayDays.value || 0;
      if (byAi()) {
        action = 'crm.aiResponderPreview';
      } else {
        action = 'crm.aiResponder';
        // What's already written is improved rather than replaced.
        if (f.body.value.trim()) {
          payload.subject = f.subject.value;
          payload.body = f.body.value;
        }
      }
    }
    var restore = EVO.busy(btn, spinner('Writing…'));
    EVO.api(action, payload)
      .then(function (res) {
        restore();
        if (which === 'cd-ai') {
          subjectEl.value = res.subject;
          messageEl.value = res.body;
          tip.textContent = res.nextStep ? 'Suggested next step: ' + res.nextStep : '';
          tip.hidden = !res.nextStep;
        } else if (action === 'crm.aiResponderPreview') {
          exampleShown = true;
          previewTitle.textContent = 'Example for Juan Dela Cruz, met at a business expo';
          previewSubject.textContent = res.subject;
          previewBody.textContent = res.body;
          EVO.toast('Example ready. Each contact gets an email like this, written for them.');
          return;
        } else {
          arForm.elements.subject.value = res.subject;
          arForm.elements.body.value = res.body;
          renderPreview();
        }
        EVO.toast('Draft ready. Read it through and change anything before you ' + (which === 'cd-ai' ? 'send it.' : 'save it.'));
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  }

  /* ---------------- Clicks ---------------- */
  function act(action, payload, btn, confirmText) {
    if (confirmText && !window.confirm(confirmText)) return;
    var restore = btn ? EVO.busy(btn, '…') : function () {};
    EVO.api(action, payload)
      .then(function (res) {
        EVO.reload(res.message);
      })
      .catch(function (e) {
        restore();
        EVO.toast(e.message, 'error');
      });
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t.closest) return;
    var b;
    if (t.closest('[data-ct-add]')) return openEdit(null);
    if ((b = t.closest('[data-ct-edit]'))) return openEdit(data.rows[b.getAttribute('data-ct-edit')]);
    if ((b = t.closest('[data-cd-edit]')) && current) {
      EVO.closeModal('bo-ct-detail');
      return openEdit(current);
    }
    if ((b = t.closest('[data-ct-open]'))) return openDetail(parseInt(b.getAttribute('data-ct-open'), 10));
    if ((b = t.closest('[data-ct-delete]'))) {
      var c = data.rows[b.getAttribute('data-ct-delete')];
      return act('crm.contactDelete', { id: c.id }, b, 'Delete ' + c.name + '? Their scheduled emails are cancelled and their email history is removed.');
    }
    if ((b = t.closest('[data-cd-cancel]'))) {
      return EVO.api('crm.messageCancel', { id: parseInt(b.getAttribute('data-cd-cancel'), 10) })
        .then(function (res) {
          EVO.toast(res.message);
          changed = true;
          return loadDetail(current.id);
        })
        .catch(function (e) {
          EVO.toast(e.message, 'error');
        });
    }
    if ((b = t.closest('[data-ob-cancel]'))) return act('crm.messageCancel', { id: parseInt(b.getAttribute('data-ob-cancel'), 10) }, b);
    if (t.closest('[data-ar-add]')) return openResponder(null);
    if ((b = t.closest('[data-ar-edit]'))) return openResponder(data.responders[b.getAttribute('data-ar-edit')]);
    if ((b = t.closest('[data-ar-active]'))) return act('crm.responderActive', { id: parseInt(b.getAttribute('data-ar-active'), 10), active: b.getAttribute('data-to') === '1' }, b);
    if ((b = t.closest('[data-ar-delete]'))) {
      var r = data.responders[b.getAttribute('data-ar-delete')];
      return act('crm.responderDelete', { id: r.id }, b, 'Delete "' + r.name + '"? Its scheduled emails are cancelled.');
    }
    if ((b = t.closest('[data-ar-insert]'))) return insertPlaceholder(b.getAttribute('data-ar-insert'));
    if ((b = t.closest('[data-ai-draft]'))) return aiDraft(b);
  });
})();
