<?php
if (!defined('APP_LOADED')) {
    require_once __DIR__ . '/../backend/bootstrap.php';
}
$user = current_user();
$notifCount = $user ? unread_notifications_count((int)$user['id_user']) : 0;
$current = basename($_SERVER['SCRIPT_NAME']);
$cartItemsCount = $user ? count(get_cart_items((int)$user['id_user'])) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('frontend/images/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('frontend/css/style.css')) ?>?v=clean-v4">
    <link rel="stylesheet" href="<?= e(url('frontend/css/responsive.css')) ?>?v=clean-v4">
    <script defer src="<?= e(url('frontend/js/main.js')) ?>?v=live-v1"></script>
    <?php if ($user): ?><script defer src="<?= e(url('frontend/js/notifications.js')) ?>?v=live-v1"></script><?php endif; ?>
</head>
<body>
<div class="top-ribbon">
    <div class="container ribbon-inner">
        <span>Objets sélectionnés · Ventes sécurisées · Offres suivies</span>
        <span>Achat direct · Enchères · Négociation</span>
    </div>
</div>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(url('frontend/images/logo.svg')) ?>" alt="Mercato Nova">
            <span><strong>Mercato</strong><em>Nova</em></span>
        </a>
        <button class="menu-toggle" type="button" aria-label="Ouvrir le menu">Menu</button>
        <nav class="main-nav" aria-label="Navigation principale">
            <a class="<?= $current === 'index.php' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">Accueil</a>
            <a class="<?= $current === 'catalogue.php' ? 'active' : '' ?>" href="<?= e(url('catalogue.php')) ?>">Catalogue</a>
            <a class="<?= in_array($current, ['encheres.php','enchere.php'], true) ? 'active' : '' ?>" href="<?= e(url('encheres.php')) ?>">Enchères</a>
            <a class="<?= in_array($current, ['negociations.php','negotiation.php'], true) ? 'active' : '' ?>" href="<?= e(url('negociations.php')) ?>">Négociations</a>
            <?php if ($user && has_role(['vendeur','admin'])): ?>
                <a class="<?= $current === 'espace-vendeur.php' ? 'active' : '' ?>" href="<?= e(url('espace-vendeur.php')) ?>">Vendre</a>
            <?php endif; ?>
            <?php if ($user): ?>
                <a class="nav-icon" href="<?= e(url('panier.php')) ?>">Panier <span class="badge soft"><?= $cartItemsCount ?></span></a>
                <a class="nav-icon" href="<?= e(url('notifications.php')) ?>">Notifications <span class="badge" id="notification-count"><?= $notifCount ?></span></a>
                <a class="<?= $current === 'mes-favoris.php' ? 'active' : '' ?>" href="<?= e(url('mes-favoris.php')) ?>">Favoris</a>
                <a class="<?= $current === 'profil.php' ? 'active' : '' ?>" href="<?= e(url('profil.php')) ?>">Profil</a>
                <a class="<?= str_starts_with($current, 'espace') ? 'active' : '' ?>" href="<?= e(url(has_role('admin') ? 'espace-admin.php' : (has_role('vendeur') ? 'espace-vendeur.php' : 'espace-client.php'))) ?>">Mon espace</a>
                <a href="<?= e(url('deconnexion.php')) ?>">Déconnexion</a>
            <?php else: ?>
                <a class="<?= $current === 'connexion.php' ? 'active' : '' ?>" href="<?= e(url('connexion.php')) ?>">Connexion</a>
                <a class="btn small" href="<?= e(url('inscription.php')) ?>">Inscription</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container main-content">
<?php foreach (get_flashes() as $flash): ?>
    <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
