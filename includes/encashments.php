<?php
/**
 * Processing members' encashment requests (Management Admin > Encashments,
 * superadmin only). Members request a payout in their backoffice
 * (mlm_submit_encashment): the amount leaves their available balance at
 * once and the request waits as "pending". Here an admin either
 *   - releases it after sending the net amount (with the payout reference), or
 *   - rejects it with a reason; the amount goes back to the member's balance.
 * Each request is processed once, also when two admins click at the same time.
 * Requests ticked in the list can be released or rejected together, or
 * downloaded as CSV (at most one page, ENCASH_MAX_BULK, at a time).
 */
declare(strict_types=1);

const ENCASH_STATUSES = ['pending' => 'Pending', 'released' => 'Released', 'rejected' => 'Rejected'];
const ENCASH_METHODS = ['gcash' => 'GCash', 'maya' => 'Maya', 'bank_transfer' => 'Bank Transfer', 'cheque' => 'Cheque'];
const ENCASH_PER_PAGE = 25;
/** Requests per bulk release, reject or CSV of ticked requests: one page of the list. */
const ENCASH_MAX_BULK = ENCASH_PER_PAGE;
const ENCASH_MAX_EXPORT = 10000;

/** Validated list filters from a request: q, status ('' or 'all' = every status, the default), method, from, to (YYYY-MM-DD). */
function encash_filters(array $in): array
{
    $date = static fn (string $key) => is_string($in[$key] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $in[$key]) && strtotime($in[$key]) ? $in[$key] : '';
    $status = is_string($in['status'] ?? null) ? $in['status'] : '';
    return [
        'q' => mb_substr(trim(is_string($in['q'] ?? null) ? $in['q'] : ''), 0, 100),
        'status' => isset(ENCASH_STATUSES[$status]) ? $status : '',
        'method' => is_string($in['method'] ?? null) && isset(ENCASH_METHODS[$in['method']]) ? $in['method'] : '',
        'from' => $date('from'),
        'to' => $date('to'),
    ];
}

/** [FROM … WHERE … sql, params] for the filters. */
function encash_filter_sql(array $f): array
{
    $sql = 'FROM mlm_encashments e
            JOIN mlm_accounts a ON a.id = e.account_id
            LEFT JOIN users u ON u.id = a.user_id
            LEFT JOIN users p ON p.id = e.processed_by
            WHERE 1 = 1';
    $params = [];
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $sql .= ' AND (e.id LIKE ? OR e.reference_number LIKE ? OR e.account_id LIKE ? OR e.account_name LIKE ? OR a.username LIKE ?
                       OR u.name LIKE ? OR u.email LIKE ? OR e.account_details LIKE ? OR e.payout_reference LIKE ?)';
        array_push($params, ...array_fill(0, 9, $like));
    }
    if ($f['status'] !== '') {
        $sql .= ' AND e.status = ?';
        $params[] = $f['status'];
    }
    if ($f['method'] !== '') {
        $sql .= ' AND e.payout_method = ?';
        $params[] = $f['method'];
    }
    if ($f['from'] !== '') {
        $sql .= ' AND e.requested_at >= ?';
        $params[] = $f['from'] . ' 00:00:00';
    }
    if ($f['to'] !== '') {
        $sql .= ' AND e.requested_at < ? + INTERVAL 1 DAY';
        $params[] = $f['to'] . ' 00:00:00';
    }
    return [$sql, $params];
}

const ENCASH_COLUMNS = 'e.*, a.username, a.user_id, u.name AS member_name, u.email AS member_email, p.name AS processed_by_name';

/**
 * One page of requests with the totals of everything that matches:
 * [rows, totals{count, gross, tax, fee, net}]. Pending requests come first,
 * oldest first (the work queue); released and rejected ones follow, newest first.
 */
function encash_list(array $f, int $page): array
{
    [$sql, $params] = encash_filter_sql($f);
    $t = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(e.amount), 0) AS gross, COALESCE(SUM(e.tax_deduction), 0) AS tax,
                        COALESCE(SUM(e.processing_fee), 0) AS fee, COALESCE(SUM(e.net_amount), 0) AS net $sql", $params);
    $order = 'e.status = "pending" DESC, IF(e.status = "pending", e.requested_at, NULL) ASC, e.requested_at DESC, e.id DESC';
    $offset = (max(1, $page) - 1) * ENCASH_PER_PAGE;
    $rows = db_all('SELECT ' . ENCASH_COLUMNS . " $sql ORDER BY $order LIMIT " . ENCASH_PER_PAGE . " OFFSET $offset", $params);
    return [$rows, ['count' => (int) $t['n'], 'gross' => (float) $t['gross'], 'tax' => (float) $t['tax'], 'fee' => (float) $t['fee'], 'net' => (float) $t['net']]];
}

