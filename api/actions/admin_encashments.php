<?php
/**
 * Superadmin actions for Management Admin > Encashments
 * (includes/encashments.php, assets/js/views/admin-encashments.js).
 * Loaded by api.php; every action requires the admin role.
 */
declare(strict_types=1);

/** " Skipped 2: …" for requests another admin processed first. */
function api_encash_skipped(array $skipped): string
{
    return $skipped ? ' Skipped ' . count($skipped) . ': ' . implode(' ', $skipped) : '';
}

return [
    // The money was sent: record the payout reference and complete the request.
    'admin.encashRelease' => static function (array $in): array {
        $e = encash_release(api_admin(), str_in($in, 'id', 40), str_in($in, 'payoutReference', 100), str_in($in, 'note', 255));
        return ['status' => $e['status'], 'message' => $e['id'] . ' released: ₱' . num($e['net_amount']) . ' to ' . $e['account_name'] . '.'];
    },

    // Not paid out: the amount goes back to the member's available balance.
    'admin.encashReject' => static function (array $in): array {
        $e = encash_reject(api_admin(), str_in($in, 'id', 40), str_in($in, 'reason', 255));
        return ['status' => $e['status'], 'message' => $e['id'] . ' rejected; ₱' . num($e['amount']) . ' returned to ' . $e['account_name'] . '.'];
    },

    // Several ticked requests at once, each with its payout reference.
    'admin.encashBulkRelease' => static function (array $in): array {
        $r = encash_bulk_release(api_admin(), is_array($in['items'] ?? null) ? $in['items'] : [], str_in($in, 'note', 255));
        return $r + ['message' => 'Released ' . $r['done'] . ' request' . ($r['done'] === 1 ? '' : 's') . ' (₱' . num($r['amount']) . ' net).' . api_encash_skipped($r['skipped'])];
    },

    // Several ticked requests at once, with one reason.
    'admin.encashBulkReject' => static function (array $in): array {
        $r = encash_bulk_reject(api_admin(), is_array($in['ids'] ?? null) ? $in['ids'] : [], str_in($in, 'reason', 255));
        return $r + ['message' => 'Rejected ' . $r['done'] . ' request' . ($r['done'] === 1 ? '' : 's') . '; ₱' . num($r['amount']) . ' returned to balances.' . api_encash_skipped($r['skipped'])];
    },

    // The ticked requests ($in['ids']), or every request matching the tab's filters, as CSV.
    'admin.encashExport' => static function (array $in): array {
        $ids = is_array($in['ids'] ?? null) ? $in['ids'] : [];
        return encash_export(api_admin(), encash_filters(is_array($in['filter'] ?? null) ? $in['filter'] : []), $ids);
    },
];
