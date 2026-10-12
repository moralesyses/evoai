<?php
/**
 * 88MMM Assistant (includes/assistant.php): answers a question about the
 * compensation plan or the portal. Open to visitors too (limited per hour);
 * signed-in members also get answers about their own account.
 * Loaded by api.php (assets/js/assistant.js).
 */
declare(strict_types=1);

return [
    // messages: the recent conversation, ending with the question. When the AI
    // service asks to wait, retryAfter (seconds) tells the chat window to ask again.
    'assistant.ask' => static function (array $in): array {
        try {
            return ['reply' => assistant_reply(current_user(), is_array($in['messages'] ?? null) ? $in['messages'] : [])];
        } catch (AiError $e) {
            if ($e->kind === 'retry' && $e->retryAfter > 0) {
                return ['reply' => null, 'retryAfter' => min(60, $e->retryAfter)];
            }
            api_fail($e->kind === 'retry' ? 'The assistant is busy right now. Please try again in a moment.' : 'The assistant could not answer that. Please try again.');
        }
    },
];
