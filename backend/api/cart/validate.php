<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$result = validate_cart(current_user_id(), $_POST);
if ($result['success']) {
    finish_response(true, $result['message'], 'confirmation.php?id=' . (int)$result['order_id'], ['order_id' => (int)$result['order_id']]);
}
finish_response(false, $result['message'], 'paiement.php');
