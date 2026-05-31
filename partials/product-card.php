<?php
$image = $product['image'] ?: DEFAULT_PRODUCT_IMAGE;
$price = $product['prix_achat_immediat'] ?: ($product['prix_actuel'] ?? $product['prix_initial']);
$typeLabels = [
    'achat_immediat' => 'Achat immédiat',
    'enchere' => 'Enchère',
    'negociation' => 'Négociation',
    'mixte' => 'Mixte'
];
$type = $product['type_vente'];
$canBuy = in_array($type, ['achat_immediat', 'mixte'], true);
$canBid = in_array($type, ['enchere', 'mixte'], true) && !empty($product['id_auction']);
$canNegotiate = in_array($type, ['negociation', 'mixte'], true);
?>
<article class="product-card">
    <div class="product-media">
        <a href="<?= e(url('produit.php?id=' . (int)$product['id_product'])) ?>">
            <img loading="lazy" src="<?= e(url($image)) ?>" alt="<?= e($product['titre']) ?>">
        </a>
        <span class="product-badge badge-<?= e($type) ?>"><?= e($typeLabels[$type] ?? $type) ?></span>
        <?php if (is_logged_in() && (int)($product['id_vendeur'] ?? 0) !== current_user_id()): ?>
            <?php $isFav = is_favorite(current_user_id(), (int)$product['id_product']); ?>
            <form class="favorite-form" action="<?= e(url('backend/api/favorites/toggle.php')) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id_product" value="<?= (int)$product['id_product'] ?>">
                <input type="hidden" name="return" value="<?= e(basename($_SERVER['REQUEST_URI'] ?? 'catalogue.php')) ?>">
                <button class="favorite-button <?= $isFav ? 'active' : '' ?>" type="submit" title="<?= $isFav ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>"><?= $isFav ? '♥' : '♡' ?></button>
            </form>
        <?php endif; ?>
    </div>
    <div class="product-body">
        <p class="muted"><?= e($product['nom_categorie'] ?? '') ?> · <?= e($product['etat_produit'] ?? 'Sélection vérifiée') ?></p>
        <h3><a href="<?= e(url('produit.php?id=' . (int)$product['id_product'])) ?>"><?= e($product['titre']) ?></a></h3>
        <p class="price"><?= money_format_fr((float)$price) ?></p>
        <p class="muted">Vendeur : <?= e(trim(($product['vendeur_prenom'] ?? '') . ' ' . ($product['vendeur_nom'] ?? ''))) ?></p>
        <div class="card-actions">
            <a class="btn secondary" href="<?= e(url('produit.php?id=' . (int)$product['id_product'])) ?>">Voir</a>
            <?php if ($canBuy): ?>
                <form action="<?= e(url('backend/api/cart/add.php')) ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_product" value="<?= (int)$product['id_product'] ?>">
                    <button class="btn" type="submit">Panier</button>
                </form>
            <?php endif; ?>
            <?php if ($canBid): ?>
                <a class="btn" href="<?= e(url('enchere.php?id=' . (int)$product['id_product'])) ?>">Enchérir</a>
            <?php endif; ?>
            <?php if ($canNegotiate): ?>
                <a class="btn secondary" href="<?= e(url('negotiation.php?product_id=' . (int)$product['id_product'])) ?>">Négocier</a>
            <?php endif; ?>
        </div>
    </div>
</article>
