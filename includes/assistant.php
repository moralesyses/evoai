<?php
/**
 * 88MMM Assistant: the chat button that answers questions about the binary
 * compensation plan and how the portal works (views/partials/assistant.php,
 * assets/js/assistant.js, API assistant.ask, AI from includes/ai.php).
 *
 * Its facts are built from the live settings (the plan, prices, perks and
 * contact details), so answers follow what the administrator changes. A
 * signed-in member also gets answers about their own account (wallet, legs,
 * pairs today). Conversations aren't stored: the browser keeps them and sends
 * the recent turns with each question.
 *
 * Config "ai": chat_limit (questions per member per day), chat_guest_limit
 * (questions per visitor per hour, by IP address).
 */
declare(strict_types=1);

/** Messages of the conversation sent back with each question, and their length. */
const ASSISTANT_MAX_MESSAGES = 12;
const ASSISTANT_MAX_CHARS = 1000;

function assistant_enabled(): bool
{
    return ai_enabled(ai_provider('chat'));
}

/** Starter questions shown in an empty chat. */
function assistant_suggestions(?array $user): array
{
    return $user
        ? ['How does the pairing bonus work?', 'How much have I earned so far?', 'How do I register a downline?', 'How do I cash out my earnings?']
        : ['How does the compensation plan work?', 'How do I join?', 'What do I get with each package?', 'How do I use an activation code?'];
}

/** The facts the assistant answers from, with the plan's current numbers. */
function assistant_knowledge(): string
{
    $plan = mlm_complan();
    $cms = get_cms();
    $peso = fn ($n): string => '₱' . number_format((float) $n);
    $list = fn ($items): string => is_array($items) && $items ? implode('; ', array_map('strval', $items)) : '-';
    $cycles = $plan['cyclesPerDay'] === 1
        ? '1 cycle a day (12:00 AM to 11:59 PM)'
        : '2 cycles a day (Cycle 1: 12:00 AM to 11:59 AM, Cycle 2: 12:00 PM to 11:59 PM)';
    $exec = (string) ($cms['executiveTierName'] ?? 'Executive Member');
    $vip = (string) ($cms['chairmansTierName'] ?? "Chairman's Circle");
    $execPrice = checkout_item('membership_executive', $cms)['price'];
    $vipPrice = checkout_item('membership_chairmans', $cms)['price'];
    $products = implode('; ', array_map(fn ($p) => $p['name'] . ' ' . $peso($p['memberPrice']) . ' (regular ' . $peso($p['price']) . ')', array_slice(mlm_products(), 0, 12)));
    $tax = (int) round(MLM_TAX_RATE * 100);
    $sample = 1000;
    $sampleNet = $sample - $sample * MLM_TAX_RATE - MLM_PROCESSING_FEE;

    return <<<TXT
ABOUT
- {$cms['brandName']} (88MMM Circle) is a membership network in the Philippines. Members get a 1EVOAI smart NFC business card with a digital card page, and can earn bonuses by inviting new members (a binary compensation plan).
- Office: {$cms['location']}. Contact: {$cms['email']}, phone {$cms['phone']}, WhatsApp {$cms['whatsapp']}.
- The portal's sections: Main Portal (information, sign in, create account), 1EVOAI Card (a member's digital business card), Member Backoffice (for members), Management Admin (administrators only) and E2EE Chat (end-to-end encrypted messages between members).

PACKAGES
- {$exec} ({$cms['executiveSubtitle']}): {$peso($execPrice)}. Includes: {$list($cms['executivePerks'] ?? [])}.
- {$vip} ({$cms['chairmansSubtitle']}): {$peso($vipPrice)}. Includes: {$list($cms['chairmansPerks'] ?? [])}.
- Annual renewal: ₱2,000 (Backoffice → Digital Card CMS → Online Membership Payments & Society Renewal).

JOINING (CREATING AN ACCOUNT)
- Main Portal → Create Account: full name, email, password, Sponsor / Referral ID (the member ID of the person who invited you, like MMM-88102; MMM-88001 if nobody did), the package, then payment.
- Pay online with Maya Checkout, or with an activation code and its PIN from your sponsor. The account is created once the payment is confirmed, and you are signed in.
- A sponsor's sign-up link fills in their ID automatically. Under the Sponsor ID the form shows where you'll be placed.
- You join the sponsor's binary tree on the leg the sponsor chose under Sign-up Placement: Auto (the leg with fewer downlines; left on a tie), Left or Right. Inside that leg you take the first free spot, level by level, left first.

