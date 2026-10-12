<?php
/**
 * Member Backoffice > Contacts: each member's contact manager, auto responder
 * and outbox (tables crm_contacts, crm_autoresponders, crm_messages). Email
 * goes out through includes/mailer.php; AI drafts come from includes/ai.php.
 *
 * Auto responder: when a contact with an email address is added (or gets
 * one later), every active auto responder message for the contact's source
 * is scheduled delay_days later. Due messages are sent when the member opens
 * the backoffice (crm.sendDue) and by bin/send-autoresponders.php (run it
 * every few minutes in production). A contact's sequence stops when they
 * unsubscribe, are marked Joined or Not interested, or auto follow-up is
 * turned off for them.
 */
declare(strict_types=1);

const CRM_STAGES = ['new' => 'New', 'contacted' => 'Contacted', 'interested' => 'Interested', 'joined' => 'Joined', 'not_interested' => 'Not interested'];
const CRM_SOURCES = ['manual' => 'Added by me', 'referral' => 'Referral', 'event' => 'Event', 'social' => 'Social media', 'card' => '1EVOAI card', 'website' => 'My website', 'other' => 'Other'];
/** Stages that end a contact's automatic follow-up. */
const CRM_STOP_STAGES = ['joined', 'not_interested'];
const CRM_PER_PAGE = 25;
const CRM_MAX_CONTACTS = 5000;
const CRM_MAX_RESPONDERS = 20;
const CRM_PLACEHOLDERS = [
    '{first_name}' => "Contact's first name",
    '{full_name}' => "Contact's full name",
    '{my_name}' => 'Your name',
    '{my_first_name}' => 'Your first name',
    '{my_email}' => 'Your email',
    '{my_phone}' => 'Your phone',
    '{my_referral_id}' => 'Your member ID',
    '{signup_link}' => 'Your sign-up link',
];
const CRM_AI_RESPONDER_GOALS = [
    'welcome' => 'Welcome them and thank them for connecting',
    'card' => 'Share my 1EVOAI digital business card and invite them to save my contact',
    'presentation' => 'Invite them to a short business presentation, online or in person',
    'checkin' => 'A friendly check-in to keep in touch',
    'join' => 'Invite them to join 88MMM Circle with my sign-up link',
];
const CRM_AI_FOLLOWUP_GOALS = [
    'followup' => 'Follow up on our last conversation',
    'presentation' => 'Invite them to a short business presentation',
    'join' => 'Invite them to join 88MMM Circle with my sign-up link',
    'thanks' => 'Thank them for their time',
    'reconnect' => 'Reconnect after a while without contact',
];
const CRM_AI_TONES = ['friendly' => 'Friendly and warm', 'professional' => 'Professional', 'enthusiastic' => 'Enthusiastic'];
const CRM_AI_LANGUAGES = ['en' => 'English', 'fil' => 'Filipino (Tagalog)', 'taglish' => 'Taglish (a casual mix of Tagalog and English)'];

/* ---------------------------------------------------------------------
 * The member (sender) and placeholders
 * ------------------------------------------------------------------- */

/** An absolute link into the portal (emails need absolute links). */
function crm_link(array $params): string
{
    return base_url() . '/index.php?' . http_build_query($params);
}

/** The member's name, email, phone, member ID and sign-up link, for emails and placeholders. */
function crm_member(string $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $u = db_one('SELECT name, email FROM users WHERE id = ?', [$userId]) ?? ['name' => '', 'email' => ''];
    $ref = (string) (db_value('SELECT id FROM mlm_accounts WHERE user_id = ? ORDER BY head_number, id LIMIT 1', [$userId]) ?: 'MMM-88001');
    $details = get_account_details($userId);
    $profile = get_member_profile($userId);
    return $cache[$userId] = [
        'name' => (string) $u['name'],
        'first' => explode(' ', trim((string) $u['name']))[0],
        'email' => (string) $u['email'],
        'phone' => (string) (($details['phone'] ?? '') ?: ($profile['phone'] ?? '')),
        'referralId' => $ref,
        'signupLink' => crm_link(['view' => 'main', 'ref' => $ref]) . '#register',
    ];
}