/** The figures at the top of the tab, and the last six months. */
function encash_summary(): array
{
    $row = static fn (string $where, array $params = []) => db_one("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS gross, COALESCE(SUM(tax_deduction), 0) AS tax,
            COALESCE(SUM(processing_fee), 0) AS fee, COALESCE(SUM(net_amount), 0) AS net FROM mlm_encashments WHERE $where", $params);
    $monthStart = date('Y-m-01 00:00:00');
    $byMethod = [];
    foreach (db_all('SELECT payout_method, COUNT(*) AS n, SUM(net_amount) AS net FROM mlm_encashments WHERE status = "pending" GROUP BY payout_method') as $m) {
        $byMethod[$m['payout_method']] = ['count' => (int) $m['n'], 'net' => (float) $m['net']];
    }
    $months = db_all('SELECT DATE_FORMAT(requested_at, "%Y-%m") AS ym, COUNT(*) AS requests, SUM(amount) AS requested,
            SUM(status = "released") AS released_n, SUM(IF(status = "released", net_amount, 0)) AS released_net,
            SUM(IF(status = "released", tax_deduction, 0)) AS tax, SUM(IF(status = "released", processing_fee, 0)) AS fees,
            SUM(status = "rejected") AS rejected_n, SUM(status = "pending") AS pending_n
        FROM mlm_encashments WHERE requested_at >= ? GROUP BY ym ORDER BY ym DESC', [date('Y-m-01', strtotime('-5 months', strtotime(date('Y-m-01'))))]);
    return [
        'pending' => $row('status = "pending"'),
        'releasedMonth' => $row('status = "released" AND processed_at >= ?', [$monthStart]),
        'released' => $row('status = "released"'),
        'rejected' => $row('status = "rejected"'),
        'pendingByMethod' => $byMethod,
        'months' => $months,
    ];
}

/** Lock a pending request inside a transaction, or explain why it can't be processed. */
function encash_lock_pending(string $id): array
{
    $e = db_one('SELECT * FROM mlm_encashments WHERE id = ? FOR UPDATE', [$id]);
    if (!$e) {
        throw new InvalidArgumentException('Encashment request not found.');
    }
    if ($e['status'] !== 'pending') {
        throw new InvalidArgumentException($e['id'] . ' was already ' . $e['status'] . ($e['processed_at'] ? ' on ' . fmt_datetime($e['processed_at']) : '') . '.');
    }
    return $e;
}

/** "GCASH" / "BANK TRANSFER" for messages. */
function encash_method_label(string $method): string
{
    return ENCASH_METHODS[$method] ?? strtoupper(str_replace('_', ' ', $method));
}

/**
 * Release a pending request: the admin has sent the net amount. Records the
 * payout reference, completes the member's transaction and notifies them.
 */
function encash_release(array $admin, string $id, string $payoutRef, string $note): array
{
    $payoutRef = trim($payoutRef);
    if ($payoutRef === '') {
        throw new InvalidArgumentException('Enter the payout reference (the GCash, Maya or bank transaction number) after sending the money.');
    }
    $e = db_tx(function () use ($admin, $id, $payoutRef, $note) {
        $e = encash_lock_pending($id);
        db_run('UPDATE mlm_encashments SET status = "released", processed_at = NOW(), processed_by = ?, payout_reference = ?, admin_note = ? WHERE id = ?',
            [$admin['id'], $payoutRef, $note, $e['id']]);
        db_run('UPDATE mlm_transactions SET status = "completed", details = CONCAT(COALESCE(details, ""), ?)
                WHERE account_id = ? AND type = "encashment" AND direction = "out" AND reference_number = ? AND status = "pending"',
            [' Released ' . date('M j, Y') . ', payout reference ' . $payoutRef . '.', $e['account_id'], $e['reference_number']]);
        mlm_notify($e['account_id'], 'Encashment Released: ₱' . num($e['net_amount']) . ' PHP',
            'Your encashment of ₱' . num($e['amount']) . ' was released: ₱' . num($e['net_amount']) . ' sent via ' . encash_method_label($e['payout_method'])
            . ' to ' . $e['account_details'] . '. Payout reference: ' . $payoutRef . '.', 'encashment');
        return $e;
    });
    add_audit('Encashment ' . $e['id'] . ' released: ₱' . num($e['net_amount']) . ' net via ' . encash_method_label($e['payout_method']) . ' to ' . $e['account_details'] . ' (payout ref ' . $payoutRef . ')', $admin['id'], $e['account_id']);
    return db_one('SELECT * FROM mlm_encashments WHERE id = ?', [$e['id']]);
}

/**
 * Reject a pending request: the full amount goes back to the member's
 * available balance (a refund line in their transactions), with the reason.
 */
function encash_reject(array $admin, string $id, string $reason): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 3) {
        throw new InvalidArgumentException('Enter the reason for the member (for example, the account details are wrong).');
    }
    $e = db_tx(function () use ($admin, $id, $reason) {
        $e = encash_lock_pending($id);
        mlm_lock_account($e['account_id']);
        db_run('UPDATE mlm_accounts SET available_balance = available_balance + ?, total_encashment = GREATEST(0, total_encashment - ?) WHERE id = ?',
            [$e['amount'], $e['amount'], $e['account_id']]);
        db_run('UPDATE mlm_encashments SET status = "rejected", processed_at = NOW(), processed_by = ?, admin_note = ? WHERE id = ?',
            [$admin['id'], $reason, $e['id']]);
        db_run('UPDATE mlm_transactions SET status = "rejected", details = CONCAT(COALESCE(details, ""), ?)
                WHERE account_id = ? AND type = "encashment" AND direction = "out" AND reference_number = ? AND status = "pending"',
            [' Rejected: ' . $reason, $e['account_id'], $e['reference_number']]);
        mlm_add_tx($e['account_id'], 'encashment', 'Encashment Rejected: Refund', (float) $e['amount'], 'in', 'completed', $e['reference_number'] . '-R',
            'Returned to available balance. Reason: ' . $reason);
        mlm_notify($e['account_id'], 'Encashment Rejected: ₱' . num($e['amount']) . ' returned',
            'Your encashment request for ₱' . num($e['amount']) . ' was not released: ' . $reason . ' The full ₱' . num($e['amount']) . ' is back in your available balance.', 'encashment');
        return $e;
    });
    add_audit('Encashment ' . $e['id'] . ' rejected, ₱' . num($e['amount']) . ' returned to the balance: ' . $reason, $admin['id'], $e['account_id']);
    return db_one('SELECT * FROM mlm_encashments WHERE id = ?', [$e['id']]);
}

