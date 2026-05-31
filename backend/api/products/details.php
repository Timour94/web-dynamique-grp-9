<?php
require_once __DIR__ . '/../init.php';
$product = get_product_by_id((int)($_GET['id'] ?? 0));
if (!$product) { json_response(['success' => false, 'message' => 'Produit introuvable'], 404); }
json_response(['success' => true, 'product' => $product]);
