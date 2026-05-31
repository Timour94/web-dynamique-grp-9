<?php
require_once __DIR__ . '/../init.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ok = set_user_status((int)($_POST['id_user'] ?? 0), $_POST['status'] ?? 'actif', current_user_id());
    finish_response($ok, $ok ? 'Utilisateur mis à jour.' : 'Action impossible.', 'espace-admin.php');
}
json_response(['success' => true, 'users' => get_all_users()]);
