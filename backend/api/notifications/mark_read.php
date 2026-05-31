<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$ok = mark_notification_read((int)($_POST['id_notification'] ?? 0), current_user_id());
finish_response($ok, $ok ? 'Notification lue.' : 'Notification introuvable.', 'notifications.php');
