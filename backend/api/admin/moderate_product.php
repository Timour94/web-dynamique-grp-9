<?php
require_once __DIR__ . '/../init.php';
require_role('admin');
verify_csrf();
$ok = moderate_product((int)($_POST['id_product'] ?? 0), $_POST['action'] ?? 'refuse', current_user_id());
finish_response($ok, $ok ? 'Annonce modérée.' : 'Action impossible.', 'espace-admin.php');
