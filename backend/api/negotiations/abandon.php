<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$result = abandon_negotiation((int)($_POST['id_negotiation'] ?? 0), current_user_id());
finish_response($result['success'], $result['message'], 'mes-negociations.php');
