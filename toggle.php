<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$productId = (int)($_POST['id_product'] ?? 0);
$result = toggle_favorite(current_user_id(), $productId);
$return = $_POST['return'] ?? 'catalogue.php';
finish_response($result['success'], $result['message'], $return, ['is_favorite' => $result['is_favorite'] ?? false]);
