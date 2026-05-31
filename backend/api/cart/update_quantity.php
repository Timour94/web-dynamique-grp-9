<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$result = update_cart_item(current_user_id(), (int)($_POST['id_cart_item'] ?? 0), (int)($_POST['quantite'] ?? 1));
finish_response($result['success'], $result['message'], 'panier.php');
