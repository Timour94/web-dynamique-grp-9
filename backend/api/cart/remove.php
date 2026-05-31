<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$ok = remove_cart_item(current_user_id(), (int)($_POST['id_cart_item'] ?? 0));
finish_response($ok, $ok ? 'Produit supprimé du panier.' : 'Impossible de supprimer cette ligne.', 'panier.php');
