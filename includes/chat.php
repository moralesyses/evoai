<?php
/**
 * End-to-end encrypted chat storage. Messages are encrypted in the browser
 * (AES-GCM-256); the server only stores ciphertext, IV and key metadata.
 * The reference used a WebSocket; this build uses short-interval polling,
 * which works on any Apache/PHP host.
 */
declare(strict_types=1);

const CHAT_VIP_ROOM = 'circle-vip';
const CHAT_REACTIONS = ['🤝', '💼', '👏'];
const CHAT_BURN_TIMERS = [0, 30, 300, 3600];

function chat_direct_room(string $a, string $b): string
{
    $ids = [$a, $b];
    sort($ids, SORT_STRING);
    return 'direct:' . $ids[0] . ':' . $ids[1];
}

/** Channels shown to a member (same labels as the reference portal). */
function chat_rooms_for(array $user): array
{
    $rooms = [[
        'id' => CHAT_VIP_ROOM, 'name' => '88MMM Executive VIP Channel', 'badge' => 'Group E2EE',
        'desc' => 'Exclusive society announcements and mastermind deal syndication', 'peerId' => null,
    ]];
    $execDesc = 'Direct end-to-end encrypted executive channel';
    $dealDesc = 'Private high-net-worth deal inquiries';
    if ($user['id'] === 'usr_mike') {
        $peers = [['usr_sofia', 'Sofia Cruz (Apex Ventures)', $execDesc], ['usr_david', 'David Tan (Horizon Cap)', $dealDesc]];
    } else {
        $peers = [['usr_mike', 'Mike Maranan (Chairman)', $user['id'] === 'usr_david' ? $dealDesc : $execDesc]];
        if ($user['id'] !== 'usr_david') {
            $peers[] = ['usr_david', 'David Tan (Horizon Cap)', $dealDesc];
        }
    }
    foreach ($peers as [$peerId, $name, $desc]) {
        if ($peerId === $user['id'] || !db_value('SELECT 1 FROM users WHERE id = ?', [$peerId])) {
            continue;
        }
        $rooms[] = ['id' => chat_direct_room($user['id'], $peerId), 'name' => $name, 'badge' => 'Direct E2EE', 'desc' => $desc, 'peerId' => $peerId];
    }
    return $rooms;
}

function chat_can_access(array $user, string $roomId): bool
{
    if ($roomId === CHAT_VIP_ROOM) {
        return true;
    }
    if (!preg_match('/^direct:(usr_[A-Za-z0-9_]+):(usr_[A-Za-z0-9_]+)$/', $roomId, $m)) {
        return false;
    }
    if ($roomId !== chat_direct_room($m[1], $m[2])) {
        return false;
    }
    return in_array($user['id'], [$m[1], $m[2]], true);
}

function chat_peer_id(array $user, string $roomId): ?string
{
    if (!preg_match('/^direct:(usr_[A-Za-z0-9_]+):(usr_[A-Za-z0-9_]+)$/', $roomId, $m)) {
        return null;
    }
    return $m[1] === $user['id'] ? $m[2] : $m[1];
}

function chat_message_to_array(array $r): array
{
    return [
        'seq' => (int) $r['seq'], 'id' => $r['id'], 'roomId' => $r['room_id'], 'senderId' => $r['sender_id'],
        'senderName' => $r['sender_name'], 'senderRole' => $r['sender_role'], 'ciphertext' => $r['ciphertext'],
        'iv' => $r['iv'], 'ephemeralTimer' => (int) $r['ephemeral_timer'], 'timestamp' => (int) $r['created_ms'],
        'algorithm' => $r['algorithm'], 'keyScheme' => $r['key_scheme'],
        'senderPub' => $r['sender_pub'], 'peerPub' => $r['peer_pub'], 'isEncrypted' => true,
    ];
}

/** Shred self-destructing messages whose burn timer has elapsed. */
function chat_purge_expired(): void
{
    $nowMs = (int) floor(microtime(true) * 1000);
    db_run('DELETE FROM chat_messages WHERE ephemeral_timer > 0 AND created_ms + ephemeral_timer * 1000 <= ?', [$nowMs]);
}

function chat_valid_jwk(?string $json): ?string
{
    if ($json === null || $json === '') {
        return null;
    }
    $d = json_decode($json, true);
    if (!is_array($d) || ($d['kty'] ?? '') !== 'EC' || ($d['crv'] ?? '') !== 'P-256' || empty($d['x']) || empty($d['y'])) {
        throw new InvalidArgumentException('Invalid public key payload.');
    }
    return json_encode(['crv' => 'P-256', 'ext' => true, 'key_ops' => [], 'kty' => 'EC', 'x' => (string) $d['x'], 'y' => (string) $d['y']], JSON_UNESCAPED_SLASHES);
}

