<?php
/**
 * Member Backoffice > Contacts (includes/crm.php, includes/ai.php): the
 * signed-in member's contacts, auto responder messages and outbox, and AI
 * drafts. Every action works on the member's own data only.
 * Loaded by api.php (assets/js/views/bo-contacts.js).
 */
declare(strict_types=1);

/** The member a read-only lookup is for: the signed-in member, or (superadmin) the member being viewed. */
function crm_api_owner(array $user, array $in): string
{
    $member = str_in($in, 'member', 40);
    return $member !== '' && is_admin($user) ? $member : $user['id'];
}

/** An AI draft, with AI errors shown to the member. */
function crm_api_ai(callable $draft): array
{
    try {
        return $draft();
    } catch (AiError $e) {
        api_fail($e->getMessage());
    }
}

return [
    'crm.contactSave' => static fn (array $in): array => crm_save_contact(api_user(), $in),

    'crm.contactDelete' => static function (array $in): array {
        if (!crm_delete_contact(api_user()['id'], int_in($in, 'id'))) {
            api_fail('That contact no longer exists.');
        }
        return ['message' => 'Contact deleted.'];
    },

    // Details and email history (also for the superadmin's read-only view of a member).
    'crm.contactDetail' => static function (array $in): array {
        $owner = crm_api_owner(api_user(), $in);
        $contact = crm_contact($owner, int_in($in, 'id'));
        if (!$contact) {
            api_fail('That contact no longer exists.');
        }
        return [
            'contact' => crm_contact_to_array($contact),
            'messages' => array_map('crm_message_to_array', crm_contact_messages($owner, (int) $contact['id'])),
        ];
    },

    'crm.responderSave' => static fn (array $in): array => crm_save_responder(api_user(), $in),

    'crm.responderActive' => static fn (array $in): array => [
        'message' => crm_set_responder_active(api_user()['id'], int_in($in, 'id'), !empty($in['active'])),
    ],

    'crm.responderDelete' => static function (array $in): array {
        if (!crm_delete_responder(api_user()['id'], int_in($in, 'id'))) {
            api_fail('That auto responder message no longer exists.');
        }
        return ['message' => 'Auto responder message deleted.'];
    },

    // Email one contact now.
    'crm.messageSend' => static fn (array $in): array => crm_send_message(api_user(), $in),

    'crm.messageCancel' => static function (array $in): array {
        if (!crm_cancel_message(api_user()['id'], int_in($in, 'id'))) {
            api_fail('That email is no longer scheduled.');
        }
        return ['message' => 'Scheduled email cancelled.'];
    },

    // Send the member's due auto responder emails (called when the backoffice opens).
    // AI writes at most one email per visit; bin/send-autoresponders.php writes the rest.
    'crm.sendDue' => static function (array $in): array {
        $user = api_user();
        @set_time_limit(240);
        return crm_send_due($user['id'], 25, 1);
    },

    // AI drafts: an auto responder message, or a personal follow-up to one contact.
    'crm.aiResponder' => static function (array $in): array {
        $user = api_user();
        return crm_api_ai(fn () => ai_draft_responder($user, $in));
    },

    // An example of what AI would write for an "AI writes it" auto responder message (sample contact).
    'crm.aiResponderPreview' => static function (array $in): array {
        $user = api_user();
        return crm_api_ai(fn () => ai_preview_auto_email($user, $in));
    },

    'crm.aiFollowUp' => static function (array $in): array {
        $user = api_user();
        $contact = crm_contact($user['id'], int_in($in, 'contactId'));
        if (!$contact) {
            api_fail('That contact no longer exists.');
        }
        return crm_api_ai(fn () => ai_draft_followup($user, $contact, crm_contact_messages($user['id'], (int) $contact['id']), $in));
    },
];
