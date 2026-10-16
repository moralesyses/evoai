<?php
/**
 * Backoffice > Network: the genealogy tree loads deeper levels on click, and
 * the member chooses where sign-ups with their ID are placed
 * (assets/js/views/bo-network.js). Loaded by api.php.
 */
declare(strict_types=1);

return [
    // Auto / left / right for new members who sign up with the active head as sponsor.
    'mlm.setSignupPlacement' => static function (array $in): array {
        return mlm_set_signup_placement(api_account(api_user()), str_in($in, 'placement', 5));
    },

    // The two direct downlines of a member in the viewer's network.
    'mlm.genealogyChildren' => static function (array $in): array {
        $user = api_user();
        $id = str_in($in, 'id', 20);
        if ($id === '' || !mlm_can_view_node($user, $id)) {
            api_fail('That member isn\'t in your team.', 403);
        }
        return mlm_genealogy_children($id);
    },
];
