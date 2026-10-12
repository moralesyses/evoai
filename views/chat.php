<?php
/**
 * E2EE executive chat (reference component "Bn").
 * Messages are encrypted/decrypted in the browser; see assets/js/views/chat.js.
 */
if (!$user) {
    $gateTitle = 'Sign in to open the E2EE Executive Chat';
    $gateText = 'Encrypted channels are available to verified 88MMM members. Your device generates its own ECDH P-256 key on sign-in — the server only ever stores ciphertext.';
    require __DIR__ . '/partials/gate.php';
    return;
}
$rooms = chat_rooms_for($user);
$activeRoom = (string) ($_GET['room'] ?? CHAT_VIP_ROOM);
if (!in_array($activeRoom, array_column($rooms, 'id'), true)) {
    $activeRoom = CHAT_VIP_ROOM;
}
$active = $rooms[array_search($activeRoom, array_column($rooms, 'id'), true)];
$peerKeys = [];
foreach (db_all("SELECT id, name, public_key FROM users WHERE role <> 'visitor'") as $u) {
    $peerKeys[$u['id']] = ['name' => $u['name'], 'publicKey' => $u['public_key']];
}
$pageScripts[] = 'assets/js/views/chat.js';
$pageData['chat'] = ['rooms' => $rooms, 'activeRoom' => $activeRoom, 'users' => $peerKeys, 'pollMs' => 2000];
$roomOn = 'bg-[#181818] border border-[#f3bd35]/50 shadow-md';
$roomOff = 'hover:bg-zinc-900 border border-transparent';
?>
<div class="max-w-6xl mx-auto">
    <div class="flex flex-col lg:flex-row h-[78vh] rounded-3xl bg-[#0c0c0c] border border-[#f3bd35]/25 overflow-hidden shadow-2xl">
        <div class="w-full lg:w-72 border-b lg:border-b-0 lg:border-r border-zinc-800/80 bg-[#101010] p-3 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between px-2 py-2 mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#f3bd35]">Encrypted Channels</span>
                    <div class="flex items-center gap-1.5">
                        <span id="chat-status-dot" class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span id="chat-status-text" class="text-[10px] text-zinc-400">Connecting</span>
                    </div>
                </div>
                <div class="space-y-1.5" role="tablist" aria-label="Channels">
                    <?php foreach ($rooms as $r): $on = $r['id'] === $activeRoom; ?>
                        <button type="button" data-room="<?= e($r['id']) ?>" data-on="<?= $roomOn ?>" data-off="<?= $roomOff ?>" class="w-full text-left p-3 rounded-2xl transition-all <?= $on ? $roomOn : $roomOff ?>">
                            <div class="flex items-center justify-between mb-1">
                                <span data-on="text-[#ffda72]" data-off="text-zinc-200" class="text-xs font-bold truncate <?= $on ? 'text-[#ffda72]' : 'text-zinc-200' ?>"><?= e($r['name']) ?></span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-[#f3bd35]/15 text-[#f3bd35] font-semibold whitespace-nowrap"><?= e($r['badge']) ?></span>
                            </div>
                            <p class="text-[11px] text-zinc-400 truncate"><?= e($r['desc']) ?></p>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mt-4 p-3 rounded-2xl bg-zinc-900/80 border border-zinc-800 text-[11px] text-zinc-400">
                <div class="flex items-center justify-between mb-1 text-zinc-300">
                    <span class="font-semibold flex items-center gap-1"><?= icon('key-round', 'w-3.5 h-3.5 text-[#f3bd35]') ?>Device Key</span>
                    <span class="text-[10px] text-[#f3bd35] font-mono">ECDH P-256</span>
                </div>
                <p id="chat-fingerprint" class="text-[10px] text-zinc-400 font-mono truncate">GENERATING_FINGERPRINT...</p>
            </div>
        </div>
        <div class="flex-1 flex flex-col bg-[#080808]">
            <div class="p-3.5 border-b border-zinc-800/80 bg-[#0e0e0e] flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-full bg-[#f3bd35]/20 flex items-center justify-center text-[#f3bd35]"><?= icon('lock', 'w-4 h-4') ?></div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold text-white flex items-center gap-1.5"><span id="chat-room-name"><?= e($active['name']) ?></span><?= icon('shield-check', 'w-3.5 h-3.5 text-[#f3bd35]') ?></h3>
                        <p class="text-[10px] text-zinc-400">AES-GCM-256 Bit Authenticated Encryption · Server Zero-Knowledge</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="chat-safety" title="Verify Cryptographic Safety Numbers" class="flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-[11px] text-[#f3bd35] transition-colors">
                        <?= icon('qr-code', 'w-3.5 h-3.5') ?><span class="hidden sm:inline">Safety Numbers</span>
                    </button>
                    <button type="button" id="chat-sound" class="p-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white transition-colors" title="Mute Chimes">
                        <span data-sound-on><?= icon('volume-2', 'w-4 h-4 text-[#f3bd35]') ?></span><span data-sound-off hidden><?= icon('volume-x', 'w-4 h-4') ?></span>
                    </button>
                </div>
            </div>
            <div id="chat-scroll" class="flex-1 overflow-y-auto p-4 space-y-4" aria-live="polite">
                <div class="text-center my-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#f3bd35]/10 border border-[#f3bd35]/25 text-[#f3bd35] text-[10px] font-semibold"><?= icon('lock', 'w-3 h-3') ?>Messages in this room are end-to-end encrypted with AES-256-GCM.</span>
                </div>
                <div id="chat-messages" class="space-y-4"></div>
                <div id="chat-typing" hidden class="flex items-center gap-2 text-xs text-[#ffda72] italic pl-2">
                    <span class="w-2 h-2 rounded-full bg-[#f3bd35] animate-ping"></span><span data-typing-text></span>
                </div>
                <div id="chat-end"></div>
            </div>
            <div class="p-3 border-t border-zinc-800/80 bg-[#0e0e0e]">
                <div class="flex items-center gap-1.5 mb-2 overflow-x-auto pb-1 text-[11px]">
                    <span class="text-zinc-500 whitespace-nowrap text-[10px]">Quick:</span>
                    <?php foreach (["Interested in Chairman's Circle", "Let's schedule breakfast", 'Send deal syndication brief', 'NFC card received in BGC'] as $q): ?>
                        <button type="button" data-quick="<?= e($q) ?>" class="px-2.5 py-1 rounded-full bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-zinc-300 whitespace-nowrap active:scale-95 transition-all"><?= e($q) ?></button>
                    <?php endforeach; ?>
                </div>
                <form id="chat-form" class="flex items-center gap-2" autocomplete="off">
                    <div class="relative">
                        <select id="chat-burn" title="Self-Destruct Ephemeral Timer" aria-label="Self-Destruct Ephemeral Timer" class="bg-zinc-900 border border-zinc-700 text-zinc-300 text-xs rounded-xl px-2.5 py-2.5 focus:outline-none focus:border-[#f3bd35]">
                            <option value="0">Burn: Off</option>
                            <option value="30">Burn: 30s</option>
                            <option value="300">Burn: 5m</option>
                            <option value="3600">Burn: 1h</option>
                        </select>
                    </div>
                    <input id="chat-input" type="text" maxlength="4000" placeholder="Send end-to-end encrypted message..." aria-label="Message" class="flex-1 px-4 py-2.5 bg-zinc-900 border border-zinc-700 rounded-xl text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-[#f3bd35] transition-colors">
                    <button type="submit" id="chat-send" disabled aria-label="Send" class="flex items-center justify-center px-4 py-2.5 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs rounded-xl hover:brightness-105 active:scale-95 transition-transform disabled:opacity-40"><?= icon('send', 'w-4 h-4') ?></button>
                </form>
            </div>
        </div>
    </div>

    <div data-modal="chat-safety" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-fadeIn" role="dialog" aria-modal="true" aria-labelledby="safety-title">
        <div class="w-full max-w-md rounded-3xl bg-[#141414] border border-[#f3bd35]/40 p-6 shadow-2xl text-center relative">
            <button type="button" data-close-modal class="absolute top-4 right-4 text-zinc-400 hover:text-white p-1 rounded-full" aria-label="Close">✕</button>
            <div class="w-12 h-12 rounded-full bg-[#f3bd35]/20 flex items-center justify-center text-[#f3bd35] mx-auto mb-3"><?= icon('shield-check', 'w-6 h-6') ?></div>
            <h3 id="safety-title" class="text-base font-bold text-white mb-1">Verify Safety Numbers</h3>
            <p class="text-xs text-zinc-400 mb-5">Compare this 60-digit safety number with <strong class="text-[#ffda72]" data-safety-peer>88MMM Peer</strong> to verify the end-to-end encryption channel.</p>
            <div id="safety-numbers" class="grid grid-cols-3 gap-2 p-4 bg-zinc-950 rounded-2xl border border-zinc-800 font-mono text-sm tracking-widest text-[#ffda72] mb-5"></div>
            <div class="flex items-center justify-center gap-2 text-xs text-emerald-400 mb-6"><?= icon('user-check', 'w-4 h-4') ?><span>ECDH P-256 Key Exchange Verified</span></div>
            <button type="button" data-close-modal class="w-full py-2.5 bg-[#f3bd35] text-black font-bold text-xs rounded-xl shadow-lg">Mark As Verified</button>
        </div>
    </div>
</div>