/** Request ids from a list of ticked boxes: unique, plausible, at most ENCASH_MAX_BULK. */
function encash_bulk_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn ($i) => is_string($i) ? trim($i) : '', $ids), static fn ($i) => $i !== '' && strlen($i) <= 40)));
    if (!$ids) {
        throw new InvalidArgumentException('Select at least one encashment request.');
    }
    if (count($ids) > ENCASH_MAX_BULK) {
        throw new InvalidArgumentException('Select at most ' . ENCASH_MAX_BULK . ' requests at a time (one page).');
    }
    return $ids;
}

/**
 * The outcome of a bulk action: ['done' => count, 'amount' => sum of $field,
 * 'skipped' => reasons]. Nothing done at all is an error (with the reasons).
 */
function encash_bulk_result(array $admin, string $verb, array $done, array $skipped, string $field): array
{
    if (!$done) {
        throw new InvalidArgumentException(implode(' ', $skipped) ?: 'Nothing was processed.');
    }
    $amount = array_sum(array_map(static fn ($e) => (float) $e[$field], $done));
    add_audit('Bulk ' . $verb . ' ' . count($done) . ' encashment request' . (count($done) === 1 ? '' : 's') . ' (₱' . num($amount) . ($field === 'net_amount' ? ' net' : '') . '): ' . implode(', ', array_column($done, 'id')), $admin['id']);
    return ['done' => count($done), 'amount' => $amount, 'skipped' => $skipped];
}

