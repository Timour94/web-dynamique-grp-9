<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$result = place_bid((int)($_POST['id_auction'] ?? 0), current_user_id(), (float)str_replace(',', '.', $_POST['montant'] ?? 0));
finish_response($result['success'], $result['message'], 'mes-encheres.php');