/** Fill the placeholders in an auto responder subject or body for one contact. */
function crm_render(string $text, array $contact, array $member): string
{
    return strtr($text, [
        '{first_name}' => explode(' ', trim((string) $contact['name']))[0],
        '{full_name}' => (string) $contact['name'],
        '{my_name}' => $member['name'],
        '{my_first_name}' => $member['first'],
        '{my_email}' => $member['email'],
        '{my_phone}' => $member['phone'],
        '{my_referral_id}' => $member['referralId'],
        '{signup_link}' => $member['signupLink'],
    ]);
}

/** Placeholders in $text that don't exist (typos), e.g. ['{firstname}']. */
function crm_unknown_placeholders(string $text): array
{
    preg_match_all('/\{[a-z_]+\}/i', $text, $m);
    return array_values(array_unique(array_diff($m[0], array_keys(CRM_PLACEHOLDERS))));
}

/** The opt-out line under every email to a contact. */
function crm_footer(array $member, string $unsubscribeUrl): string
{
    return "\n\n--\nYou're receiving this email because {$member['name']} added you to their contacts on 88MMM Circle. To stop these emails, open: {$unsubscribeUrl}";
}

/* ---------------------------------------------------------------------
 * Contacts
 * ------------------------------------------------------------------- */

function crm_contact_to_array(array $r): array
{
    return [
        'id' => (int) $r['id'], 'name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone'], 'company' => $r['company'],
        'stage' => $r['stage'], 'source' => $r['source'], 'tags' => $r['tags'], 'notes' => (string) $r['notes'],
        'autoFollow' => (bool) $r['auto_follow'], 'unsubscribed' => $r['unsubscribed_at'] !== null,
        'lastContacted' => $r['last_contacted_at'] ? fmt_date($r['last_contacted_at']) : null,
        'added' => fmt_date($r['created_at']),
    ];
}

function crm_contact(string $userId, int $id): ?array
{
    return db_one('SELECT * FROM crm_contacts WHERE id = ? AND user_id = ?', [$id, $userId]);
}

/**
 * One page of a member's contacts, newest first, with each contact's next
 * scheduled message. Filters: q (name, email, phone, company, tags), stage.
 * Returns [rows, total].
 */
function crm_list_contacts(string $userId, string $q, string $stage, int $page): array
{
    $where = ['c.user_id = ?'];
    $params = [$userId];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company LIKE ? OR c.tags LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if (isset(CRM_STAGES[$stage])) {
        $where[] = 'c.stage = ?';
        $params[] = $stage;
    }
    $sql = ' FROM crm_contacts c WHERE ' . implode(' AND ', $where);
    $total = (int) db_value('SELECT COUNT(*)' . $sql, $params);
    $offset = (max(1, $page) - 1) * CRM_PER_PAGE;
    $rows = db_all('SELECT c.*, (SELECT MIN(m.send_at) FROM crm_messages m WHERE m.contact_id = c.id AND m.status = "scheduled") AS next_send_at'
        . $sql . ' ORDER BY c.created_at DESC, c.id DESC LIMIT ' . CRM_PER_PAGE . ' OFFSET ' . $offset, $params);
    return [$rows, $total];
}

/** Number of contacts in each stage. */
function crm_stage_counts(string $userId): array
{
    $counts = array_fill_keys(array_keys(CRM_STAGES), 0);
    foreach (db_all('SELECT stage, COUNT(*) AS n FROM crm_contacts WHERE user_id = ? GROUP BY stage', [$userId]) as $r) {
        $counts[$r['stage']] = (int) $r['n'];
    }
    return $counts;
}

/** The contact form's fields, validated. */
function crm_contact_input(array $in): array
{
    $name = str_in($in, 'name', 150);
    if ($name === '') {
        throw new InvalidArgumentException("Enter the contact's name.");
    }
    $email = strtolower(str_in($in, 'email', 190));
    if ($email !== '' && !valid_email($email)) {
        throw new InvalidArgumentException('Enter a valid email address, or leave it empty.');
    }
    $tags = array_filter(array_map(fn ($t) => mb_substr(trim($t), 0, 40), explode(',', str_in($in, 'tags', 255))), fn ($t) => $t !== '');
    $stage = str_in($in, 'stage', 20);
    $source = str_in($in, 'source', 20);
    return [
        'name' => $name,
        'email' => $email,
        'phone' => str_in($in, 'phone', 60),
        'company' => str_in($in, 'company', 190),
        'stage' => isset(CRM_STAGES[$stage]) ? $stage : 'new',
        'source' => isset(CRM_SOURCES[$source]) ? $source : 'manual',
        'tags' => mb_substr(implode(', ', array_slice(array_unique($tags), 0, 10)), 0, 255),
        'notes' => str_in($in, 'notes', 5000),
        'auto_follow' => !empty($in['autoFollow']) ? 1 : 0,
    ];
}

