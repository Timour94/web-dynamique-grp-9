<?php
function get_role_id(string $role): int
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id_role FROM roles WHERE nom_role = ? LIMIT 1');
    $stmt->execute([$role]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        throw new RuntimeException('Rôle introuvable : ' . $role);
    }
    return (int)$id;
}

function get_user_by_id(int $id): ?array
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT u.*, r.nom_role FROM users u JOIN roles r ON r.id_role = u.id_role WHERE u.id_user = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function get_categories(): array
{
    return getDB()->query('SELECT * FROM categories ORDER BY nom_categorie')->fetchAll();
}

function get_main_image_sql(): string
{
    return '(SELECT chemin_image FROM product_images pi WHERE pi.id_product = p.id_product ORDER BY image_principale DESC, id_image ASC LIMIT 1)';
}

function get_products(array $filters = []): array
{
    close_ended_auctions();
    $pdo = getDB();
    $where = ['p.statut = "publie"'];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(p.titre LIKE ? OR p.description LIKE ?)';
        $q = '%' . $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
    }
    if (!empty($filters['categorie'])) {
        $where[] = 'p.id_categorie = ?';
        $params[] = (int)$filters['categorie'];
    }
    if (!empty($filters['type_vente'])) {
        $typeFilter = (string)$filters['type_vente'];
        if ($typeFilter === 'enchere') {
            // La page Enchères doit aussi afficher les ventes mixtes qui possèdent une enchère active.
            $where[] = 'p.type_vente IN ("enchere", "mixte") AND a.id_auction IS NOT NULL';
        } elseif ($typeFilter === 'negociation') {
            // La page Négociations doit aussi afficher les ventes mixtes négociables.
            $where[] = 'p.type_vente IN ("negociation", "mixte")';
        } elseif ($typeFilter === 'achat_immediat') {
            $where[] = 'p.type_vente IN ("achat_immediat", "mixte")';
        } else {
            $where[] = 'p.type_vente = ?';
            $params[] = $typeFilter;
        }
    }
    if (isset($filters['prix_min']) && $filters['prix_min'] !== '') {
        $where[] = 'COALESCE(p.prix_achat_immediat, a.prix_actuel, p.prix_initial) >= ?';
        $params[] = (float)$filters['prix_min'];
    }
    if (isset($filters['prix_max']) && $filters['prix_max'] !== '') {
        $where[] = 'COALESCE(p.prix_achat_immediat, a.prix_actuel, p.prix_initial) <= ?';
        $params[] = (float)$filters['prix_max'];
    }

    $sort = $filters['sort'] ?? 'recent';
    $order = match ($sort) {
        'prix_asc' => 'COALESCE(p.prix_achat_immediat, a.prix_actuel, p.prix_initial) ASC',
        'prix_desc' => 'COALESCE(p.prix_achat_immediat, a.prix_actuel, p.prix_initial) DESC',
        'enchere_fin' => 'a.date_fin ASC',
        default => 'p.date_creation DESC',
    };

    $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 100;
    $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;

    $sql = 'SELECT p.*, c.nom_categorie, u.prenom AS vendeur_prenom, u.nom AS vendeur_nom, '
        . get_main_image_sql() . ' AS image, a.id_auction, a.prix_actuel, a.date_fin, a.statut_enchere '
        . 'FROM products p '
        . 'JOIN categories c ON c.id_categorie = p.id_categorie '
        . 'JOIN users u ON u.id_user = p.id_vendeur '
        . 'LEFT JOIN auctions a ON a.id_product = p.id_product AND a.statut_enchere = "en_cours" '
        . 'WHERE ' . implode(' AND ', $where) . ' '
        . 'ORDER BY ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_product_by_id(int $id): ?array
{
    close_ended_auctions();
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT p.*, c.nom_categorie, u.prenom AS vendeur_prenom, u.nom AS vendeur_nom, u.email AS vendeur_email, '
        . get_main_image_sql() . ' AS image, a.id_auction, a.prix_depart, a.prix_actuel, a.date_debut, a.date_fin, a.statut_enchere, a.id_gagnant '
        . 'FROM products p '
        . 'JOIN categories c ON c.id_categorie = p.id_categorie '
        . 'JOIN users u ON u.id_user = p.id_vendeur '
        . 'LEFT JOIN auctions a ON a.id_product = p.id_product '
        . 'WHERE p.id_product = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function get_product_images(int $productId): array
{
    $stmt = getDB()->prepare('SELECT * FROM product_images WHERE id_product = ? ORDER BY image_principale DESC, id_image ASC');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function create_product(array $data, int $sellerId, string|array|null $imagePaths = null): array
{
    $pdo = getDB();
    $errors = [];
    $titre = clean_string($data['titre'] ?? '', 180);
    $description = trim((string)($data['description'] ?? ''));
    $idCategorie = (int)($data['id_categorie'] ?? 0);
    $typeVente = validate_product_type((string)($data['type_vente'] ?? 'achat_immediat'), $errors);
    $etat = clean_string($data['etat_produit'] ?? 'Bon état', 80);
    $stock = max(1, (int)($data['stock'] ?? 1));
    $prixInitial = validate_price_value($data['prix_initial'] ?? 0, 'prix initial', $errors);
    $prixAchatRaw = $data['prix_achat_immediat'] ?? '';
    $prixAchat = $prixAchatRaw !== '' ? validate_price_value($prixAchatRaw, 'prix achat immédiat', $errors) : null;
    $dateFin = trim((string)($data['date_fin_enchere'] ?? ''));

    validate_required($titre, 'titre', $errors);
    validate_required($description, 'description', $errors);
    if ($idCategorie <= 0) {
        $errors[] = 'La catégorie est obligatoire.';
    }
    if (in_array($typeVente, ['achat_immediat', 'mixte'], true) && ($prixAchat === null || $prixAchat <= 0)) {
        $errors[] = 'Le prix d’achat immédiat est obligatoire pour ce type de vente.';
    }
    if (in_array($typeVente, ['enchere', 'mixte'], true) && $dateFin === '') {
        $errors[] = 'La date de fin d’enchère est obligatoire.';
    }
    if (in_array($typeVente, ['enchere', 'mixte'], true) && $dateFin !== '' && strtotime($dateFin) <= time()) {
        $errors[] = 'La date de fin d’enchère doit être dans le futur.';
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    if (is_string($imagePaths) && $imagePaths !== '') {
        $imagePaths = [$imagePaths];
    } elseif (!is_array($imagePaths)) {
        $imagePaths = [];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO products (id_vendeur, id_categorie, titre, description, prix_initial, prix_achat_immediat, type_vente, etat_produit, stock, statut, date_creation, date_modification) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "en_attente", NOW(), NOW())');
        $stmt->execute([$sellerId, $idCategorie, $titre, $description, $prixInitial, $prixAchat, $typeVente, $etat, $stock]);
        $productId = (int)$pdo->lastInsertId();

        foreach ($imagePaths as $index => $imagePath) {
            if ($imagePath) {
                $img = $pdo->prepare('INSERT INTO product_images (id_product, chemin_image, image_principale) VALUES (?, ?, ?)');
                $img->execute([$productId, $imagePath, $index === 0 ? 1 : 0]);
            }
        }

        if (in_array($typeVente, ['enchere', 'mixte'], true)) {
            $stmt = $pdo->prepare('INSERT INTO auctions (id_product, prix_depart, prix_actuel, date_debut, date_fin, statut_enchere) VALUES (?, ?, ?, NOW(), ?, "en_cours")');
            $stmt->execute([$productId, $prixInitial, $prixInitial, $dateFin]);
        }

        $admins = get_admin_ids();
        foreach ($admins as $adminId) {
            add_notification((int)$adminId, 'Nouvelle annonce à modérer', 'Une annonce attend validation : ' . $titre, 'moderation', 'espace-admin.php');
        }
        $pdo->commit();
        return ['success' => true, 'id_product' => $productId, 'errors' => []];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['success' => false, 'errors' => ['Erreur lors de la création de l’annonce : ' . $e->getMessage()]];
    }
}

function update_product(int $productId, array $data, int $sellerId, string|array|null $imagePaths = null): array
{
    $product = get_product_by_id($productId);
    if (!$product || !can_manage_product($product)) {
        return ['success' => false, 'errors' => ['Annonce introuvable ou accès refusé.']];
    }
    if ($product['statut'] === 'vendu') {
        return ['success' => false, 'errors' => ['Impossible de modifier une annonce déjà vendue.']];
    }

    $errors = [];
    $titre = clean_string($data['titre'] ?? '', 180);
    $description = trim((string)($data['description'] ?? ''));
    $idCategorie = (int)($data['id_categorie'] ?? 0);
    $typeVente = validate_product_type((string)($data['type_vente'] ?? $product['type_vente']), $errors);
    $prixInitial = validate_price_value($data['prix_initial'] ?? $product['prix_initial'], 'prix initial', $errors);
    $prixAchatRaw = $data['prix_achat_immediat'] ?? '';
    $prixAchat = $prixAchatRaw !== '' ? validate_price_value($prixAchatRaw, 'prix achat immédiat', $errors) : null;
    $etat = clean_string($data['etat_produit'] ?? 'Bon état', 80);
    $stock = max(1, (int)($data['stock'] ?? 1));
    $dateFin = trim((string)($data['date_fin_enchere'] ?? ''));

    validate_required($titre, 'titre', $errors);
    validate_required($description, 'description', $errors);
    if ($idCategorie <= 0) {
        $errors[] = 'La catégorie est obligatoire.';
    }
    if (in_array($typeVente, ['achat_immediat', 'mixte'], true) && ($prixAchat === null || $prixAchat <= 0)) {
        $errors[] = 'Le prix d’achat immédiat est obligatoire pour ce type de vente.';
    }
    if (in_array($typeVente, ['enchere', 'mixte'], true) && $dateFin === '') {
        $errors[] = 'La date de fin d’enchère est obligatoire pour ce type de vente.';
    }
    if (in_array($typeVente, ['enchere', 'mixte'], true) && $dateFin !== '' && strtotime($dateFin) <= time()) {
        $errors[] = 'La date de fin d’enchère doit être dans le futur.';
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    if (is_string($imagePaths) && $imagePaths !== '') {
        $imagePaths = [$imagePaths];
    } elseif (!is_array($imagePaths)) {
        $imagePaths = [];
    }

    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE products SET id_categorie = ?, titre = ?, description = ?, prix_initial = ?, prix_achat_immediat = ?, type_vente = ?, etat_produit = ?, stock = ?, statut = IF(statut = "refuse", "en_attente", statut), date_modification = NOW() WHERE id_product = ?');
        $stmt->execute([$idCategorie, $titre, $description, $prixInitial, $prixAchat, $typeVente, $etat, $stock, $productId]);

        update_product_gallery($productId, $data, $imagePaths);

        if (in_array($typeVente, ['enchere', 'mixte'], true)) {
            $auctionStmt = $pdo->prepare('SELECT * FROM auctions WHERE id_product = ? LIMIT 1');
            $auctionStmt->execute([$productId]);
            $auction = $auctionStmt->fetch();

            if ($auction) {
                $bidCountStmt = $pdo->prepare('SELECT COUNT(*) FROM bids WHERE id_auction = ?');
                $bidCountStmt->execute([(int)$auction['id_auction']]);
                $bidCount = (int)$bidCountStmt->fetchColumn();
                $prixActuel = $bidCount > 0 ? max((float)$auction['prix_actuel'], $prixInitial) : $prixInitial;

                $pdo->prepare('UPDATE auctions SET prix_depart = ?, prix_actuel = ?, date_fin = ?, statut_enchere = IF(statut_enchere = "annulee", "en_cours", statut_enchere) WHERE id_auction = ?')
                    ->execute([$prixInitial, $prixActuel, $dateFin, (int)$auction['id_auction']]);
            } else {
                $pdo->prepare('INSERT INTO auctions (id_product, prix_depart, prix_actuel, date_debut, date_fin, statut_enchere) VALUES (?, ?, ?, NOW(), ?, "en_cours")')
                    ->execute([$productId, $prixInitial, $prixInitial, $dateFin]);
            }
        } else {
            $pdo->prepare('UPDATE auctions SET statut_enchere = "annulee" WHERE id_product = ? AND statut_enchere = "en_cours"')->execute([$productId]);
        }

        $pdo->commit();
        return ['success' => true, 'errors' => []];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['success' => false, 'errors' => ['Erreur lors de la modification : ' . $e->getMessage()]];
    }
}

function get_user_products(int $sellerId): array
{
    $stmt = getDB()->prepare('SELECT p.*, c.nom_categorie, ' . get_main_image_sql() . ' AS image FROM products p JOIN categories c ON c.id_categorie = p.id_categorie WHERE p.id_vendeur = ? ORDER BY p.date_creation DESC');
    $stmt->execute([$sellerId]);
    return $stmt->fetchAll();
}

function get_pending_products(): array
{
    return getDB()->query('SELECT p.*, u.prenom, u.nom, c.nom_categorie, ' . get_main_image_sql() . ' AS image FROM products p JOIN users u ON u.id_user = p.id_vendeur JOIN categories c ON c.id_categorie = p.id_categorie WHERE p.statut = "en_attente" ORDER BY p.date_creation ASC')->fetchAll();
}

function moderate_product(int $productId, string $action, int $adminId): bool
{
    $product = get_product_by_id($productId);
    if (!$product) {
        return false;
    }
    $status = $action === 'accept' ? 'publie' : 'refuse';
    $pdo = getDB();
    $pdo->prepare('UPDATE products SET statut = ?, date_modification = NOW() WHERE id_product = ?')->execute([$status, $productId]);
    $titre = $status === 'publie' ? 'Annonce validée' : 'Annonce refusée';
    $message = $status === 'publie' ? 'Votre annonce est maintenant visible dans le catalogue.' : 'Votre annonce a été refusée par la modération.';
    add_notification((int)$product['id_vendeur'], $titre, $message, 'moderation', 'produit.php?id=' . $productId);
    $pdo->prepare('INSERT INTO admin_logs (id_admin, action, details, date_action) VALUES (?, ?, ?, NOW())')->execute([$adminId, 'moderation_' . $status, 'Produit #' . $productId]);
    return true;
}

function get_admin_ids(): array
{
    return getDB()->query('SELECT u.id_user FROM users u JOIN roles r ON r.id_role = u.id_role WHERE r.nom_role = "admin"')->fetchAll(PDO::FETCH_COLUMN);
}

function add_notification(int $userId, string $title, string $message, string $type = 'info', string $link = ''): void
{
    $stmt = getDB()->prepare('INSERT INTO notifications (id_user, titre, message, type_notification, lien, est_lue, date_creation) VALUES (?, ?, ?, ?, ?, 0, NOW())');
    $stmt->execute([$userId, $title, $message, $type, $link]);
}

function get_notifications(int $userId): array
{
    $stmt = getDB()->prepare('SELECT * FROM notifications WHERE id_user = ? ORDER BY date_creation DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function unread_notifications_count(int $userId): int
{
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE id_user = ? AND est_lue = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function mark_notification_read(int $notificationId, int $userId): bool
{
    $stmt = getDB()->prepare('UPDATE notifications SET est_lue = 1 WHERE id_notification = ? AND id_user = ?');
    $stmt->execute([$notificationId, $userId]);
    return $stmt->rowCount() > 0;
}

function get_or_create_cart(int $userId): int
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id_cart FROM carts WHERE id_user = ? AND statut = "actif" LIMIT 1');
    $stmt->execute([$userId]);
    $cartId = $stmt->fetchColumn();
    if ($cartId) {
        return (int)$cartId;
    }
    $pdo->prepare('INSERT INTO carts (id_user, statut, date_creation) VALUES (?, "actif", NOW())')->execute([$userId]);
    return (int)$pdo->lastInsertId();
}

function get_cart_items(int $userId): array
{
    $cartId = get_or_create_cart($userId);
    $stmt = getDB()->prepare('SELECT ci.*, p.titre, p.stock, p.statut, p.id_vendeur, p.type_vente, p.prix_achat_immediat, '
        . get_main_image_sql() . ' AS image '
        . 'FROM cart_items ci JOIN products p ON p.id_product = ci.id_product WHERE ci.id_cart = ? ORDER BY ci.id_cart_item DESC');
    $stmt->execute([$cartId]);
    return $stmt->fetchAll();
}

function add_to_cart(int $userId, int $productId, int $quantity = 1): array
{
    $pdo = getDB();
    $product = get_product_by_id($productId);
    if (!$product || $product['statut'] !== 'publie') {
        return ['success' => false, 'message' => 'Produit indisponible.'];
    }
    if ((int)$product['id_vendeur'] === $userId) {
        return ['success' => false, 'message' => 'Vous ne pouvez pas acheter votre propre produit.'];
    }
    if (!in_array($product['type_vente'], ['achat_immediat', 'mixte'], true)) {
        return ['success' => false, 'message' => 'Ce produit n’est pas disponible en achat immédiat.'];
    }
    if ((int)$product['stock'] < $quantity) {
        return ['success' => false, 'message' => 'Stock insuffisant.'];
    }

    $cartId = get_or_create_cart($userId);
    $stmt = $pdo->prepare('SELECT id_cart_item, quantite FROM cart_items WHERE id_cart = ? AND id_product = ? LIMIT 1');
    $stmt->execute([$cartId, $productId]);
    $existing = $stmt->fetch();
    $price = (float)$product['prix_achat_immediat'];

    if ($existing) {
        $newQty = (int)$existing['quantite'] + $quantity;
        if ($newQty > (int)$product['stock']) {
            return ['success' => false, 'message' => 'Stock insuffisant pour cette quantité.'];
        }
        $pdo->prepare('UPDATE cart_items SET quantite = ?, prix_unitaire = ? WHERE id_cart_item = ?')->execute([$newQty, $price, (int)$existing['id_cart_item']]);
    } else {
        $pdo->prepare('INSERT INTO cart_items (id_cart, id_product, quantite, prix_unitaire) VALUES (?, ?, ?, ?)')->execute([$cartId, $productId, $quantity, $price]);
    }
    return ['success' => true, 'message' => 'Produit ajouté au panier.'];
}

function update_cart_item(int $userId, int $cartItemId, int $quantity): array
{
    $quantity = max(1, $quantity);
    $cartId = get_or_create_cart($userId);
    $stmt = getDB()->prepare('SELECT ci.*, p.stock FROM cart_items ci JOIN products p ON p.id_product = ci.id_product WHERE ci.id_cart_item = ? AND ci.id_cart = ?');
    $stmt->execute([$cartItemId, $cartId]);
    $item = $stmt->fetch();
    if (!$item) {
        return ['success' => false, 'message' => 'Ligne panier introuvable.'];
    }
    if ($quantity > (int)$item['stock']) {
        return ['success' => false, 'message' => 'Quantité supérieure au stock disponible.'];
    }
    getDB()->prepare('UPDATE cart_items SET quantite = ? WHERE id_cart_item = ?')->execute([$quantity, $cartItemId]);
    return ['success' => true, 'message' => 'Panier mis à jour.'];
}

function remove_cart_item(int $userId, int $cartItemId): bool
{
    $cartId = get_or_create_cart($userId);
    $stmt = getDB()->prepare('DELETE FROM cart_items WHERE id_cart_item = ? AND id_cart = ?');
    $stmt->execute([$cartItemId, $cartId]);
    return $stmt->rowCount() > 0;
}

function cart_total(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float)$item['prix_unitaire'] * (int)$item['quantite'];
    }
    return $total;
}

function validate_cart(int $userId, array $data): array
{
    $pdo = getDB();
    $adresse = clean_string($data['adresse_livraison'] ?? '', 255);
    $methode = clean_string($data['methode'] ?? 'carte_fictive', 50);
    if ($adresse === '') {
        return ['success' => false, 'message' => 'Adresse de livraison obligatoire.'];
    }

    $cartId = get_or_create_cart($userId);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT ci.*, p.titre, p.stock, p.statut, p.id_vendeur, p.prix_achat_immediat FROM cart_items ci JOIN products p ON p.id_product = ci.id_product WHERE ci.id_cart = ? FOR UPDATE');
        $stmt->execute([$cartId]);
        $items = $stmt->fetchAll();
        if (!$items) {
            throw new RuntimeException('Le panier est vide.');
        }

        $total = 0.0;
        foreach ($items as $item) {
            if ($item['statut'] !== 'publie') {
                throw new RuntimeException('Le produit "' . $item['titre'] . '" n’est plus disponible.');
            }
            if ((int)$item['id_vendeur'] === $userId) {
                throw new RuntimeException('Vous ne pouvez pas acheter votre propre produit.');
            }
            if ((int)$item['stock'] < (int)$item['quantite']) {
                throw new RuntimeException('Stock insuffisant pour "' . $item['titre'] . '".');
            }
            $total += (float)$item['prix_unitaire'] * (int)$item['quantite'];
        }

        $pdo->prepare('INSERT INTO orders (id_acheteur, montant_total, statut_commande, date_commande, adresse_livraison) VALUES (?, ?, "payee", NOW(), ?)')->execute([$userId, $total, $adresse]);
        $orderId = (int)$pdo->lastInsertId();

        foreach ($items as $item) {
            $pdo->prepare('INSERT INTO order_items (id_order, id_product, id_vendeur, quantite, prix_unitaire) VALUES (?, ?, ?, ?, ?)')->execute([$orderId, (int)$item['id_product'], (int)$item['id_vendeur'], (int)$item['quantite'], (float)$item['prix_unitaire']]);
            $pdo->prepare('UPDATE products SET stock = stock - ?, statut = IF(stock - ? <= 0, "vendu", statut), date_modification = NOW() WHERE id_product = ?')->execute([(int)$item['quantite'], (int)$item['quantite'], (int)$item['id_product']]);
            add_notification((int)$item['id_vendeur'], 'Nouvelle vente', 'Votre produit "' . $item['titre'] . '" a été acheté.', 'vente', 'mes-ventes.php');
        }

        $pdo->prepare('INSERT INTO payments (id_order, methode, statut_paiement, montant, date_paiement, transaction_reference) VALUES (?, ?, "accepte", ?, NOW(), ?)')->execute([$orderId, $methode, $total, 'SIM-' . strtoupper(bin2hex(random_bytes(4)))]);
        $pdo->prepare('DELETE FROM cart_items WHERE id_cart = ?')->execute([$cartId]);
        $pdo->prepare('UPDATE carts SET statut = "valide" WHERE id_cart = ?')->execute([$cartId]);
        add_notification($userId, 'Commande validée', 'Votre paiement simulé a été accepté.', 'commande', 'confirmation.php?id=' . $orderId);
        $pdo->commit();
        return ['success' => true, 'message' => 'Commande validée.', 'order_id' => $orderId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function get_user_orders(int $userId): array
{
    $stmt = getDB()->prepare('SELECT * FROM orders WHERE id_acheteur = ? ORDER BY date_commande DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_order_details(int $orderId, int $userId): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM orders WHERE id_order = ? AND id_acheteur = ? LIMIT 1');
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT oi.*, p.titre, ' . get_main_image_sql() . ' AS image FROM order_items oi JOIN products p ON p.id_product = oi.id_product WHERE oi.id_order = ?');
    $stmt->execute([$orderId]);
    $order['items'] = $stmt->fetchAll();
    $stmt = getDB()->prepare('SELECT * FROM payments WHERE id_order = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $order['payment'] = $stmt->fetch();
    return $order;
}

function get_sales_by_seller(int $sellerId): array
{
    $stmt = getDB()->prepare('SELECT o.date_commande, o.statut_commande, oi.*, p.titre, u.prenom, u.nom FROM order_items oi JOIN orders o ON o.id_order = oi.id_order JOIN products p ON p.id_product = oi.id_product JOIN users u ON u.id_user = o.id_acheteur WHERE oi.id_vendeur = ? ORDER BY o.date_commande DESC');
    $stmt->execute([$sellerId]);
    return $stmt->fetchAll();
}

function place_bid(int $auctionId, int $userId, float $amount): array
{
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT a.*, p.id_vendeur, p.titre, p.statut
             FROM auctions a
             JOIN products p ON p.id_product = a.id_product
             WHERE a.id_auction = ?
             FOR UPDATE'
        );
        $stmt->execute([$auctionId]);
        $auction = $stmt->fetch();

        if (!$auction || $auction['statut_enchere'] !== 'en_cours' || $auction['statut'] !== 'publie') {
            throw new RuntimeException('Enchère indisponible.');
        }

        if ((int)$auction['id_vendeur'] === $userId) {
            throw new RuntimeException('Vous ne pouvez pas enchérir sur votre propre annonce.');
        }

        if (strtotime($auction['date_fin']) <= time()) {
            throw new RuntimeException('Cette enchère est terminée.');
        }

        $minimum = (float)$auction['prix_actuel'] + MIN_BID_STEP;

        if ($amount < $minimum) {
            throw new RuntimeException('Votre offre doit être au minimum de ' . money_format_fr($minimum) . '.');
        }

        $previousBidderStmt = $pdo->prepare(
            'SELECT id_user
             FROM bids
             WHERE id_auction = ?
             ORDER BY montant DESC, date_bid DESC
             LIMIT 1'
        );
        $previousBidderStmt->execute([$auctionId]);
        $previousBidderId = $previousBidderStmt->fetchColumn();

        $pdo->prepare(
            'INSERT INTO bids (id_auction, id_user, montant, date_bid)
             VALUES (?, ?, ?, NOW())'
        )->execute([$auctionId, $userId, $amount]);

        $pdo->prepare(
            'UPDATE auctions
             SET prix_actuel = ?
             WHERE id_auction = ?'
        )->execute([$amount, $auctionId]);

        add_notification(
            (int)$auction['id_vendeur'],
            'Nouvelle enchère',
            'Une offre de ' . money_format_fr($amount) . ' a été placée sur "' . $auction['titre'] . '".',
            'enchere',
            'enchere.php?id=' . (int)$auction['id_product']
        );

        add_notification(
            $userId,
            'Offre enregistrée',
            'Votre offre de ' . money_format_fr($amount) . ' a bien été enregistrée pour "' . $auction['titre'] . '".',
            'enchere',
            'enchere.php?id=' . (int)$auction['id_product']
        );

        if ($previousBidderId && (int)$previousBidderId !== $userId) {
            add_notification(
                (int)$previousBidderId,
                'Vous avez été surenchéri',
                'Une nouvelle offre plus élevée a été placée sur "' . $auction['titre'] . '".',
                'enchere',
                'enchere.php?id=' . (int)$auction['id_product']
            );
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Offre enregistrée.'
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();

        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}


function get_bid_history(int $auctionId): array
{
    $stmt = getDB()->prepare('SELECT b.*, u.prenom, u.nom FROM bids b JOIN users u ON u.id_user = b.id_user WHERE b.id_auction = ? ORDER BY b.montant DESC, b.date_bid DESC');
    $stmt->execute([$auctionId]);
    return $stmt->fetchAll();
}

function close_ended_auctions(): void
{
    static $already = false;
    if ($already) {
        return;
    }
    $already = true;
    $pdo = getDB();
    $stmt = $pdo->query('SELECT a.*, p.titre, p.id_vendeur FROM auctions a JOIN products p ON p.id_product = a.id_product WHERE a.statut_enchere = "en_cours" AND a.date_fin <= NOW()');
    $auctions = $stmt->fetchAll();
    foreach ($auctions as $auction) {
        $pdo->beginTransaction();
        try {
            $top = $pdo->prepare('SELECT * FROM bids WHERE id_auction = ? ORDER BY montant DESC, date_bid ASC LIMIT 1');
            $top->execute([(int)$auction['id_auction']]);
            $bid = $top->fetch();
            if ($bid) {
                $pdo->prepare('UPDATE auctions SET statut_enchere = "terminee", id_gagnant = ? WHERE id_auction = ?')->execute([(int)$bid['id_user'], (int)$auction['id_auction']]);
                $pdo->prepare('UPDATE products SET statut = "vendu", stock = 0 WHERE id_product = ?')->execute([(int)$auction['id_product']]);
                $pdo->prepare('INSERT INTO orders (id_acheteur, montant_total, statut_commande, date_commande, adresse_livraison) VALUES (?, ?, "payee", NOW(), "Adresse à confirmer")')->execute([(int)$bid['id_user'], (float)$bid['montant']]);
                $orderId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO order_items (id_order, id_product, id_vendeur, quantite, prix_unitaire) VALUES (?, ?, ?, 1, ?)')->execute([$orderId, (int)$auction['id_product'], (int)$auction['id_vendeur'], (float)$bid['montant']]);
                $pdo->prepare('INSERT INTO payments (id_order, methode, statut_paiement, montant, date_paiement, transaction_reference) VALUES (?, "enchere_simulee", "accepte", ?, NOW(), ?)')->execute([$orderId, (float)$bid['montant'], 'AUC-' . strtoupper(bin2hex(random_bytes(4)))]);
                add_notification((int)$bid['id_user'], 'Enchère gagnée', 'Vous avez gagné l’enchère : ' . $auction['titre'], 'enchere', 'confirmation.php?id=' . $orderId);
                add_notification((int)$auction['id_vendeur'], 'Enchère terminée', 'Votre enchère est terminée avec un gagnant.', 'enchere', 'mes-ventes.php');
                $losers = $pdo->prepare('SELECT DISTINCT id_user FROM bids WHERE id_auction = ? AND id_user <> ?');
                $losers->execute([(int)$auction['id_auction'], (int)$bid['id_user']]);
                foreach ($losers->fetchAll(PDO::FETCH_COLUMN) as $loserId) {
                    add_notification((int)$loserId, 'Enchère perdue', 'Vous n’avez pas remporté l’enchère : ' . $auction['titre'], 'enchere', 'mes-encheres.php');
                }
            } else {
                $pdo->prepare('UPDATE auctions SET statut_enchere = "terminee" WHERE id_auction = ?')->execute([(int)$auction['id_auction']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
        }
    }
}

function get_user_bids(int $userId): array
{
    $stmt = getDB()->prepare('SELECT b.*, a.prix_actuel, a.date_fin, a.statut_enchere, a.id_gagnant, p.titre, p.id_product FROM bids b JOIN auctions a ON a.id_auction = b.id_auction JOIN products p ON p.id_product = a.id_product WHERE b.id_user = ? ORDER BY b.date_bid DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function expire_old_negotiations(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $stmt = getDB()->prepare('UPDATE negotiations SET statut_negociation = "expiree", date_fin = NOW() WHERE statut_negociation = "en_cours" AND date_debut < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->execute([NEGOTIATION_EXPIRATION_DAYS]);
}

function start_negotiation(int $productId, int $buyerId, float $offer, string $message): array
{
    expire_old_negotiations();
    $pdo = getDB();
    $product = get_product_by_id($productId);
    if (!$product || $product['statut'] !== 'publie') {
        return ['success' => false, 'message' => 'Produit indisponible.'];
    }
    if ((int)$product['id_vendeur'] === $buyerId) {
        return ['success' => false, 'message' => 'Vous ne pouvez pas négocier votre propre produit.'];
    }
    if (!in_array($product['type_vente'], ['negociation', 'mixte'], true)) {
        return ['success' => false, 'message' => 'Ce produit n’est pas ouvert à la négociation.'];
    }
    if ($offer <= 0) {
        return ['success' => false, 'message' => 'Le prix proposé doit être positif.'];
    }

    $stmt = $pdo->prepare('SELECT id_negotiation FROM negotiations WHERE id_product = ? AND id_acheteur = ? AND statut_negociation = "en_cours" LIMIT 1');
    $stmt->execute([$productId, $buyerId]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return ['success' => true, 'message' => 'Une négociation existe déjà.', 'id_negotiation' => (int)$existing];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO negotiations (id_product, id_acheteur, id_vendeur, prix_initial, prix_actuel, statut_negociation, date_debut) VALUES (?, ?, ?, ?, ?, "en_cours", NOW())')->execute([$productId, $buyerId, (int)$product['id_vendeur'], (float)$product['prix_achat_immediat'], $offer]);
        $negotiationId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO negotiation_messages (id_negotiation, id_sender, message, offre_prix, date_message) VALUES (?, ?, ?, ?, NOW())')->execute([$negotiationId, $buyerId, clean_string($message, 500), $offer]);
        add_notification((int)$product['id_vendeur'], 'Nouvelle négociation', 'Un acheteur propose ' . money_format_fr($offer) . ' pour "' . $product['titre'] . '".', 'negociation', 'negotiation.php?id=' . $negotiationId);
        $pdo->commit();
        return ['success' => true, 'message' => 'Négociation ouverte.', 'id_negotiation' => $negotiationId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function get_negotiation(int $negotiationId, int $userId): ?array
{
    expire_old_negotiations();
    $stmt = getDB()->prepare('SELECT n.*, p.titre, p.id_product, p.statut AS product_statut, u1.prenom AS acheteur_prenom, u1.nom AS acheteur_nom, u2.prenom AS vendeur_prenom, u2.nom AS vendeur_nom FROM negotiations n JOIN products p ON p.id_product = n.id_product JOIN users u1 ON u1.id_user = n.id_acheteur JOIN users u2 ON u2.id_user = n.id_vendeur WHERE n.id_negotiation = ? AND (n.id_acheteur = ? OR n.id_vendeur = ? OR ? = 1) LIMIT 1');
    $stmt->execute([$negotiationId, $userId, $userId, is_admin() ? 1 : 0]);
    $neg = $stmt->fetch();
    if (!$neg) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT m.*, u.prenom, u.nom FROM negotiation_messages m JOIN users u ON u.id_user = m.id_sender WHERE m.id_negotiation = ? ORDER BY m.date_message ASC');
    $stmt->execute([$negotiationId]);
    $neg['messages'] = $stmt->fetchAll();
    return $neg;
}

function send_counter_offer(int $negotiationId, int $senderId, float $offer, string $message): array
{
    $neg = get_negotiation($negotiationId, $senderId);
    if (!$neg || $neg['statut_negociation'] !== 'en_cours') {
        return ['success' => false, 'message' => 'Négociation indisponible.'];
    }
    if (!in_array($senderId, [(int)$neg['id_acheteur'], (int)$neg['id_vendeur']], true)) {
        return ['success' => false, 'message' => 'Accès refusé.'];
    }
    if (count($neg['messages']) >= MAX_NEGOTIATION_MESSAGES) {
        return ['success' => false, 'message' => 'Nombre maximal d’échanges atteint. Acceptez ou refusez la négociation.'];
    }
    $lastSender = get_negotiation_last_sender($negotiationId);
    if ($lastSender !== null && $lastSender === $senderId) {
        return ['success' => false, 'message' => 'Vous devez attendre la réponse de l’autre participant avant de renvoyer une offre.'];
    }
    if ($offer <= 0) {
        return ['success' => false, 'message' => 'Le montant doit être positif.'];
    }
    $pdo = getDB();
    $pdo->prepare('INSERT INTO negotiation_messages (id_negotiation, id_sender, message, offre_prix, date_message) VALUES (?, ?, ?, ?, NOW())')->execute([$negotiationId, $senderId, clean_string($message, 500), $offer]);
    $pdo->prepare('UPDATE negotiations SET prix_actuel = ? WHERE id_negotiation = ?')->execute([$offer, $negotiationId]);
    $receiver = $senderId === (int)$neg['id_acheteur'] ? (int)$neg['id_vendeur'] : (int)$neg['id_acheteur'];
    add_notification($receiver, 'Offre reçue', 'Une nouvelle offre attend votre réponse pour "' . $neg['titre'] . '".', 'negociation', 'negotiation.php?id=' . $negotiationId);
    return ['success' => true, 'message' => 'Offre envoyée.'];
}

function accept_negotiation(int $negotiationId, int $userId): array
{
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT n.*, p.titre, p.stock, p.statut FROM negotiations n JOIN products p ON p.id_product = n.id_product WHERE n.id_negotiation = ? FOR UPDATE');
        $stmt->execute([$negotiationId]);
        $neg = $stmt->fetch();
        if (!$neg || $neg['statut_negociation'] !== 'en_cours') {
            throw new RuntimeException('Négociation indisponible.');
        }
        if (!in_array($userId, [(int)$neg['id_acheteur'], (int)$neg['id_vendeur']], true)) {
            throw new RuntimeException('Accès refusé.');
        }
        $lastSender = get_negotiation_last_sender($negotiationId);
        if ($lastSender === null || $lastSender === $userId) {
            throw new RuntimeException('Vous ne pouvez accepter que la dernière offre envoyée par l’autre participant.');
        }
        if ($neg['statut'] !== 'publie' || (int)$neg['stock'] <= 0) {
            throw new RuntimeException('Produit indisponible.');
        }
        $price = (float)$neg['prix_actuel'];
        $pdo->prepare('UPDATE negotiations SET statut_negociation = "acceptee", date_fin = NOW() WHERE id_negotiation = ?')->execute([$negotiationId]);
        $pdo->prepare('UPDATE products SET stock = stock - 1, statut = IF(stock - 1 <= 0, "vendu", statut) WHERE id_product = ?')->execute([(int)$neg['id_product']]);
        $pdo->prepare('INSERT INTO orders (id_acheteur, montant_total, statut_commande, date_commande, adresse_livraison) VALUES (?, ?, "payee", NOW(), "Adresse à confirmer")')->execute([(int)$neg['id_acheteur'], $price]);
        $orderId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO order_items (id_order, id_product, id_vendeur, quantite, prix_unitaire) VALUES (?, ?, ?, 1, ?)')->execute([$orderId, (int)$neg['id_product'], (int)$neg['id_vendeur'], $price]);
        $pdo->prepare('INSERT INTO payments (id_order, methode, statut_paiement, montant, date_paiement, transaction_reference) VALUES (?, "negociation_simulee", "accepte", ?, NOW(), ?)')->execute([$orderId, $price, 'NEG-' . strtoupper(bin2hex(random_bytes(4)))]);
        add_notification((int)$neg['id_acheteur'], 'Négociation acceptée', 'La négociation pour "' . $neg['titre'] . '" est acceptée.', 'negociation', 'confirmation.php?id=' . $orderId);
        add_notification((int)$neg['id_vendeur'], 'Négociation acceptée', 'La vente négociée est validée.', 'negociation', 'mes-ventes.php');
        $pdo->commit();
        return ['success' => true, 'message' => 'Négociation acceptée.', 'order_id' => $orderId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function refuse_negotiation(int $negotiationId, int $userId): array
{
    $neg = get_negotiation($negotiationId, $userId);
    if (!$neg || $neg['statut_negociation'] !== 'en_cours') {
        return ['success' => false, 'message' => 'Négociation indisponible.'];
    }
    $lastSender = get_negotiation_last_sender($negotiationId);
    if ($lastSender === null || $lastSender === $userId) {
        return ['success' => false, 'message' => 'Vous ne pouvez refuser que la dernière offre envoyée par l’autre participant.'];
    }
    getDB()->prepare('UPDATE negotiations SET statut_negociation = "refusee", date_fin = NOW() WHERE id_negotiation = ?')->execute([$negotiationId]);
    $receiver = $userId === (int)$neg['id_acheteur'] ? (int)$neg['id_vendeur'] : (int)$neg['id_acheteur'];
    add_notification($receiver, 'Négociation refusée', 'La négociation pour "' . $neg['titre'] . '" a été refusée.', 'negociation', 'mes-negociations.php');
    return ['success' => true, 'message' => 'Négociation refusée.'];
}

function abandon_negotiation(int $negotiationId, int $userId): array
{
    $neg = get_negotiation($negotiationId, $userId);
    if (!$neg || $neg['statut_negociation'] !== 'en_cours') {
        return ['success' => false, 'message' => 'Négociation indisponible.'];
    }
    getDB()->prepare('UPDATE negotiations SET statut_negociation = "abandonnee", date_fin = NOW() WHERE id_negotiation = ?')->execute([$negotiationId]);
    $receiver = $userId === (int)$neg['id_acheteur'] ? (int)$neg['id_vendeur'] : (int)$neg['id_acheteur'];
    add_notification($receiver, 'Négociation abandonnée', 'La négociation pour "' . $neg['titre'] . '" a été abandonnée.', 'negociation', 'mes-negociations.php');
    return ['success' => true, 'message' => 'Négociation abandonnée.'];
}

function get_user_negotiations(int $userId): array
{
    expire_old_negotiations();
    $stmt = getDB()->prepare('SELECT n.*, p.titre FROM negotiations n JOIN products p ON p.id_product = n.id_product WHERE n.id_acheteur = ? OR n.id_vendeur = ? ORDER BY n.date_debut DESC');
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}

function get_dashboard_stats(): array
{
    $pdo = getDB();
    return [
        'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'products' => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'pending' => (int)$pdo->query('SELECT COUNT(*) FROM products WHERE statut = "en_attente"')->fetchColumn(),
        'orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'auctions' => (int)$pdo->query('SELECT COUNT(*) FROM auctions')->fetchColumn(),
        'negotiations' => (int)$pdo->query('SELECT COUNT(*) FROM negotiations')->fetchColumn(),
    ];
}

function get_all_users(): array
{
    return getDB()->query('SELECT u.*, r.nom_role FROM users u JOIN roles r ON r.id_role = u.id_role ORDER BY u.date_creation DESC')->fetchAll();
}

function set_user_status(int $userId, string $status, int $adminId): bool
{
    if (!in_array($status, ['actif', 'bloque'], true)) {
        return false;
    }
    getDB()->prepare('UPDATE users SET statut_compte = ? WHERE id_user = ?')->execute([$status, $userId]);
    getDB()->prepare('INSERT INTO admin_logs (id_admin, action, details, date_action) VALUES (?, ?, ?, NOW())')->execute([$adminId, 'user_status', 'Utilisateur #' . $userId . ' => ' . $status]);
    return true;
}

function report_product(int $userId, int $productId, string $motif, string $description): bool
{
    $stmt = getDB()->prepare('INSERT INTO reports (id_user, id_product, motif, description, statut, date_report) VALUES (?, ?, ?, ?, "ouvert", NOW())');
    $stmt->execute([$userId, $productId, clean_string($motif, 120), clean_string($description, 500)]);
    foreach (get_admin_ids() as $adminId) {
        add_notification((int)$adminId, 'Nouveau signalement', 'Une annonce a été signalée.', 'signalement', 'espace-admin.php');
    }
    return true;
}

function get_reports(): array
{
    return getDB()->query('SELECT r.*, p.titre, u.prenom, u.nom FROM reports r JOIN products p ON p.id_product = r.id_product JOIN users u ON u.id_user = r.id_user ORDER BY r.date_report DESC')->fetchAll();
}


function update_user_profile(int $userId, array $data): array
{
    $errors = [];
    $prenom = clean_string($data['prenom'] ?? '', 80);
    $nom = clean_string($data['nom'] ?? '', 80);
    $telephone = clean_string($data['telephone'] ?? '', 30);
    $adresse = clean_string($data['adresse'] ?? '', 255);
    $ville = clean_string($data['ville'] ?? '', 100);
    $codePostal = clean_string($data['code_postal'] ?? '', 20);
    $currentPassword = (string)($data['current_password'] ?? '');
    $newPassword = (string)($data['new_password'] ?? '');
    $confirmPassword = (string)($data['confirm_password'] ?? '');

    validate_required($prenom, 'prénom', $errors);
    validate_required($nom, 'nom', $errors);

    $passwordMustChange = $newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '';
    if ($passwordMustChange) {
        if ($currentPassword === '') {
            $errors[] = 'Le mot de passe actuel est obligatoire pour changer le mot de passe.';
        }
        validate_password_value($newPassword, $errors);
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Les deux nouveaux mots de passe ne correspondent pas.';
        }
    }

    $user = get_user_by_id($userId);
    if (!$user) {
        $errors[] = 'Utilisateur introuvable.';
    }

    if ($passwordMustChange && $user && !password_verify($currentPassword, $user['mot_de_passe'])) {
        $errors[] = 'Le mot de passe actuel est incorrect.';
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    $pdo = getDB();
    if ($passwordMustChange) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET prenom = ?, nom = ?, telephone = ?, adresse = ?, ville = ?, code_postal = ?, mot_de_passe = ? WHERE id_user = ?');
        $stmt->execute([$prenom, $nom, $telephone, $adresse, $ville, $codePostal, $hash, $userId]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET prenom = ?, nom = ?, telephone = ?, adresse = ?, ville = ?, code_postal = ? WHERE id_user = ?');
        $stmt->execute([$prenom, $nom, $telephone, $adresse, $ville, $codePostal, $userId]);
    }

    add_notification($userId, 'Profil mis à jour', 'Vos informations personnelles ont été modifiées.', 'compte', 'profil.php');
    return ['success' => true, 'errors' => []];
}

function update_report_status(int $reportId, string $status, int $adminId): bool
{
    if (!in_array($status, ['ouvert', 'traite', 'rejete'], true)) {
        return false;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT r.*, p.titre FROM reports r JOIN products p ON p.id_product = r.id_product WHERE r.id_report = ? LIMIT 1');
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    if (!$report) {
        return false;
    }

    $pdo->prepare('UPDATE reports SET statut = ? WHERE id_report = ?')->execute([$status, $reportId]);
    $pdo->prepare('INSERT INTO admin_logs (id_admin, action, details, date_action) VALUES (?, ?, ?, NOW())')
        ->execute([$adminId, 'report_' . $status, 'Signalement #' . $reportId . ' sur produit #' . (int)$report['id_product']]);

    add_notification((int)$report['id_user'], 'Signalement mis à jour', 'Votre signalement concernant "' . $report['titre'] . '" est maintenant : ' . $status . '.', 'signalement', 'notifications.php');
    return true;
}

function get_vendor_accounts(): array
{
    $sql = 'SELECT u.*, r.nom_role,
            COUNT(DISTINCT p.id_product) AS nb_annonces,
            COUNT(DISTINCT CASE WHEN p.statut = "publie" THEN p.id_product END) AS nb_annonces_publiees,
            COUNT(DISTINCT oi.id_order_item) AS nb_ventes,
            COALESCE(SUM(oi.quantite * oi.prix_unitaire), 0) AS chiffre_affaires
        FROM users u
        JOIN roles r ON r.id_role = u.id_role
        LEFT JOIN products p ON p.id_vendeur = u.id_user
        LEFT JOIN order_items oi ON oi.id_vendeur = u.id_user
        WHERE r.nom_role = "vendeur"
        GROUP BY u.id_user
        ORDER BY u.date_creation DESC';
    return getDB()->query($sql)->fetchAll();
}

function get_negotiation_last_sender(int $negotiationId): ?int
{
    $stmt = getDB()->prepare('SELECT id_sender FROM negotiation_messages WHERE id_negotiation = ? ORDER BY date_message DESC, id_message DESC LIMIT 1');
    $stmt->execute([$negotiationId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
}

function can_answer_negotiation(array $neg, int $userId): bool
{
    if (($neg['statut_negociation'] ?? '') !== 'en_cours') {
        return false;
    }
    if (!in_array($userId, [(int)$neg['id_acheteur'], (int)$neg['id_vendeur']], true)) {
        return false;
    }
    $messages = $neg['messages'] ?? [];
    if (!$messages) {
        return false;
    }
    $last = end($messages);
    return (int)$last['id_sender'] !== $userId;
}

function update_product_gallery(int $productId, array $data, array $newImagePaths = []): void
{
    $pdo = getDB();
    $deleteIds = array_map('intval', (array)($data['delete_images'] ?? []));
    if ($deleteIds) {
        $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
        $stmt = $pdo->prepare('SELECT * FROM product_images WHERE id_product = ? AND id_image IN (' . $placeholders . ')');
        $stmt->execute(array_merge([$productId], $deleteIds));
        foreach ($stmt->fetchAll() as $img) {
            $path = (string)$img['chemin_image'];
            if (str_starts_with($path, 'frontend/images/produits/')) {
                $absolute = dirname(__DIR__, 2) . '/' . $path;
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }
        }
        $pdo->prepare('DELETE FROM product_images WHERE id_product = ? AND id_image IN (' . $placeholders . ')')
            ->execute(array_merge([$productId], $deleteIds));
    }

    foreach ($newImagePaths as $path) {
        if ($path) {
            $pdo->prepare('INSERT INTO product_images (id_product, chemin_image, image_principale) VALUES (?, ?, 0)')->execute([$productId, $path]);
        }
    }

    $mainImageId = (int)($data['main_image_id'] ?? 0);
    if ($mainImageId > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE id_product = ? AND id_image = ?');
        $stmt->execute([$productId, $mainImageId]);
        if ((int)$stmt->fetchColumn() > 0) {
            $pdo->prepare('UPDATE product_images SET image_principale = 0 WHERE id_product = ?')->execute([$productId]);
            $pdo->prepare('UPDATE product_images SET image_principale = 1 WHERE id_product = ? AND id_image = ?')->execute([$productId, $mainImageId]);
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE id_product = ? AND image_principale = 1');
    $stmt->execute([$productId]);
    if ((int)$stmt->fetchColumn() === 0) {
        $stmt = $pdo->prepare('SELECT id_image FROM product_images WHERE id_product = ? ORDER BY id_image ASC LIMIT 1');
        $stmt->execute([$productId]);
        $first = $stmt->fetchColumn();
        if ($first) {
            $pdo->prepare('UPDATE product_images SET image_principale = 1 WHERE id_image = ?')->execute([(int)$first]);
        }
    }
}

function is_favorite(int $userId, int $productId): bool
{
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM favorites WHERE id_user = ? AND id_product = ?');
    $stmt->execute([$userId, $productId]);
    return (int)$stmt->fetchColumn() > 0;
}

function toggle_favorite(int $userId, int $productId): array
{
    $product = get_product_by_id($productId);
    if (!$product || $product['statut'] !== 'publie') {
        return ['success' => false, 'message' => 'Produit indisponible.'];
    }
    if ((int)$product['id_vendeur'] === $userId) {
        return ['success' => false, 'message' => 'Vous ne pouvez pas ajouter votre propre annonce aux favoris.'];
    }
    $pdo = getDB();
    if (is_favorite($userId, $productId)) {
        $pdo->prepare('DELETE FROM favorites WHERE id_user = ? AND id_product = ?')->execute([$userId, $productId]);
        return ['success' => true, 'message' => 'Produit retiré des favoris.', 'is_favorite' => false];
    }
    $pdo->prepare('INSERT INTO favorites (id_user, id_product, date_creation) VALUES (?, ?, NOW())')->execute([$userId, $productId]);
    return ['success' => true, 'message' => 'Produit ajouté aux favoris.', 'is_favorite' => true];
}

function get_user_favorites(int $userId): array
{
    close_ended_auctions();
    $stmt = getDB()->prepare('SELECT p.*, c.nom_categorie, u.prenom AS vendeur_prenom, u.nom AS vendeur_nom, f.date_creation AS date_favori, '
        . get_main_image_sql() . ' AS image, a.id_auction, a.prix_actuel, a.date_fin, a.statut_enchere '
        . 'FROM favorites f '
        . 'JOIN products p ON p.id_product = f.id_product '
        . 'JOIN categories c ON c.id_categorie = p.id_categorie '
        . 'JOIN users u ON u.id_user = p.id_vendeur '
        . 'LEFT JOIN auctions a ON a.id_product = p.id_product AND a.statut_enchere = "en_cours" '
        . 'WHERE f.id_user = ? AND p.statut = "publie" '
        . 'ORDER BY f.date_creation DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function count_user_favorites(int $userId): int
{
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM favorites f JOIN products p ON p.id_product = f.id_product WHERE f.id_user = ? AND p.statut = "publie"');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function get_admin_logs(int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    return getDB()->query('SELECT l.*, u.prenom, u.nom, u.email FROM admin_logs l JOIN users u ON u.id_user = l.id_admin ORDER BY l.date_action DESC LIMIT ' . $limit)->fetchAll();
}

function get_auction_status(int $auctionId): ?array
{
    close_ended_auctions();
    $stmt = getDB()->prepare('SELECT a.*, p.id_product, p.titre FROM auctions a JOIN products p ON p.id_product = a.id_product WHERE a.id_auction = ? LIMIT 1');
    $stmt->execute([$auctionId]);
    $auction = $stmt->fetch();
    if (!$auction) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM bids WHERE id_auction = ?');
    $stmt->execute([$auctionId]);
    $auction['nb_offres'] = (int)$stmt->fetchColumn();
    return $auction;
}
