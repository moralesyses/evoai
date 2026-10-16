<?php
/**
 * 88MMM Assistant: the chat button and window (includes/assistant.php,
 * assets/js/assistant.js, positions in assets/css/custom.css). Included by
 * views/layout.php while AI is on, and for administrators while it is off
 * ($assistantOn false: the window says which API key is missing). Expects
 * $user (array|null) and $assistantOn.
 */
$asSuggestions = assistant_suggestions($user);
$asProvider = ai_provider('chat');
?>
<div data-assistant data-suggestions="<?= e(json_encode($asSuggestions, JSON_UNESCAPED_UNICODE)) ?>"<?= $assistantOn ? '' : ' data-assistant-off' ?>>
    <button type="button" data-assistant-toggle aria-expanded="false" aria-controls="evo-assistant-panel" aria-label="Ask the 88MMM Assistant" class="evo-assistant-btn flex items-center gap-2 px-4 py-3 rounded-full bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-extrabold text-xs shadow-2xl hover:brightness-105 transition-all">
        <?= icon('sparkles', 'w-4 h-4') ?>
        <span data-assistant-label>Ask AI</span>
    </button>

    <section id="evo-assistant-panel" data-assistant-panel hidden role="dialog" aria-labelledby="evo-assistant-title" class="evo-assistant-panel rounded-3xl bg-[#121212] border border-[#f3bd35]/40 shadow-2xl overflow-hidden">
        <header class="flex items-center gap-3 px-4 py-3 border-b border-zinc-800 bg-gradient-to-r from-[#1c170d] to-[#121212]">
            <div class="w-9 h-9 rounded-full bg-[#f3bd35]/15 text-[#ffda72] flex items-center justify-center shrink-0"><?= icon('sparkles', 'w-4 h-4') ?></div>
            <div class="flex-1 min-w-0">
                <h2 id="evo-assistant-title" class="text-sm font-bold text-white">88MMM Assistant</h2>
                <p class="text-[10px] text-zinc-400 truncate">Ask about the rewards plan, joining, codes or the portal</p>
            </div>
            <button type="button" data-assistant-reset title="New chat" aria-label="Start a new chat" class="p-1.5 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800"><?= icon('rotate-ccw', 'w-4 h-4') ?></button>
            <button type="button" data-assistant-close title="Close" aria-label="Close the assistant" class="p-1.5 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800"><?= icon('x', 'w-4 h-4') ?></button>
        </header>

        <?php if (!$assistantOn): ?>
            <div class="evo-assistant-log px-4 py-4 text-xs text-zinc-300 space-y-2">
                <p class="font-semibold text-amber-200">The 88MMM Assistant is off, so members and visitors don't see this button.</p>
                <p>It uses <?= e(AI_PROVIDERS[$asProvider]) ?> (<span class="font-mono">ai.chat_provider</span> in config/config.php), and <?= e(AI_PROVIDERS[$asProvider === 'groq' ? 'anthropic' : 'groq']) ?> answers when that one can't. To turn it on, add either API key in config/config.php: <span class="font-mono text-zinc-200">'ai' =&gt; ['anthropic' =&gt; ['api_key' =&gt; 'sk-ant-…']]</span> or <span class="font-mono text-zinc-200">['groq' =&gt; ['api_key' =&gt; 'gsk_…']]</span>.</p>
                <p class="text-zinc-500">Only administrators see this message.</p>
            </div>
        <?php else: ?>
        <div data-assistant-log role="log" aria-live="polite" class="evo-assistant-log px-4 py-3 space-y-3 text-xs"></div>

        <div data-assistant-suggest class="px-4 pb-2 flex flex-wrap gap-1.5"></div>

        <form data-assistant-form class="px-3 pb-3 pt-2 border-t border-zinc-800 space-y-1.5">
            <div class="flex items-end gap-2">
                <label for="evo-assistant-input" class="evo-sr-only">Your question</label>
                <textarea id="evo-assistant-input" data-assistant-input rows="1" maxlength="1000" placeholder="Ask a question…" class="flex-1 px-3 py-2 rounded-xl bg-zinc-900 border border-zinc-700 text-white text-xs focus:outline-none focus:border-[#f3bd35] " style="max-height:120px;resize:none"></textarea>
                <button type="submit" data-assistant-send aria-label="Send" class="shrink-0 p-2.5 rounded-xl bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black hover:brightness-105 disabled:opacity-50"><?= icon('send', 'w-4 h-4') ?></button>
            </div>
            <p class="text-[10px] text-zinc-500">AI answers can be wrong. For account problems, contact 88MMM Circle.</p>
        </form>
        <?php endif; ?>
    </section>
</div>
