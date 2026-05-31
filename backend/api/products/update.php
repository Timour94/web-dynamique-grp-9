<?php
require_once __DIR__ . '/../init.php';
require_role(['vendeur','admin']);
verify_csrf();
$image = handle_product_image_upload('image');
$result = update_product((int)($_POST['id_product'] ?? 0), $_POST, current_user_id(), $image);
finish_response($result['success'], $result['success'] ? 'Annonce modifiée.' : implode(' ', $result['errors']), 'espace-vendeur.php');