function chat_post(array $user, array $in): array
{
    $roomId = str_in($in, 'roomId', 120);
    if (!chat_can_access($user, $roomId)) {
        throw new InvalidArgumentException('You do not have access to this channel.');
    }
    $ciphertext = (string) ($in['ciphertext'] ?? '');
    $iv = (string) ($in['iv'] ?? '');
    if ($ciphertext === '' || strlen($ciphertext) > 65536 || !preg_match('#^[A-Za-z0-9+/=]+$#', $ciphertext)) {
        throw new InvalidArgumentException('Invalid ciphertext.');
    }
    if (!preg_match('#^[A-Za-z0-9+/=]{16}$#', $iv)) {
        throw new InvalidArgumentException('Invalid initialization vector.');
    }
    $timer = int_in($in, 'ephemeralTimer');
    if (!in_array($timer, CHAT_BURN_TIMERS, true)) {
        $timer = 0;
    }
    $scheme = ($in['keyScheme'] ?? '') === 'ecdh-p256' ? 'ecdh-p256' : 'room-pbkdf2';
    $senderPub = $scheme === 'ecdh-p256' ? chat_valid_jwk((string) ($in['senderPub'] ?? '')) : null;
    $peerPub = $scheme === 'ecdh-p256' ? chat_valid_jwk((string) ($in['peerPub'] ?? '')) : null;
    if ($scheme === 'ecdh-p256' && (!$senderPub || !$peerPub || $roomId === CHAT_VIP_ROOM)) {
        throw new InvalidArgumentException('Direct ECDH messages require both public keys.');
    }
    $id = (string) ($in['id'] ?? '');
    if (!preg_match('/^msg_\d{10,16}_[a-z0-9]{4,10}$/', $id) || db_value('SELECT 1 FROM chat_messages WHERE id = ?', [$id])) {
        $id = 'msg_' . (int) floor(microtime(true) * 1000) . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    }
    $nowMs = (int) floor(microtime(true) * 1000);
    db_run('INSERT INTO chat_messages (id, room_id, sender_id, sender_name, sender_role, ciphertext, iv, ephemeral_timer, algorithm, key_scheme, sender_pub, peer_pub, created_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "AES-GCM-256", ?, ?, ?, ?)',
        [$id, $roomId, $user['id'], $user['name'], $user['role'], $ciphertext, $iv, $timer, $scheme, $senderPub, $peerPub, $nowMs]);
    db_run('DELETE FROM chat_typing WHERE room_id = ? AND user_id = ?', [$roomId, $user['id']]);
    bump_counter('messagesExchanged');
    return chat_message_to_array(db_one('SELECT * FROM chat_messages WHERE id = ?', [$id]));
}

/** Messages after $since (sequence), reaction map, typing users and peer key. */
function chat_poll(array $user, string $roomId, int $since): array
{
    if (!chat_can_access($user, $roomId)) {
        throw new InvalidArgumentException('You do not have access to this channel.');
    }
    chat_purge_expired();
    $rows = db_all('SELECT * FROM chat_messages WHERE room_id = ? AND seq > ? ORDER BY seq ASC LIMIT 300', [$roomId, $since]);
    $messages = array_map('chat_message_to_array', $rows);

    $reactions = [];
    foreach (db_all('SELECT r.message_id, r.emoji, r.user_id FROM chat_reactions r JOIN chat_messages m ON m.id = r.message_id WHERE m.room_id = ? ORDER BY r.created_at', [$roomId]) as $r) {
        $reactions[$r['message_id']][$r['emoji']][] = $r['user_id'];
    }
    $alive = array_map(fn ($r) => $r['id'], db_all('SELECT id FROM chat_messages WHERE room_id = ?', [$roomId]));

    $typing = array_map(fn ($r) => $r['user_name'], db_all(
        'SELECT user_name FROM chat_typing WHERE room_id = ? AND user_id <> ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 6 SECOND)',
        [$roomId, $user['id']]
    ));

    $peerKey = null;
    $peerId = chat_peer_id($user, $roomId);
    if ($peerId) {
        $peerKey = db_value('SELECT public_key FROM users WHERE id = ?', [$peerId]);
    }
    $latest = (int) db_value('SELECT COALESCE(MAX(seq), 0) FROM chat_messages WHERE room_id = ?', [$roomId]);
    return [
        'messages' => $messages, 'reactions' => $reactions, 'alive' => $alive, 'typing' => $typing,
        'peerPublicKey' => $peerKey, 'latestSeq' => max($latest, $since),
    ];
}

function chat_toggle_reaction(array $user, string $messageId, string $emoji): void
{
    if (!in_array($emoji, CHAT_REACTIONS, true)) {
        throw new InvalidArgumentException('Unsupported reaction.');
    }
    $room = db_value('SELECT room_id FROM chat_messages WHERE id = ?', [$messageId]);
    if (!$room || !chat_can_access($user, (string) $room)) {
        throw new InvalidArgumentException('Message not found.');
    }
    $exists = db_value('SELECT 1 FROM chat_reactions WHERE message_id = ? AND emoji = ? AND user_id = ?', [$messageId, $emoji, $user['id']]);
    if ($exists) {
        db_run('DELETE FROM chat_reactions WHERE message_id = ? AND emoji = ? AND user_id = ?', [$messageId, $emoji, $user['id']]);
    } else {
        db_run('INSERT INTO chat_reactions (message_id, emoji, user_id) VALUES (?, ?, ?)', [$messageId, $emoji, $user['id']]);
    }
}

function chat_set_typing(array $user, string $roomId, bool $typing): void
{
    if (!chat_can_access($user, $roomId)) {
        return;
    }
    if ($typing) {
        db_run('INSERT INTO chat_typing (room_id, user_id, user_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), updated_at = NOW()',
            [$roomId, $user['id'], $user['name']]);
    } else {
        db_run('DELETE FROM chat_typing WHERE room_id = ? AND user_id = ?', [$roomId, $user['id']]);
    }
}
