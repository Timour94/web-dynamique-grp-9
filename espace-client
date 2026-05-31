<?php
require_once __DIR__ . '/backend/bootstrap.php';
require_login();
$pageTitle = 'Mon espace - Mercato Nova';
$user = current_user();
$orders = get_user_orders(current_user_id());
$bids = get_user_bids(current_user_id());
$negs = get_user_negotiations(current_user_id());
require_once __DIR__ . '/partials/header.php';
?>

<section class="page-title"><div><p class="eyebrow">Espace acheteur</p><h1>Bonjour <?= e($user['prenom']) ?></h1><p>Votre tableau de bord personnel : achats, enchères, négociations et notifications.</p></div><a class="btn" href="<?= e(url('catalogue.php')) ?>">Explorer</a></section>
<div class="dashboard-grid">
    <a class="dashboard-card" href="mes-achats.php"><strong><?= count($orders) ?></strong><span>Achats</span></a>
    <a class="dashboard-card" href="mes-encheres.php"><strong><?= count($bids) ?></strong><span>Offres d’enchères</span></a>
    <a class="dashboard-card" href="mes-negociations.php"><strong><?= count($negs) ?></strong><span>Négociations</span></a>
    <a class="dashboard-card" href="notifications.php"><strong><?= unread_notifications_count(current_user_id()) ?></strong><span>Notifications non lues</span></a>
</div>
<section class="section-head"><div><p class="eyebrow">Actions rapides</p><h2>Continuer votre parcours</h2></div></section>
<div class="how-grid"><a class="how-card" href="catalogue.php?type_vente=achat_immediat"><b>1</b><h3>Achat immédiat</h3><p class="muted">Produits disponibles en validation directe.</p></a><a class="how-card" href="encheres.php"><b>2</b><h3>Enchères</h3><p class="muted">Suivez les ventes avec historique des offres.</p></a><a class="how-card" href="negociations.php"><b>3</b><h3>Négociations</h3><p class="muted">Proposez un prix et échangez avec les vendeurs.</p></a></div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