BINARY COMPENSATION PLAN (current settings)
- Entry: {$peso($plan['entryFee'])} Executive membership.
- Direct referral bonus: {$peso($plan['directReferralBonus'])} for each member you personally sponsor (they sign up with your ID, or you place them with Register Downline).
- Points: each new member adds 1 point to the leg (left or right) they are in, for every member above them in the tree.
- Pairing bonus: {$peso($plan['pairingBonus'])} per pair. When a member you sponsor joins and your other leg has a point waiting, one point from each leg makes a pair and you earn the pairing bonus.
- Pair limits: {$plan['maxPairsPerCycle']} pairs per cycle, {$cycles}, so at most {$plan['pairsPerDay']} pairs a day: up to {$peso($plan['maxCyclePairingIncome'])} pairing bonus per cycle and {$peso($plan['maxDailyPairingIncome'])} per day. Pair counts reset every day at midnight.
- Points that aren't paired stay on your legs for later pairs.
- Example: you sponsor Ana on your left leg: {$peso($plan['directReferralBonus'])}. Then you sponsor Ben on your right leg: {$peso($plan['directReferralBonus'])} direct + {$peso($plan['pairingBonus'])} pairing (Ana's and Ben's points make a pair).
- Earnings are not guaranteed: they depend on people actually joining. The administrator can change the bonuses and pair limits (Management Admin → Binary Complan).

ACTIVATION CODES
- An activation code with its PIN pays for one membership. Executive codes (EVO88-EXE-…) are for Executive sign-ups and for Register Downline; Chairman's Circle codes (EVO88-VIP-…) are for Chairman's Circle sign-ups.
- Members get Executive codes in Backoffice → My Account → Activation Codes, paid from their available balance at {$peso($plan['entryFee'])} each (1 to 20 at a time), and can transfer an unused code to another member by username.
- A code is Active, Inactive (it can't be used until the administrator activates it) or Used. Each has a serial number like SN-000001. Keep the PIN private.

MEMBER BACKOFFICE TABS
1. Dashboard: wallet and earnings, today's pair progress per cycle, Request Encashment.
2. My Account: your accounts ("heads": a member can have several; switch the Active Head at the top), profile, Security & 2FA PIN, activation code vault, NFC card settings.
3. Network: Network Summary; Network Genealogy (Tree): click a member to open their downlines, the eye icon shows their points; Register Downline (place a new member with an Executive activation code: Auto, Left or Right, or click an empty slot); Sign-up Placement (where sign-ups with your ID go); Network List; Unilevel Sponsorship Tree (who sponsored whom).
4. Transactions: every bonus, encashment, code purchase and order.
5. Reports: earnings charts.
6. Shop: products at member prices, paid with your available balance or with Maya (card or Maya wallet). Products: {$products}.
7. Digital Card CMS: edit your 1EVOAI digital card, theme and NFC tap action; membership renewal.
8. Contacts: your contact list, auto responder emails (written by you, or AI writes a personal one for each contact) and the outbox.
9. My Website: once you own a .com domain (Digital Card CMS → Your .com Domain), build your own one-page website there: pick a theme, add sections (hero, about, services, gallery, testimonials, video, questions, contact form, digital card, social links), upload images and publish. Your domain then shows the website and your card is at yourname.com/?view=card. Messages from the website's contact form go to your Contacts.

ENCASHMENT (CASHING OUT)
- Backoffice → Dashboard → Request Encashment. Minimum ₱500. Deductions: {$tax}% tax and a ₱50 processing fee (₱1,000 gives ₱{$sampleNet} net). Payout by GCash, Maya, bank transfer or cheque. It needs your security PIN (set it in My Account → Security & 2FA PIN). The amount leaves your available balance at once and the request stays Pending until the admin pays it out. Released: the transaction shows Completed and a notification gives the payout reference. Rejected: the full amount returns to your available balance, with the reason in your notifications and transactions.

ACCOUNT HELP
- Forgot your password: Main Portal → sign in → Forgot password?
- For anything the portal can't do for you (refunds, account corrections, problems with a payment), contact 88MMM Circle using the contact details above, or ask your sponsor.
TXT;
}

