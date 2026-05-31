<?php

function normalize_upload_files_array(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [$files];
    }

    $normalized = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }

    return $normalized;
}

function detect_uploaded_image_mime(string $tmpPath): ?string
{
    $info = @getimagesize($tmpPath);
    if (is_array($info) && !empty($info['mime'])) {
        return strtolower((string)$info['mime']);
    }

    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $tmpPath);
            @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmpPath);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }
    }

    return null;
}

function image_extension_from_upload(array $file, ?string $mime): ?string
{
    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if ($mime && isset($allowedMime[$mime])) {
        return $allowedMime[$mime];
    }

    $originalName = strtolower((string)($file['name'] ?? ''));
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (in_array($ext, $allowedExt, true)) {
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    return null;
}

function get_product_images_directory(): array
{
    $root = dirname(__DIR__, 2);
    $relativeDir = 'frontend/images/produits';
    $absoluteDir = $root . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'produits';

    if (!is_dir($absoluteDir)) {
        @mkdir($absoluteDir, 0777, true);
    }

    if (is_dir($absoluteDir)) {
        @chmod($absoluteDir, 0777);
    }

    return [$absoluteDir, $relativeDir];
}

function directory_accepts_write(string $absoluteDir): bool
{
    if (!is_dir($absoluteDir)) {
        return false;
    }

    // Sur Windows/MAMP, is_writable() peut parfois être trompeur.
    // On teste donc réellement l'écriture avec un petit fichier temporaire.
    $testFile = rtrim($absoluteDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.write_test_' . uniqid('', true) . '.tmp';
    $ok = @file_put_contents($testFile, 'test') !== false;

    if ($ok) {
        @unlink($testFile);
        return true;
    }

    return false;
}

function save_product_uploaded_file(array $file): ?string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Image trop lourde pour la configuration PHP.',
            UPLOAD_ERR_FORM_SIZE => 'Image trop lourde pour le formulaire.',
            UPLOAD_ERR_PARTIAL => 'L’image n’a été envoyée que partiellement.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire PHP manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d’écrire le fichier sur le disque.',
            UPLOAD_ERR_EXTENSION => 'L’envoi a été bloqué par une extension PHP.',
        ];
        flash('error', $messages[$error] ?? 'Erreur pendant l’envoi de l’image.');
        return null;
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_file($tmpPath)) {
        flash('error', 'Fichier temporaire introuvable pendant l’envoi de l’image.');
        return null;
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        flash('error', 'L’image envoyée semble vide.');
        return null;
    }

    if ($size > 8 * 1024 * 1024) {
        flash('error', 'Image trop lourde : 8 Mo maximum par fichier.');
        return null;
    }

    $mime = detect_uploaded_image_mime($tmpPath);
    $extension = image_extension_from_upload($file, $mime);
    if (!$extension) {
        flash('error', 'Format d’image non autorisé. Utilise JPG, PNG, WEBP ou GIF.');
        return null;
    }

    [$absoluteDir, $relativeDir] = get_product_images_directory();

    if (!is_dir($absoluteDir)) {
        flash('error', 'Impossible de créer le dossier images produits : ' . $absoluteDir);
        return null;
    }

    if (!directory_accepts_write($absoluteDir)) {
        flash('error', 'Le dossier images produits n’est pas accessible en écriture : ' . $absoluteDir . '. Mets ce dossier en lecture/écriture ou déplace le projet dans C:\\MAMP\\htdocs.');
        return null;
    }

    try {
        $filename = 'produit_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    } catch (Throwable $e) {
        $filename = 'produit_' . date('Ymd_His') . '_' . str_replace('.', '', uniqid('', true)) . '.' . $extension;
    }

    $destination = rtrim($absoluteDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    $moved = false;
    if (is_uploaded_file($tmpPath)) {
        $moved = @move_uploaded_file($tmpPath, $destination);
    }

    if (!$moved) {
        $moved = @copy($tmpPath, $destination);
    }

    if (!$moved || !is_file($destination)) {
        flash('error', 'Impossible d’enregistrer l’image dans : ' . $destination);
        return null;
    }

    @chmod($destination, 0666);

    return $relativeDir . '/' . $filename;
}

function handle_product_image_upload(string $fieldName = 'image'): ?string
{
    if (empty($_FILES[$fieldName])) {
        return null;
    }

    $files = normalize_upload_files_array($_FILES[$fieldName]);
    foreach ($files as $file) {
        $path = save_product_uploaded_file($file);
        if ($path) {
            return $path;
        }
    }

    return null;
}

function handle_multiple_product_image_uploads(string $fieldName = 'images'): array
{
    if (empty($_FILES[$fieldName])) {
        return [];
    }

    $paths = [];
    foreach (normalize_upload_files_array($_FILES[$fieldName]) as $file) {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $path = save_product_uploaded_file($file);
        if ($path) {
            $paths[] = $path;
        }
    }

    return $paths;
}

function collect_product_uploaded_images(): array
{
    $paths = handle_multiple_product_image_uploads('images');

    // Compatibilité avec l’ancien formulaire qui utilisait name="image".
    $single = handle_product_image_upload('image');
    if ($single) {
        array_unshift($paths, $single);
    }

    return array_values(array_unique(array_filter($paths)));
}
