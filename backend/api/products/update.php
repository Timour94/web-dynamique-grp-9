<?php
require_once __DIR__ . '/../init.php';
require_role(['vendeur','admin']);
verify_csrf();
$images = collect_product_uploaded_images();
$result = update_product((int)($_POST['id_product'] ?? 0), $_POST, current_user_id(), $images);
finish_response($result['success'], $result['success'] ? 'Annonce modifiée.' : implode(' ', $result['errors']), 'espace-vendeur.php');
