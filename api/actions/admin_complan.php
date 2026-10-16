<?php
/**
 * Superadmin action for Management Admin > Binary Complan.
 * Loaded by api.php; requires the admin (Super Admin) role.
 */
declare(strict_types=1);

return [
    // Save the direct referral bonus, pairing bonus, pairs per cycle and cycles per day.
    'admin.complanSave' => static function (array $in): array {
        $admin = api_admin();
        [$plan, $errors] = mlm_save_complan($admin, $in);
        if ($errors) {
            return ['saved' => false, 'errors' => $errors, 'message' => 'Nothing was saved. Please fix the highlighted fields.'];
        }
        return [
            'saved' => true,
            'plan' => $plan,
            'message' => sprintf('Rewards plan saved: ₱%s referral bonus, ₱%s per match, %d matches per day (max ₱%s/day).',
                num($plan['directReferralBonus']), num($plan['pairingBonus']), $plan['pairsPerDay'], num($plan['maxDailyPairingIncome'])),
        ];
    },
];