/**
 * Why a contact can't get emails right now, or null. $auto: for automatic
 * messages (also stopped by the stage and the auto follow-up switch).
 */
function crm_stop_reason(array $contact, bool $auto = true): ?string
{
    if ($contact['unsubscribed_at'] !== null) {
        return 'They unsubscribed';
    }
    if ($contact['email'] === '') {
        return 'No email address';
    }
    if ($auto && !$contact['auto_follow']) {
        return 'Auto follow-up is off for this contact';
    }
    if ($auto && in_array($contact['stage'], CRM_STOP_STAGES, true)) {
        return 'Marked ' . CRM_STAGES[$contact['stage']];
    }
    return null;
}

/** Schedule the member's active auto responder messages for a new contact. Returns how many. */
function crm_start_sequence(array $contact): int
{
    if (crm_stop_reason($contact) !== null || db_value('SELECT 1 FROM crm_messages WHERE contact_id = ? AND is_auto = 1 LIMIT 1', [$contact['id']])) {
        return 0;
    }
    $n = 0;
    foreach (db_all('SELECT id, delay_days FROM crm_autoresponders WHERE user_id = ? AND active = 1 AND source IN ("all", ?) ORDER BY delay_days, id', [$contact['user_id'], $contact['source']]) as $r) {
        db_run('INSERT INTO crm_messages (user_id, contact_id, responder_id, is_auto, to_email, status, send_at) VALUES (?, ?, ?, 1, ?, "scheduled", NOW() + INTERVAL ? DAY)',
            [$contact['user_id'], $contact['id'], $r['id'], $contact['email'], (int) $r['delay_days']]);
        $n++;
    }
    return $n;
}

/** Cancel a contact's scheduled automatic messages. Returns how many. */
function crm_stop_sequence(int $contactId, string $why): int
{
    return db_run('UPDATE crm_messages SET status = "cancelled", note = ? WHERE contact_id = ? AND is_auto = 1 AND status = "scheduled"', [$why, $contactId])->rowCount();
}