/**
 * Release several pending requests: $items is [['id', 'payoutReference'], …],
 * one reference each (they may be the same batch reference). Every reference
 * is checked before anything is released; a request processed meanwhile is
 * skipped and reported.
 */
function encash_bulk_release(array $admin, array $items, string $note): array
{
    $refs = [];
    foreach ($items as $it) {
        if (is_array($it) && is_string($it['id'] ?? null)) {
            $refs[trim($it['id'])] = mb_substr(trim((string) ($it['payoutReference'] ?? '')), 0, 100);
        }
    }
    encash_bulk_ids(array_keys($refs));
    foreach ($refs as $id => $ref) {
        if ($ref === '') {
            throw new InvalidArgumentException('Enter the payout reference for ' . $id . ', or one reference for all.');
        }
    }
    $done = [];
    $skipped = [];
    foreach ($refs as $id => $ref) {
        try {
            $done[] = encash_release($admin, (string) $id, $ref, $note);
        } catch (InvalidArgumentException $e) {
            $skipped[] = $e->getMessage();
        }
    }
    return encash_bulk_result($admin, 'released', $done, $skipped, 'net_amount');
}

/** Reject several pending requests with one reason; each amount goes back to its member's balance. */
function encash_bulk_reject(array $admin, array $ids, string $reason): array
{
    $ids = encash_bulk_ids($ids);
    if (mb_strlen(trim($reason)) < 3) {
        throw new InvalidArgumentException('Enter the reason for the members (for example, the account details are wrong).');
    }
    $done = [];
    $skipped = [];
    foreach ($ids as $id) {
        try {
            $done[] = encash_reject($admin, $id, $reason);
        } catch (InvalidArgumentException $e) {
            $skipped[] = $e->getMessage();
        }
    }
    return encash_bulk_result($admin, 'rejected', $done, $skipped, 'amount');
}

/**
 * Requests as CSV (for payout batches and accounting): every request
 * matching the filters, or only the ticked $ids (any status).
 */
function encash_export(array $admin, array $f, array $ids = []): array
{
    [$sql, $params] = encash_filter_sql($ids ? encash_filters([]) : $f);
    if ($ids) {
        $ids = encash_bulk_ids($ids);
        $sql .= ' AND e.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = array_merge($params, $ids);
    }
    $n = (int) db_value("SELECT COUNT(*) $sql", $params);
    if ($n === 0) {
        throw new InvalidArgumentException($ids ? 'The selected requests no longer exist.' : 'No encashment requests match these filters.');
    }
    if ($n > ENCASH_MAX_EXPORT) {
        throw new InvalidArgumentException('That is ' . number_format($n) . ' requests. Download at most ' . number_format(ENCASH_MAX_EXPORT) . ' at a time: narrow the filters.');
    }
    $rows = db_all('SELECT ' . ENCASH_COLUMNS . " $sql ORDER BY e.requested_at, e.id", $params);
    $money = static fn ($v) => number_format((float) $v, 2, '.', '');
    $lines = [csv_line(['Request ID', 'Reference', 'Requested', 'Head ID', 'Account Name', 'Username', 'Member', 'Member Email', 'Payout Channel', 'Account Details',
        'Gross', 'Tax', 'Fee', 'Net Payout', 'Status', 'Processed', 'Processed By', 'Payout Reference', 'Note / Reason'])];
    foreach ($rows as $r) {
        $lines[] = csv_line([
            $r['id'], $r['reference_number'], $r['requested_at'], $r['account_id'], $r['account_name'], $r['username'], (string) $r['member_name'], (string) $r['member_email'],
            encash_method_label($r['payout_method']), $r['account_details'],
            $money($r['amount']), $money($r['tax_deduction']), $money($r['processing_fee']), $money($r['net_amount']),
            ENCASH_STATUSES[$r['status']] ?? $r['status'], (string) $r['processed_at'], (string) $r['processed_by_name'], $r['payout_reference'], $r['admin_note'],
        ]);
    }
    add_audit('Downloaded ' . $n . ($ids ? ' selected' : '') . ' encashment request' . ($n === 1 ? '' : 's') . ' as CSV', $admin['id']);
    $label = $ids ? 'selected-' : ($f['status'] !== '' ? $f['status'] . '-' : '');
    return ['csv' => implode("\r\n", $lines) . "\r\n", 'count' => $n, 'filename' => 'encashments-' . $label . date('Y-m-d') . '.csv'];
}
