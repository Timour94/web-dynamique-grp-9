<?php
function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Vous devez être connecté pour accéder à cette page.');
        redirect('connexion.php');
    }
}

function has_role(string|array $roles): bool
{
    $roles = (array)$roles;
    return in_array(user_role(), $roles, true);
}

function require_role(string|array $roles): void
{
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        exit('Accès refusé : vous n’avez pas les permissions nécessaires.');
    }
}

function is_admin(): bool
{
    return has_role('admin');
}

function can_manage_product(array $product): bool
{
    return is_admin() || ((int)$product['id_vendeur'] === current_user_id());
}
