<?php
/**
 * Superadmin actions for portal users (Management Admin > Users).
 * Loaded by api.php; every action requires the admin (Super Admin) role.
 * The audit log records field names only, never the values.
 */
declare(strict_types=1);

return [
    // Open the editor for one user.
    'admin.user' => static function (array $in): array {
        $admin = api_admin();
        $user = admin_find_user(str_in($in, 'userId', 40));
        if (!$user) {
            api_fail('This user doesn\'t exist. It may have been deleted.', 404);
        }
        return ['user' => admin_user_for_editor($user, $admin)];
    },

    // Save only the changed fields, unless someone else changed the user meanwhile.
    'admin.userSave' => static function (array $in): array {
        $admin = api_admin();
        $userId = str_in($in, 'userId', 40);
        $stored = admin_find_user($userId);
        if (!$stored) {
            api_fail('This user doesn\'t exist. It may have been deleted.', 404);
        }
        [$changes, $errors] = admin_user_changes($stored, $in, $admin);
        if ($errors) {
            return ['saved' => false, 'errors' => $errors, 'message' => 'Nothing was saved. Please fix the highlighted fields.'];
        }
        if (!$changes) {
            return ['saved' => false, 'unchanged' => true, 'message' => 'Nothing was changed, so there was nothing to save.', 'user' => admin_user_for_editor($stored, $admin)];
        }
        if (!admin_save_user($userId, str_in($in, 'version', 30), $changes)) {
            $latest = admin_find_user($userId);
            return [
                'saved' => false,
                'conflict' => true,
                'message' => 'This account changed after you opened it (for example the user signed in or someone else saved it), so nothing was saved. These are the latest details; please make your changes again.',
                'user' => $latest ? admin_user_for_editor($latest, $admin) : null,
            ];
        }
        // Other sessions of this user end; a superadmin resetting their own password stays signed in.
        refresh_password_stamp($userId);
        $fields = array_map(fn ($c) => $c === 'password_hash' ? 'password' : $c, array_keys($changes));
        add_audit('User ' . $userId . ' edited (' . implode(', ', $fields) . ')', $admin['id']);
        return ['saved' => true, 'message' => 'Saved.', 'user' => admin_user_for_editor(admin_find_user($userId), $admin)];
    },

    // Permanently delete one user.
    'admin.userDelete' => static function (array $in): array {
        $admin = api_admin();
        $userId = str_in($in, 'userId', 40);
        $user = admin_find_user($userId);
        if (!$user) {
            api_fail('This user doesn\'t exist. It may have been deleted already.', 404);
        }
        if ($userId === $admin['id']) {
            api_fail('You cannot delete your own account.');
        }
        if ($userId === MASTER_PROFILE_ID) {
            api_fail('The founder\'s account can\'t be deleted: it holds the master 1EVOAI Card.');
        }
        try {
            $deleted = admin_delete_user($userId);
        } catch (PDOException $e) {
            // 1451: other tables still point at this user (foreign key).
            if (($e->errorInfo[1] ?? null) !== 1451) {
                throw $e;
            }
            api_fail($user['name'] . ' wasn\'t deleted because other records in the database still refer to this account.');
        }
        if ($deleted) {
            add_audit('User ' . $userId . ' deleted', $admin['id']);
        }
        return ['deleted' => $deleted, 'message' => 'Deleted ' . $user['name'] . ' (' . $user['email'] . ').'];
    },
];
