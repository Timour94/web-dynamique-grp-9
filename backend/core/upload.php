<?php
function handle_product_image_upload(string $fieldName = 'image'): ?string
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Erreur pendant l’envoi de l’image.');
        return null;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($_FILES[$fieldName]['tmp_name']);
    if (!isset($allowed[$mime])) {
        flash('error', 'Format d’image non autorisé. Utilise JPG, PNG, WEBP ou GIF.');
        return null;
    }

    $dir = dirname(__DIR__, 2) . '/frontend/images/produits';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'produit_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $destination = $dir . '/' . $filename;
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $destination)) {
        flash('error', 'Impossible d’enregistrer l’image.');
        return null;
    }
    return 'frontend/images/produits/' . $filename;
}
