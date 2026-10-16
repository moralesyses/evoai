<?php
/**
 * Superadmin actions for Management Admin > Activation Codes.
 * Loaded by api.php; every action requires the admin (Super Admin) role.
 */
declare(strict_types=1);

return [
    // Generate codes into a backoffice account's vault; the PINs are shown once here.
    'admin.codesGenerate' => static function (array $in): array {
        $admin = api_admin();
        $res = admin_generate_codes($admin, str_in($in, 'package', 40), int_in($in, 'quantity'), str_in($in, 'owner', 60), str_in($in, 'status', 20) ?: 'active');
        $n = count($res['codes']);
        return [
            'codes' => $res['codes'],
            'owner' => ['id' => $res['owner']['id'], 'username' => $res['owner']['username'], 'name' => $res['owner']['user_name']],
            'message' => 'Generated ' . $n . ' code' . ($n === 1 ? '' : 's') . ' for @' . $res['owner']['username'] . '.',
        ];
    },

    // Activate or deactivate one unused code.
    'admin.codeStatus' => static function (array $in): array {
        $admin = api_admin();
        $row = admin_set_code_status($admin, str_in($in, 'code', 40), str_in($in, 'status', 20));
        return ['status' => $row['status'], 'message' => $row['code'] . ' is now ' . strtolower(CODE_STATUSES[$row['status']]) . '.'];
    },

    // Activate or deactivate many codes: the selected ones, or every unused code matching the logs filters.
    'admin.codesStatus' => static function (array $in): array {
        $admin = api_admin();
        $status = str_in($in, 'status', 20);
        if (isset($in['filter']) && is_array($in['filter'])) {
            $f = $in['filter'];
            [$from, $to, $rangeError] = code_serial_range(str_in($f, 'from', 20), str_in($f, 'to', 20));
            if ($rangeError !== null) {
                api_fail($rangeError);
            }
            $codes = admin_matching_codes(str_in($f, 'q', 100), str_in($f, 'status', 20), str_in($f, 'package', 40), $from, $to);
            if (!$codes) {
                api_fail('No unused codes match these filters.');
            }
        } else {
            $codes = is_array($in['codes'] ?? null) ? array_filter($in['codes'], 'is_string') : [];
            if (count($codes) > CODES_MAX_SELECTION) {
                api_fail('Select at most ' . CODES_MAX_SELECTION . ' codes at a time, or use "Select all matching".');
            }
        }
        $r = admin_set_codes_status($admin, $codes, $status);
        $word = $status === 'active' ? 'Activated' : 'Deactivated';
        $msg = $word . ' ' . $r['changed'] . ' code' . ($r['changed'] === 1 ? '' : 's') . '.';
        if ($r['already']) {
            $msg .= ' ' . $r['already'] . ' ' . ($r['already'] === 1 ? 'was' : 'were') . ' already ' . ($status === 'active' ? 'active' : 'inactive') . '.';
        }
        if ($r['used']) {
            $msg .= ' ' . $r['used'] . ' used code' . ($r['used'] === 1 ? '' : 's') . ' can\'t change.';
        }
        if ($r['missing']) {
            $msg .= ' ' . $r['missing'] . ' no longer exist' . ($r['missing'] === 1 ? 's' : '') . '.';
        }
        return $r + ['message' => $msg];
    },

    // Download the selected codes, or every code matching the logs filters, as CSV.
    'admin.codesExport' => static function (array $in): array {
        $admin = api_admin();
        $filter = isset($in['filter']) && is_array($in['filter']) ? $in['filter'] : null;
        $codes = is_array($in['codes'] ?? null) ? array_filter($in['codes'], 'is_string') : [];
        $r = admin_export_codes($admin, $codes, $filter);
        return $r + ['message' => 'Downloaded ' . $r['count'] . ' code' . ($r['count'] === 1 ? '' : 's') . ' as CSV.'];
    },

    // Verify one code (and optionally its PIN).
    'admin.codeCheck' => static function (array $in): array {
        api_admin();
        $code = str_in($in, 'code', 40);
        if ($code === '') {
            api_fail('Enter the activation code to check.');
        }
        $pin = isset($in['pin']) && is_scalar($in['pin']) ? (string) $in['pin'] : null;
        return ['found' => ($info = admin_check_code($code, $pin)) !== null, 'code' => $info];
    },
];
