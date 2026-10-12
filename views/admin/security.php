<?php
/**
 * Admin tab "Security & Audit Log" (reference component "In").
 *
 * The signed-in member's E2EE key pair lives only in this browser, so the
 * fingerprint and public JWK are filled in by assets/js/views/admin-security.js
 * (EvoCrypto.getKeyPair). Below the reference's two cards, a compact audit trail
 * lists the latest server-side audit events ($auditLogs).
 */
$secUserNames = [];
foreach ($users as $secU) {
    $secUserNames[$secU['id']] = $secU['name'];
}
?>
<div class="max-w-3xl mx-auto space-y-6" id="security-panel">
    <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-[#f3bd35]/15 flex items-center justify-center text-[#f3bd35]">
                    <?= icon('lock', 'w-5 h-5') ?>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Hardware &amp; Cryptographic Security</h3>
                    <p class="text-xs text-zinc-400">SubtleCrypto ECDH (Elliptic Curve Diffie-Hellman) P-256 + AES-GCM 256</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
                <?= icon('shield-check', 'w-4 h-4') ?>
                Client-Side E2EE Active
            </span>
        </div>
        <p class="text-xs text-zinc-300 leading-relaxed mb-6">
            All executive chats and inquiry transmissions are encrypted directly inside your device's browser memory prior to transmission over the
            network. The central server has zero knowledge of your private key and stores only authenticated AES-GCM ciphertexts and random
            initialization vectors (IVs).
        </p>
        <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2 mb-4">
            <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-zinc-300 flex items-center gap-1.5">
                    <?= icon('cpu', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                    Cryptographic Fingerprint (SHA-256)
                </span>
                <span class="text-[10px] text-[#ffda72] font-mono">P-256 Curve</span>
            </div>
            <div id="sec-fingerprint" class="p-2.5 rounded-xl bg-zinc-900 font-mono text-xs text-[#f3bd35] tracking-widest break-all select-all border border-zinc-800">GENERATING_KEY...</div>
        </div>
        <div class="p-4 rounded-2xl bg-zinc-950 border border-zinc-800 space-y-2 mb-6">
            <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-zinc-300 flex items-center gap-1.5">
                    <?= icon('file-code', 'w-3.5 h-3.5 text-[#f3bd35]') ?>
                    Public Key JSON Web Key (JWK)
                </span>
                <button type="button" id="sec-copy-jwk" class="flex items-center gap-1 text-[#f3bd35] hover:underline text-[11px]">
                    <?= icon('copy', 'w-3 h-3') ?>
                    <span>Copy JWK</span>
                </button>
            </div>
            <pre id="sec-jwk" class="p-2.5 rounded-xl bg-zinc-900 font-mono text-[10px] text-zinc-400 overflow-x-auto max-h-36 border border-zinc-800">Loading...</pre>
        </div>
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <button type="button" id="sec-export" class="w-full sm:w-auto flex-1 flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-[#ffda72] to-[#f3bd35] text-black font-bold text-xs rounded-xl shadow-lg hover:brightness-105 active:scale-95 transition-transform">
                <?= icon('download', 'w-4 h-4') ?>
                <span>Export Encrypted Key Backup (.json)</span>
            </button>
        </div>
    </div>

    <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 shadow-xl space-y-3">
        <h4 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35]">Cryptographic Invariants &amp; Security Specs</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div class="p-3 rounded-2xl bg-zinc-900 border border-zinc-800">
                <span class="text-zinc-400 block text-[11px]">Symmetric Cipher</span>
                <strong class="text-white block mt-0.5">AES-GCM (256-bit)</strong>
                <span class="text-[10px] text-zinc-500">Galois/Counter Mode with 128-bit authentication tag</span>
            </div>
            <div class="p-3 rounded-2xl bg-zinc-900 border border-zinc-800">
                <span class="text-zinc-400 block text-[11px]">Key Exchange</span>
                <strong class="text-white block mt-0.5">ECDH (NIST P-256)</strong>
                <span class="text-[10px] text-zinc-500">SubtleCrypto browser native hardware accelerated</span>
            </div>
            <div class="p-3 rounded-2xl bg-zinc-900 border border-zinc-800">
                <span class="text-zinc-400 block text-[11px]">Initialization Vector</span>
                <strong class="text-white block mt-0.5">96-bit (12-byte) Cryptographic Random</strong>
                <span class="text-[10px] text-zinc-500">Unique IV generated per message, never reused</span>
            </div>
            <div class="p-3 rounded-2xl bg-zinc-900 border border-zinc-800">
                <span class="text-zinc-400 block text-[11px]">Safety Number Format</span>
                <strong class="text-white block mt-0.5">60 Digits (12 blocks of 5)</strong>
                <span class="text-[10px] text-zinc-500">Signal protocol standard out-of-band verification</span>
            </div>
        </div>
    </div>

    <div class="p-5 rounded-3xl bg-[#111111] border border-[#f3bd35]/20 shadow-xl space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold uppercase tracking-wider text-[#f3bd35] flex items-center gap-1.5"><?= icon('shield-check', 'w-3.5 h-3.5') ?> Security &amp; Audit Logs</h4>
            <span class="text-[10px] text-zinc-500"><?= count($auditLogs) ?> Recent Events</span>
        </div>
        <div class="space-y-2 max-h-56 overflow-y-auto pr-1 text-[11px] text-zinc-400">
            <?php if (!$auditLogs): ?>
                <div class="p-2 rounded-xl bg-zinc-950 border border-zinc-800/80">No audit events recorded yet.</div>
            <?php endif; ?>
            <?php foreach ($auditLogs as $log): ?>
                <div class="p-2 rounded-xl bg-zinc-950 border border-zinc-800/80">
                    <div class="text-zinc-200 font-medium"><?= e($log['action']) ?></div>
                    <div class="text-[9px] text-zinc-500 mt-0.5"><?= e($secUserNames[$log['userId']] ?? ($log['userId'] ?: 'System')) ?><?= $log['accountId'] ? ' (' . e($log['accountId']) . ')' : '' ?> · <?= e($log['ipAddress']) ?> · <?= e($log['device']) ?> · <?= e(fmt_datetime($log['timestamp'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
