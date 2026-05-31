<?php
require_once __DIR__ . '/../init.php';
require_role(['vendeur','admin']);
verify_csrf();
$product = get_product_by_id((int)($_POST['id_product'] ?? 0));
if (!$product || !can_manage_product($product)) { finish_response(false, 'Accès refusé.', 'espace-vendeur.php'); }
getDB()->prepare('UPDATE products SET statut = "archive", date_modification = NOW() WHERE id_product = ?')->execute([(int)$product['id_product']]);
finish_response(true, 'Annonce archivée.', 'espace-vendeur.php');