/** Add or update a contact (id > 0 updates). Returns ['contact', 'message']. */
function crm_save_contact(array $user, array $in): array
{
    $f = crm_contact_input($in);
    $id = int_in($in, 'id', 0);
    return db_tx(function () use ($user, $f, $id) {
        $n = fn (int $k, string $what): string => $k . ' ' . $what . ($k === 1 ? '' : 's');
        if ($id > 0) {
            $old = crm_contact($user['id'], $id);
            if (!$old) {
                throw new InvalidArgumentException('That contact no longer exists.');
            }
            db_run('UPDATE crm_contacts SET name = ?, email = ?, phone = ?, company = ?, stage = ?, source = ?, tags = ?, notes = ?, auto_follow = ? WHERE id = ? AND user_id = ?',
                [...array_values($f), $id, $user['id']]);
            $row = crm_contact($user['id'], $id);
            $msg = 'Contact saved.';
            $why = crm_stop_reason($row);
            if ($why !== null && ($stopped = crm_stop_sequence($id, $why))) {
                $msg .= ' Cancelled ' . $n($stopped, 'scheduled auto message') . '.';
            }
            // A contact who first gets an email address now starts the auto responder.
            if ($old['email'] === '' && ($started = crm_start_sequence($row))) {
                $msg .= ' Scheduled ' . $n($started, 'auto responder message') . '.';
            }
        } else {
            if ((int) db_value('SELECT COUNT(*) FROM crm_contacts WHERE user_id = ?', [$user['id']]) >= CRM_MAX_CONTACTS) {
                throw new InvalidArgumentException('You have ' . number_format(CRM_MAX_CONTACTS) . ' contacts, the most a member can keep. Delete some first.');
            }
            db_run('INSERT INTO crm_contacts (user_id, name, email, phone, company, stage, source, tags, notes, auto_follow, unsub_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$user['id'], ...array_values($f), bin2hex(random_bytes(16))]);
            $row = crm_contact($user['id'], (int) db()->lastInsertId());
            $started = crm_start_sequence($row);
            $msg = 'Contact added.' . ($started ? ' Scheduled ' . $n($started, 'auto responder message') . '.' : '');
        }
        return ['contact' => crm_contact_to_array($row), 'message' => $msg];
    });
}

function crm_delete_contact(string $userId, int $id): bool
{
    return db_run('DELETE FROM crm_contacts WHERE id = ? AND user_id = ?', [$id, $userId])->rowCount() > 0;
}

/* ---------------------------------------------------------------------
 * Auto responder messages
 * ------------------------------------------------------------------- */

function crm_responder_to_array(array $r): array
{
    return [
        'id' => (int) $r['id'], 'name' => $r['name'], 'subject' => $r['subject'], 'body' => $r['body'],
        'writtenBy' => $r['written_by'], 'aiGoal' => $r['ai_goal'], 'aiTone' => $r['ai_tone'],
        'aiLanguage' => $r['ai_language'], 'aiInstructions' => $r['ai_instructions'],
        'delayDays' => (int) $r['delay_days'], 'source' => $r['source'], 'active' => (bool) $r['active'],
        'scheduled' => (int) ($r['scheduled'] ?? 0), 'sent' => (int) ($r['sent'] ?? 0),
    ];
}

/** An AI-written message's settings in the shape includes/ai.php takes. */
function crm_responder_ai_settings(array $r): array
{
    return [
        'goal' => $r['ai_goal'], 'tone' => $r['ai_tone'], 'language' => $r['ai_language'],
        'instructions' => $r['ai_instructions'], 'delayDays' => (int) $r['delay_days'],
    ];
}

/** The member's auto responder messages in sending order, with scheduled / sent counts. */
function crm_responders(string $userId): array
{
    return array_map('crm_responder_to_array', db_all('SELECT r.*,
            (SELECT COUNT(*) FROM crm_messages m WHERE m.responder_id = r.id AND m.status = "scheduled") AS scheduled,
            (SELECT COUNT(*) FROM crm_messages m WHERE m.responder_id = r.id AND m.status = "sent") AS sent
        FROM crm_autoresponders r WHERE r.user_id = ? ORDER BY r.delay_days, r.id', [$userId]));
}

/**
 * Add or update an auto responder message (id > 0 updates). writtenBy
 * "member": one subject and body for everyone (placeholders filled in);
 * "ai": the AI writes each contact's email when it is due, from aiGoal,
 * aiTone, aiLanguage and aiInstructions. Returns ['responder', 'message'].
 */
function crm_save_responder(array $user, array $in): array
{
    $id = int_in($in, 'id', 0);
    $old = $id > 0 ? db_one('SELECT * FROM crm_autoresponders WHERE id = ? AND user_id = ?', [$id, $user['id']]) : null;
    if ($id > 0 && !$old) {
        throw new InvalidArgumentException('That auto responder message no longer exists.');
    }
    $byAi = str_in($in, 'writtenBy', 10) === 'ai';
    // With AI off the editor has no AI fields: an AI-written message keeps its settings.
    $aiIn = array_key_exists('aiGoal', $in) || !$old ? $in
        : ['aiGoal' => $old['ai_goal'], 'aiTone' => $old['ai_tone'], 'aiLanguage' => $old['ai_language'], 'aiInstructions' => $old['ai_instructions']];
    $pick = fn (array $options, string $key): string => isset($options[$v = str_in($aiIn, $key, 30)]) ? $v : (string) array_key_first($options);
    $goal = $pick(CRM_AI_RESPONDER_GOALS, 'aiGoal');
    $subject = str_in($in, 'subject', 200);
    $body = str_in($in, 'body', 10000);
    if ($byAi) {
        // A message already written by AI can still be edited (it waits) while AI is off.
        if (!ai_enabled() && ($old['written_by'] ?? 'member') !== 'ai') {
            throw new InvalidArgumentException('AI assistance is off, so AI can\'t write this message. Choose "I write it", or ask the administrator to turn AI on.');
        }
        $subject = '';
        $body = '';
    } else {
        if ($subject === '' || $body === '') {
            throw new InvalidArgumentException('Write a subject and a message.');
        }
        if ($bad = crm_unknown_placeholders($subject . ' ' . $body)) {
            throw new InvalidArgumentException('Unknown placeholder ' . implode(', ', $bad) . '. You can use ' . implode(', ', array_keys(CRM_PLACEHOLDERS)) . '.');
        }
    }
    $source = str_in($in, 'source', 20);
    $f = [
        'name' => str_in($in, 'name', 120) ?: mb_substr($byAi ? CRM_AI_RESPONDER_GOALS[$goal] : $subject, 0, 120),
        'subject' => $subject,
        'body' => $body,
        'written_by' => $byAi ? 'ai' : 'member',
        'ai_goal' => $goal,
        'ai_tone' => $pick(CRM_AI_TONES, 'aiTone'),
        'ai_language' => $pick(CRM_AI_LANGUAGES, 'aiLanguage'),
        'ai_instructions' => str_in($aiIn, 'aiInstructions', 600),
        'delay_days' => max(0, min(365, int_in($in, 'delayDays', 0))),
        'source' => $source === 'all' || isset(CRM_SOURCES[$source]) ? $source : 'all',
        'active' => !empty($in['active']) ? 1 : 0,
    ];
    $columns = implode(', ', array_keys($f));
    if ($old) {
        db_run('UPDATE crm_autoresponders SET ' . implode(' = ?, ', array_keys($f)) . ' = ? WHERE id = ? AND user_id = ?', [...array_values($f), $id, $user['id']]);
        $msg = 'Auto responder message saved.';
        if (!$f['active'] && ($n = crm_cancel_responder_messages($id, 'Auto responder message turned off'))) {
            $msg .= ' Cancelled ' . $n . ' scheduled email' . ($n === 1 ? '' : 's') . '.';
        }
    } else {
        if ((int) db_value('SELECT COUNT(*) FROM crm_autoresponders WHERE user_id = ?', [$user['id']]) >= CRM_MAX_RESPONDERS) {
            throw new InvalidArgumentException('You have ' . CRM_MAX_RESPONDERS . ' auto responder messages, the most a member can have.');
        }
        db_run("INSERT INTO crm_autoresponders (user_id, {$columns}) VALUES (?" . str_repeat(', ?', count($f)) . ')', [$user['id'], ...array_values($f)]);
        $id = (int) db()->lastInsertId();
        $msg = $byAi
            ? 'Auto responder message added. AI writes a personal email for each contact you add from now on.'
            : 'Auto responder message added. It goes to contacts you add from now on.';
    }
    $row = db_one('SELECT * FROM crm_autoresponders WHERE id = ?', [$id]);
    return ['responder' => crm_responder_to_array($row), 'message' => $msg];
}

function crm_cancel_responder_messages(int $responderId, string $why): int
{
    return db_run('UPDATE crm_messages SET status = "cancelled", note = ? WHERE responder_id = ? AND status = "scheduled"', [$why, $responderId])->rowCount();
}

/** Turn an auto responder message on or off (off cancels its scheduled emails). */
function crm_set_responder_active(string $userId, int $id, bool $active): string
{
    if (!db_run('UPDATE crm_autoresponders SET active = ? WHERE id = ? AND user_id = ?', [$active ? 1 : 0, $id, $userId])->rowCount()
        && !db_value('SELECT 1 FROM crm_autoresponders WHERE id = ? AND user_id = ?', [$id, $userId])) {
        throw new InvalidArgumentException('That auto responder message no longer exists.');
    }
    if ($active) {
        return 'Turned on. It goes to contacts you add from now on.';
    }
    $n = crm_cancel_responder_messages($id, 'Auto responder message turned off');
    return 'Turned off.' . ($n ? ' Cancelled ' . $n . ' scheduled email' . ($n === 1 ? '' : 's') . '.' : '');
}

function crm_delete_responder(string $userId, int $id): bool
{
    if (!db_value('SELECT 1 FROM crm_autoresponders WHERE id = ? AND user_id = ?', [$id, $userId])) {
        return false;
    }
    crm_cancel_responder_messages($id, 'Auto responder message deleted');
    // Emails it never sent keep its subject in the outbox.
    db_run('UPDATE crm_messages m JOIN crm_autoresponders r ON r.id = m.responder_id SET m.subject = r.subject WHERE r.id = ? AND m.subject = ""', [$id]);
    db_run('DELETE FROM crm_autoresponders WHERE id = ? AND user_id = ?', [$id, $userId]);
    return true;
}

/* ---------------------------------------------------------------------
 * Outbox and sending
 * ------------------------------------------------------------------- */

function crm_message_to_array(array $r): array
{
    $auto = (bool) $r['is_auto'];
    return [
        'id' => (int) $r['id'], 'contactId' => (int) $r['contact_id'],
        'contactName' => $r['contact_name'] ?? '', 'toEmail' => $r['to_email'] ?: ($r['contact_email'] ?? ''),
        'type' => $auto ? 'auto' : 'personal', 'responderName' => $auto ? ($r['responder_name'] ?? 'deleted message') : null,
        // A scheduled automatic email is written when it is sent: show the message's current subject.
        'subject' => $r['subject'] !== '' ? $r['subject']
            : (($r['responder_written_by'] ?? '') === 'ai' ? 'AI writes it when it goes out' : (string) ($r['responder_subject'] ?? '')),
        'status' => $r['status'], 'note' => $r['note'],
        'when' => fmt_datetime($r['status'] === 'sent' && $r['sent_at'] ? $r['sent_at'] : $r['send_at']),
    ];
}

const CRM_MESSAGE_SELECT = 'SELECT m.*, c.name AS contact_name, c.email AS contact_email, r.name AS responder_name, r.subject AS responder_subject,
        r.written_by AS responder_written_by
    FROM crm_messages m JOIN crm_contacts c ON c.id = m.contact_id LEFT JOIN crm_autoresponders r ON r.id = m.responder_id';

/** One page of the member's outbox, latest first (scheduled ones by when they go out). Returns [rows, total]. */
function crm_list_messages(string $userId, string $status, int $page): array
{
    $where = 'm.user_id = ?';
    $params = [$userId];
    if (in_array($status, ['scheduled', 'sent', 'failed', 'cancelled'], true)) {
        $where .= ' AND m.status = ?';
        $params[] = $status;
    }
    $total = (int) db_value("SELECT COUNT(*) FROM crm_messages m WHERE $where", $params);
    $offset = (max(1, $page) - 1) * CRM_PER_PAGE;
    $rows = db_all(CRM_MESSAGE_SELECT . " WHERE $where ORDER BY COALESCE(m.sent_at, m.send_at) DESC, m.id DESC LIMIT " . CRM_PER_PAGE . " OFFSET $offset", $params);
    return [$rows, $total];
}

/** A contact's emails, latest first. */
function crm_contact_messages(string $userId, int $contactId): array
{
    return db_all(CRM_MESSAGE_SELECT . ' WHERE m.user_id = ? AND m.contact_id = ? ORDER BY COALESCE(m.sent_at, m.send_at) DESC, m.id DESC LIMIT 50', [$userId, $contactId]);
}

function crm_cancel_message(string $userId, int $id): bool
{
    return db_run('UPDATE crm_messages SET status = "cancelled", note = "Cancelled by you" WHERE id = ? AND user_id = ? AND status = "scheduled"', [$id, $userId])->rowCount() > 0;
}

/**
 * Send one scheduled message now, if it is still scheduled and allowed. An
 * AI-written auto responder message is written for this contact first.
 * Returns 'sent', 'failed', 'cancelled', 'waiting' (email off, or AI off /
 * busy / over today's limit: it stays scheduled) or 'skipped'.
 */
function crm_deliver(int $messageId): string
{
    if (mail_transport() === 'off') {
        return 'waiting';
    }
    // Claim it (sent_at marks when), so two senders never send the same email.
    if (!db_run('UPDATE crm_messages SET status = "sending", sent_at = NOW() WHERE id = ? AND status = "scheduled"', [$messageId])->rowCount()) {
        return 'skipped';
    }
    $m = db_one('SELECT * FROM crm_messages WHERE id = ?', [$messageId]);
    $contact = db_one('SELECT * FROM crm_contacts WHERE id = ?', [$m['contact_id']]);
    $auto = (bool) $m['is_auto'];
    $responder = $auto && $m['responder_id'] !== null ? db_one('SELECT * FROM crm_autoresponders WHERE id = ?', [$m['responder_id']]) : null;
    $why = $contact ? crm_stop_reason($contact, $auto) : 'Contact deleted';
    if ($why === null && $auto && (!$responder || !$responder['active'])) {
        $why = $responder ? 'Auto responder message turned off' : 'Auto responder message deleted';
    }
    if ($why !== null) {
        db_run('UPDATE crm_messages SET status = "cancelled", sent_at = NULL, note = ? WHERE id = ?', [$why, $messageId]);
        return 'cancelled';
    }

    $member = crm_member($m['user_id']);
    if ($auto && $responder['written_by'] === 'ai') {
        try {
            $text = ai_write_auto_email(['id' => $m['user_id']], crm_responder_ai_settings($responder), $contact, crm_contact_messages($m['user_id'], (int) $contact['id']));
        } catch (AiError $e) {
            if ($e->kind === 'fail') {
                db_run('UPDATE crm_messages SET status = "failed", sent_at = NULL, to_email = ?, note = ? WHERE id = ?',
                    [$contact['email'], mb_substr("AI couldn't write this email: " . $e->getMessage(), 0, 255), $messageId]);
                return 'failed';
            }
            // AI off, busy or over today's limit: it stays scheduled and goes out later.
            $later = ['retry' => 'NOW() + INTERVAL 15 MINUTE', 'limit' => 'NOW() + INTERVAL 1 HOUR'][$e->kind] ?? 'send_at';
            db_run("UPDATE crm_messages SET status = 'scheduled', sent_at = NULL, send_at = {$later}, note = ? WHERE id = ?", [mb_substr('Waiting: ' . $e->getMessage(), 0, 255), $messageId]);
            return 'waiting';
        }
        $subjectText = $text['subject'];
        $bodyText = $text['body'];
    } else {
        $subjectText = $auto ? $responder['subject'] : $m['subject'];
        $bodyText = $auto ? $responder['body'] : (string) $m['body'];
    }
    $subject = mb_substr(crm_render($subjectText, $contact, $member), 0, 200);
    $body = crm_render($bodyText, $contact, $member);
    $unsubscribe = crm_link(['view' => 'unsubscribe', 't' => $contact['unsub_token']]);
    $result = mail_send([
        'to' => $contact['email'], 'toName' => $contact['name'], 'subject' => $subject,
        'body' => $body . crm_footer($member, $unsubscribe),
        'fromName' => $member['name'], 'replyTo' => $member['email'], 'replyToName' => $member['name'],
        'unsubscribeUrl' => $unsubscribe,
    ]);
    if ($result['ok']) {
        db_run('UPDATE crm_messages SET status = "sent", sent_at = NOW(), to_email = ?, subject = ?, body = ?, note = "" WHERE id = ?', [$contact['email'], $subject, $body, $messageId]);
        db_run('UPDATE crm_contacts SET last_contacted_at = NOW() WHERE id = ?', [$contact['id']]);
        return 'sent';
    }
    db_run('UPDATE crm_messages SET status = "failed", sent_at = NULL, to_email = ?, subject = ?, body = ?, note = ? WHERE id = ?',
        [$contact['email'], $subject, $body, mb_substr((string) ($result['error'] ?? 'Sending failed'), 0, 255), $messageId]);
    return 'failed';
}

/**
 * Send the messages that are due: one member's ($userId) or everyone's
 * (null, bin/send-autoresponders.php). $maxAi caps how many AI-written
 * emails are written in this run (null: no cap); the rest wait for the next
 * run. With email off nothing is sent and 'waiting' counts what is due.
 * Returns counts per outcome.
 */
function crm_send_due(?string $userId, int $limit = 25, ?int $maxAi = null): array
{
    $out = ['sent' => 0, 'failed' => 0, 'cancelled' => 0, 'waiting' => 0];
    // A send cut off midway is reported rather than retried, so nobody gets the same email twice.
    db_run('UPDATE crm_messages SET status = "failed", sent_at = NULL, note = "Interrupted while sending. Send it again if it did not arrive." WHERE status = "sending" AND sent_at < NOW() - INTERVAL 15 MINUTE');
    $where = 'm.status = "scheduled" AND m.send_at <= NOW()' . ($userId !== null ? ' AND m.user_id = ?' : '');
    $params = $userId !== null ? [$userId] : [];
    if (mail_transport() === 'off') {
        $out['waiting'] = (int) db_value("SELECT COUNT(*) FROM crm_messages m WHERE $where", $params);
        return $out;
    }
    $rows = db_all("SELECT m.id, COALESCE(r.written_by, 'member') AS written_by FROM crm_messages m LEFT JOIN crm_autoresponders r ON r.id = m.responder_id
                    WHERE $where ORDER BY m.send_at, m.id LIMIT " . max(1, $limit), $params);
    $aiWritten = 0;
    foreach ($rows as $r) {
        // Writing takes the AI a few seconds per email, so a page visit writes only a few.
        if ($r['written_by'] === 'ai' && ai_enabled()) {
            if ($maxAi !== null && $aiWritten >= $maxAi) {
                $out['waiting']++;
                continue;
            }
            $aiWritten++;
        }
        $status = crm_deliver((int) $r['id']);
        if (isset($out[$status])) {
            $out[$status]++;
        }
    }
    return $out;
}

/**
 * Email one contact now (written by the member, maybe from an AI draft).
 * Returns ['status' => 'sent'|'failed'|'scheduled', 'message'].
 */
function crm_send_message(array $user, array $in): array
{
    $contact = crm_contact($user['id'], int_in($in, 'contactId'));
    if (!$contact) {
        throw new InvalidArgumentException('That contact no longer exists.');
    }
    $subject = str_in($in, 'subject', 200);
    $body = str_in($in, 'body', 10000);
    if ($subject === '' || $body === '') {
        throw new InvalidArgumentException('Write a subject and a message.');
    }
    if ($bad = crm_unknown_placeholders($subject . ' ' . $body)) {
        throw new InvalidArgumentException('Unknown placeholder ' . implode(', ', $bad) . '. You can use ' . implode(', ', array_keys(CRM_PLACEHOLDERS)) . '.');
    }
    if ($why = crm_stop_reason($contact, false)) {
        throw new InvalidArgumentException("You can't email {$contact['name']}: " . lcfirst($why) . '.');
    }
    db_run('INSERT INTO crm_messages (user_id, contact_id, to_email, subject, body, status, send_at) VALUES (?, ?, ?, ?, ?, "scheduled", NOW())',
        [$user['id'], $contact['id'], $contact['email'], $subject, $body]);
    $id = (int) db()->lastInsertId();
    if ($contact['stage'] === 'new') {
        db_run('UPDATE crm_contacts SET stage = "contacted" WHERE id = ?', [$contact['id']]);
    }
    $status = crm_deliver($id);
    if ($status === 'sent') {
        return ['status' => 'sent', 'message' => "Email sent to {$contact['name']}."];
    }
    if ($status === 'failed') {
        $note = (string) db_value('SELECT note FROM crm_messages WHERE id = ?', [$id]);
        return ['status' => 'failed', 'message' => 'The email could not be sent: ' . $note];
    }
    return ['status' => 'scheduled', 'message' => 'Saved in your outbox. It goes out once email sending is set up.'];
}

/* ---------------------------------------------------------------------
 * Unsubscribe (link at the bottom of every email)
 * ------------------------------------------------------------------- */

/** The contact (and member name) for an unsubscribe token, or null. */
function crm_contact_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    return db_one('SELECT c.*, u.name AS member_name FROM crm_contacts c JOIN users u ON u.id = c.user_id WHERE c.unsub_token = ?', [$token]);
}

/** Stop every email from the member to this contact. */
function crm_unsubscribe(array $contact): void
{
    db_run('UPDATE crm_contacts SET unsubscribed_at = COALESCE(unsubscribed_at, NOW()) WHERE id = ?', [$contact['id']]);
    db_run('UPDATE crm_messages SET status = "cancelled", note = "They unsubscribed" WHERE contact_id = ? AND status = "scheduled"', [$contact['id']]);
}
