<?php
require_once __DIR__ . '/../init.php';
require_role(['vendeur','admin']);
verify_csrf();
$image = handle_product_image_upload('image');
$result = create_product($_POST, current_user_id(), $image);
finish_response($result['success'], $result['success'] ? 'Annonce créée.' : implode(' ', $result['errors']), 'espace-vendeur.php');
