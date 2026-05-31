<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$result = add_to_cart(current_user_id(), (int)($_POST['id_product'] ?? 0), max(1, (int)($_POST['quantite'] ?? 1)));
finish_response($result['success'], $result['message'], $result['success'] ? 'panier.php' : 'catalogue.php');
