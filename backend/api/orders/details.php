<?php
require_once __DIR__ . '/../init.php';
require_login();
$order = get_order_details((int)($_GET['id'] ?? 0), current_user_id());
if (!$order) { json_response(['success' => false, 'message' => 'Commande introuvable'], 404); }
json_response(['success' => true, 'order' => $order]);
