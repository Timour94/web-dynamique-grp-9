<?php
require_once __DIR__ . '/../init.php';
$filters = [
    'q' => $_GET['q'] ?? '',
    'categorie' => $_GET['categorie'] ?? '',
    'type_vente' => $_GET['type_vente'] ?? '',
    'prix_min' => $_GET['prix_min'] ?? '',
    'prix_max' => $_GET['prix_max'] ?? '',
    'sort' => $_GET['sort'] ?? 'recent',
    'limit' => $_GET['limit'] ?? 100,
];
$products = get_products($filters);
foreach ($products as &$p) {
    $p['image_url'] = url($p['image'] ?: DEFAULT_PRODUCT_IMAGE);
    $p['details_url'] = url('produit.php?id=' . (int)$p['id_product']);
    $p['auction_url'] = url('enchere.php?id=' . (int)$p['id_product']);
    $p['has_auction'] = !empty($p['id_auction']);
    $p['negotiation_url'] = url('negotiation.php?product_id=' . (int)$p['id_product']);
    $p['display_price'] = money_format_fr((float)($p['prix_achat_immediat'] ?: ($p['prix_actuel'] ?? $p['prix_initial'])));
}
json_response(['success' => true, 'products' => $products]);
