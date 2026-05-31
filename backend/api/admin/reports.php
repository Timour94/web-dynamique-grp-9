<?php
require_once __DIR__ . '/../init.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ok = update_report_status((int)($_POST['id_report'] ?? 0), (string)($_POST['status'] ?? 'ouvert'), current_user_id());
    finish_response($ok, $ok ? 'Signalement mis à jour.' : 'Action impossible.', 'espace-admin.php');
}

json_response(['success' => true, 'reports' => get_reports()]);