/** A signed-in member's own account summary (only theirs), for questions like "how much have I earned?". */
function assistant_member_context(array $user): string
{
    $a = mlm_active_account($user);
    $plan = mlm_complan();
    $cycle = mlm_current_cycle();
    $pairs = $a['cycle' . $cycle . 'PairsToday'];
    $unused = (int) db_value('SELECT COUNT(*) FROM mlm_codes WHERE owner_account_id = ? AND status = "active"', [$a['id']]);
    $peso = fn ($n): string => '₱' . number_format((float) $n, 2);
    $n = fn (int $count, string $what): string => $count . ' ' . $what . ($count === 1 ? '' : 's');
    return implode("\n", [
        "THE MEMBER YOU ARE TALKING TO (their own data; as of now)",
        "- Name: {$user['name']}. Active head: {$a['id']} ({$a['accountName']}). Sponsor: {$a['sponsorId']}.",
        "- Available balance: {$peso($a['availableBalance'])}. Total income: {$peso($a['totalIncome'])} (direct referral {$peso($a['totalDirectBonus'])}, pairing {$peso($a['totalPairingBonus'])}). Total encashed: {$peso($a['totalEncashment'])}.",
        "- Directly sponsored: {$a['sponsoredCount']}. Left leg: {$n($a['leftCount'], 'member')}, {$n($a['leftPoints'], 'point')} waiting. Right leg: {$n($a['rightCount'], 'member')}, {$n($a['rightPoints'], 'point')} waiting.",
        "- Pairs so far in " . mlm_cycle_label($cycle) . ": {$pairs} of {$plan['maxPairsPerCycle']}.",
        "- Sign-up Placement: " . (MLM_PLACEMENTS[$a['signupPlacement']] ?? 'Auto') . ". Unused activation codes: {$unused}.",
    ]);
}

/** How the assistant behaves. */
function assistant_system_prompt(?array $user): string
{
    $rules = <<<'TXT'
You are the 88MMM Assistant on the 88MMM Circle portal. You answer questions about the 88MMM Circle compensation plan, membership, the 1EVOAI cards and how to use this portal.

Rules:
- Answer from the facts below. If they don't cover something, say you don't know and suggest contacting 88MMM Circle (contact details below) or the sponsor. Never make up features, prices, dates, rules or policies.
- Explain the plan with the real numbers, and give a short worked example when it helps. Never promise or estimate guaranteed income; when asked how much someone can earn, explain the bonuses and limits and say earnings depend on people actually joining.
- You can't see other members' data, and you can't make payments, transfers or changes. Tell people where to do it in the portal.
- Never ask for passwords, PINs or activation code PINs. If someone shares one, tell them to keep it private.
- If a question isn't about 88MMM Circle or this portal, say briefly that you can only help with 88MMM questions.
- Reply in the language the person writes in (English, Filipino or Taglish).
- Keep answers short and clear: a few sentences, or a short list of steps. Plain text; you may use "- " for list items and **bold** for key words. No tables, no headings.
TXT;
    $text = $rules . "\n\nFACTS\n" . assistant_knowledge();
    if ($user) {
        $text .= "\n\n" . assistant_member_context($user);
    } else {
        $text .= "\n\nThe person isn't signed in; you don't know who they are.";
    }
    return $text;
}

/**
 * Answer the last question of a conversation. $messages: [['role' => 'user'
 * |'assistant', 'content' => …], …] from the browser, ending with the
 * question. Returns the answer. Throws InvalidArgumentException (bad input,
 * limit reached) or AiError.
 */
function assistant_reply(?array $user, array $messages): string
{
    if (!assistant_enabled()) {
        throw new InvalidArgumentException('The assistant is off right now.');
    }
    $clean = [];
    foreach (array_slice(array_values($messages), -ASSISTANT_MAX_MESSAGES) as $m) {
        $role = is_array($m) ? ($m['role'] ?? '') : '';
        $content = is_array($m) && is_string($m['content'] ?? null) ? trim(mb_substr($m['content'], 0, ASSISTANT_MAX_CHARS)) : '';
        if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
            $clean[] = ['role' => $role, 'content' => $content];
        }
    }
    // The conversation must start and end with the person.
    while ($clean && $clean[0]['role'] !== 'user') {
        array_shift($clean);
    }
    if (!$clean || end($clean)['role'] !== 'user') {
        throw new InvalidArgumentException('Type a question first.');
    }

    if ($user) {
        $limit = max(1, (int) config('ai.chat_limit', 100));
        $bucket = 'ai-chat:' . $user['id'];
        $window = 86400;
        $tooMany = "You've asked {$limit} questions today. You can ask more tomorrow.";
    } else {
        $limit = max(1, (int) config('ai.chat_guest_limit', 20));
        $bucket = 'ai-chat-ip:' . client_ip();
        $window = 3600;
        $tooMany = "That's {$limit} questions this hour. Sign in, or try again a little later.";
    }
    if (rate_limited($bucket, $limit, $window)) {
        throw new InvalidArgumentException($tooMany);
    }
    rate_limit_hit($bucket, $window);
    return ai_chat(assistant_system_prompt($user), $clean, 2048);
}
