<?php
function clean_string(?string $value, int $max = 255): string
{
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    return mb_substr($value, 0, $max);
}

function validate_required(string $value, string $label, array &$errors): void
{
    if (trim($value) === '') {
        $errors[] = "Le champ $label est obligatoire.";
    }
}

function validate_email_value(string $email, array &$errors): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse e-mail est invalide.";
    }
}

function validate_password_value(string $password, array &$errors): void
{
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une majuscule et un chiffre.';
    }
}

function validate_price_value($price, string $label, array &$errors): float
{
    $price = str_replace(',', '.', (string)$price);
    if (!is_numeric($price) || (float)$price < 0) {
        $errors[] = "Le champ $label doit être un montant positif.";
        return 0.0;
    }
    return round((float)$price, 2);
}

function validate_role_value(string $role, array &$errors): string
{
    $allowed = ['acheteur', 'vendeur'];
    if (!in_array($role, $allowed, true)) {
        $errors[] = 'Le rôle choisi est invalide.';
        return 'acheteur';
    }
    return $role;
}

function validate_product_type(string $type, array &$errors): string
{
    $allowed = ['achat_immediat', 'enchere', 'negociation', 'mixte'];
    if (!in_array($type, $allowed, true)) {
        $errors[] = 'Le type de vente est invalide.';
        return 'achat_immediat';
    }
    return $type;
}
