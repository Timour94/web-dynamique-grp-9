DROP DATABASE IF EXISTS mercato_nova;
CREATE DATABASE mercato_nova CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mercato_nova;

CREATE TABLE roles (
    id_role INT AUTO_INCREMENT PRIMARY KEY,
    nom_role VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    id_role INT NOT NULL,
    nom VARCHAR(80) NOT NULL,
    prenom VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    telephone VARCHAR(30),
    adresse VARCHAR(255),
    ville VARCHAR(100),
    code_postal VARCHAR(20),
    statut_compte ENUM('actif','bloque') NOT NULL DEFAULT 'actif',
    date_creation DATETIME NOT NULL,
    date_derniere_connexion DATETIME NULL,
    FOREIGN KEY (id_role) REFERENCES roles(id_role)
) ENGINE=InnoDB;

CREATE TABLE categories (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom_categorie VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE products (
    id_product INT AUTO_INCREMENT PRIMARY KEY,
    id_vendeur INT NOT NULL,
    id_categorie INT NOT NULL,
    titre VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    prix_initial DECIMAL(10,2) NOT NULL DEFAULT 0,
    prix_achat_immediat DECIMAL(10,2) NULL,
    type_vente ENUM('achat_immediat','enchere','negociation','mixte') NOT NULL,
    etat_produit VARCHAR(80) NOT NULL,
    stock INT NOT NULL DEFAULT 1,
    statut ENUM('en_attente','publie','vendu','refuse','archive') NOT NULL DEFAULT 'en_attente',
    date_creation DATETIME NOT NULL,
    date_modification DATETIME NOT NULL,
    FOREIGN KEY (id_vendeur) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_categorie) REFERENCES categories(id_categorie)
) ENGINE=InnoDB;

CREATE TABLE product_images (
    id_image INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT NOT NULL,
    chemin_image VARCHAR(255) NOT NULL,
    image_principale TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE carts (
    id_cart INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    statut ENUM('actif','valide','abandonne') NOT NULL DEFAULT 'actif',
    date_creation DATETIME NOT NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
    id_cart_item INT AUTO_INCREMENT PRIMARY KEY,
    id_cart INT NOT NULL,
    id_product INT NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_cart) REFERENCES carts(id_cart) ON DELETE CASCADE,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_product (id_cart, id_product)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id_order INT AUTO_INCREMENT PRIMARY KEY,
    id_acheteur INT NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    statut_commande ENUM('en_attente','payee','annulee','terminee') NOT NULL DEFAULT 'en_attente',
    date_commande DATETIME NOT NULL,
    adresse_livraison VARCHAR(255) NOT NULL,
    FOREIGN KEY (id_acheteur) REFERENCES users(id_user)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id_order_item INT AUTO_INCREMENT PRIMARY KEY,
    id_order INT NOT NULL,
    id_product INT NOT NULL,
    id_vendeur INT NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_order) REFERENCES orders(id_order) ON DELETE CASCADE,
    FOREIGN KEY (id_product) REFERENCES products(id_product),
    FOREIGN KEY (id_vendeur) REFERENCES users(id_user)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id_payment INT AUTO_INCREMENT PRIMARY KEY,
    id_order INT NOT NULL,
    methode VARCHAR(50) NOT NULL,
    statut_paiement ENUM('en_attente','accepte','refuse') NOT NULL DEFAULT 'en_attente',
    montant DECIMAL(10,2) NOT NULL,
    date_paiement DATETIME NULL,
    transaction_reference VARCHAR(80),
    FOREIGN KEY (id_order) REFERENCES orders(id_order) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE auctions (
    id_auction INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT NOT NULL UNIQUE,
    prix_depart DECIMAL(10,2) NOT NULL,
    prix_actuel DECIMAL(10,2) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut_enchere ENUM('en_cours','terminee','annulee') NOT NULL DEFAULT 'en_cours',
    id_gagnant INT NULL,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    FOREIGN KEY (id_gagnant) REFERENCES users(id_user)
) ENGINE=InnoDB;

CREATE TABLE bids (
    id_bid INT AUTO_INCREMENT PRIMARY KEY,
    id_auction INT NOT NULL,
    id_user INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_bid DATETIME NOT NULL,
    FOREIGN KEY (id_auction) REFERENCES auctions(id_auction) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id_user)
) ENGINE=InnoDB;

CREATE TABLE negotiations (
    id_negotiation INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT NOT NULL,
    id_acheteur INT NOT NULL,
    id_vendeur INT NOT NULL,
    prix_initial DECIMAL(10,2) NULL,
    prix_actuel DECIMAL(10,2) NOT NULL,
    statut_negociation ENUM('en_cours','acceptee','refusee','expiree','abandonnee') NOT NULL DEFAULT 'en_cours',
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NULL,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    FOREIGN KEY (id_acheteur) REFERENCES users(id_user),
    FOREIGN KEY (id_vendeur) REFERENCES users(id_user)
) ENGINE=InnoDB;

CREATE TABLE negotiation_messages (
    id_message INT AUTO_INCREMENT PRIMARY KEY,
    id_negotiation INT NOT NULL,
    id_sender INT NOT NULL,
    message VARCHAR(500),
    offre_prix DECIMAL(10,2) NOT NULL,
    date_message DATETIME NOT NULL,
    FOREIGN KEY (id_negotiation) REFERENCES negotiations(id_negotiation) ON DELETE CASCADE,
    FOREIGN KEY (id_sender) REFERENCES users(id_user)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id_notification INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    titre VARCHAR(180) NOT NULL,
    message VARCHAR(500) NOT NULL,
    type_notification VARCHAR(60) NOT NULL,
    lien VARCHAR(255),
    est_lue TINYINT(1) NOT NULL DEFAULT 0,
    date_creation DATETIME NOT NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE favorites (
    id_favorite INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_product INT NOT NULL,
    date_creation DATETIME NOT NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (id_user, id_product)
) ENGINE=InnoDB;

CREATE TABLE reports (
    id_report INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_product INT NOT NULL,
    motif VARCHAR(120) NOT NULL,
    description VARCHAR(500),
    statut ENUM('ouvert','traite','rejete') NOT NULL DEFAULT 'ouvert',
    date_report DATETIME NOT NULL,
    FOREIGN KEY (id_user) REFERENCES users(id_user),
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admin_logs (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL,
    action VARCHAR(120) NOT NULL,
    details VARCHAR(500),
    date_action DATETIME NOT NULL,
    FOREIGN KEY (id_admin) REFERENCES users(id_user)
) ENGINE=InnoDB;

INSERT INTO roles (nom_role, description) VALUES
('acheteur', 'Utilisateur qui achète, enchérit et négocie'),
('vendeur', 'Utilisateur qui publie des annonces et gère ses ventes'),
('admin', 'Utilisateur de gestion et de modération');

INSERT INTO users (id_role, nom, prenom, email, mot_de_passe, telephone, adresse, ville, code_postal, statut_compte, date_creation) VALUES
(3, 'Nova', 'Admin', 'admin@mercatonova.local', '$2y$12$D9jLJM68jxe5slAZF8lefu48FLCHa9XOmmk.47iC0r9KR2Cm5h2Wy', '0102030405', '1 Rue Centrale', 'Paris', '75000', 'actif', NOW()),
(2, 'Tual', 'Josselin', 'josselin.tual@mercatonova.local', '$2y$12$ncmhNNAh9JlKy3/JtJ3kC.PSgy2aa0Xs3lEbSSfwrejzz2vTFHxp.', '0600000001', '10 Avenue Marché', 'Paris', '75011', 'actif', NOW()),
(1, 'Durand', 'Alice', 'acheteur@mercatonova.local', '$2y$12$KvRyLk6zsTCjXTMGRi8Amey0aYN3quex/364Xf/P06gg2M8jQQwBa', '0600000002', '20 Rue Client', 'Lyon', '69000', 'actif', NOW()),
(2, 'Nabi', 'Mahel', 'mahel.nabi@mercatonova.local', '$2y$12$ncmhNNAh9JlKy3/JtJ3kC.PSgy2aa0Xs3lEbSSfwrejzz2vTFHxp.', '0600000003', '18 Rue des Halles', 'Paris', '75002', 'actif', NOW()),
(2, 'Remini', 'Gaspard', 'gaspard.remini@mercatonova.local', '$2y$12$ncmhNNAh9JlKy3/JtJ3kC.PSgy2aa0Xs3lEbSSfwrejzz2vTFHxp.', '0600000004', '42 Quai Nova', 'Lyon', '69002', 'actif', NOW()),
(2, 'Garica', 'Raphael', 'raphael.garica@mercatonova.local', '$2y$12$ncmhNNAh9JlKy3/JtJ3kC.PSgy2aa0Xs3lEbSSfwrejzz2vTFHxp.', '0600000005', '7 Place du Marché', 'Bordeaux', '33000', 'actif', NOW());

INSERT INTO categories (nom_categorie, description, image) VALUES
('Montres', 'Montres premium et objets horlogers', 'frontend/images/categories/category-montres.jpg'),
('Art', 'Objets d’art et décoration', 'frontend/images/categories/category-art.jpg'),
('High-tech', 'Objets connectés, gaming et accessoires', 'frontend/images/categories/category-high-tech.jpg'),
('Mode', 'Mode premium, sacs et sneakers', 'frontend/images/categories/category-mode.jpg'),
('Livres', 'Livres rares et éditions de collection', 'frontend/images/categories/category-livres.jpg'),
('Collection', 'Objets uniques, pièces rares et curiosités', 'frontend/images/categories/category-collection.jpg');

INSERT INTO products (id_vendeur, id_categorie, titre, description, prix_initial, prix_achat_immediat, type_vente, etat_produit, stock, statut, date_creation, date_modification) VALUES
(2, 1, 'Montre automatique Sello 5', 'Montre automatique en très bon état. Mouvement fiable, bracelet acier et présentation premium. Vente mixte : achat immédiat ou enchère.', 420.00, 890.00, 'mixte', 'Très bon état', 1, 'publie', NOW(), NOW()),
(4, 3, 'Casque audio studio Aura', 'Casque Bluetooth haut de gamme avec réduction de bruit, idéal pour le travail et le voyage.', 80.00, 149.00, 'achat_immediat', 'Bon état', 3, 'publie', NOW(), NOW()),
(5, 2, 'Lithographie signée atelier Nova', 'Œuvre numérotée, encadrée, proposée avec un mécanisme de négociation sécurisé.', 300.00, 450.00, 'negociation', 'Excellent état', 1, 'publie', NOW(), NOW()),
(6, 6, 'Pièce de collection rare', 'Pièce commémorative proposée exclusivement aux enchères avec historique complet des offres.', 95.00, NULL, 'enchere', 'Correct', 1, 'publie', NOW(), NOW()),
(2, 4, 'Sac cuir premium Atelier', 'Sac en cuir pleine fleur, style classique, compatible achat immédiat.', 140.00, 260.00, 'achat_immediat', 'Très bon état', 1, 'publie', NOW(), NOW()),
(4, 5, 'Livre ancien illustré', 'Edition ancienne illustrée, ouverte à la discussion via négociation.', 60.00, 120.00, 'negociation', 'Bon état', 1, 'publie', NOW(), NOW()),
(5, 3, 'iPhone 14 Pro 128 Go', 'Smartphone reconditionné, écran contrôlé, batterie vérifiée et achat immédiat.', 650.00, 899.00, 'achat_immediat', 'Très bon état', 2, 'publie', NOW(), NOW()),
(6, 3, 'MacBook Air M2', 'Ordinateur portable léger, parfait pour les cours et les projets web.', 950.00, 1290.00, 'achat_immediat', 'Excellent état', 1, 'publie', NOW(), NOW()),
(2, 3, 'Camera Canon EOS 600', 'Appareil photo polyvalent proposé aux enchères avec suivi complet des offres.', 350.00, NULL, 'enchere', 'Bon état', 1, 'publie', NOW(), NOW()),
(4, 3, 'Console Nova Play', 'Console de salon avec manette, produit high-tech disponible en achat direct.', 310.00, 499.00, 'achat_immediat', 'Très bon état', 2, 'publie', NOW(), NOW()),
(5, 4, 'Sneakers édition limitée', 'Paire rare, taille 42, vendue par enchère pour valoriser les objets de collection.', 180.00, NULL, 'enchere', 'Neuf', 1, 'publie', NOW(), NOW()),
(6, 2, 'Vase céramique artisan', 'Vase contemporain fait main, vente par négociation avec contre-offres.', 160.00, 280.00, 'negociation', 'Excellent état', 1, 'publie', NOW(), NOW()),
(2, 2, 'Lampe design laiton', 'Lampe de décoration premium, achat immédiat disponible.', 120.00, 230.00, 'achat_immediat', 'Très bon état', 1, 'publie', NOW(), NOW()),
(4, 2, 'Fauteuil lounge premium', 'Fauteuil design en attente de validation administrateur.', 420.00, 690.00, 'achat_immediat', 'Bon état', 1, 'en_attente', NOW(), NOW()),
(5, 1, 'Montre vintage vendue', 'Produit déjà vendu apparaissant dans l’historique des commandes.', 200.00, 320.00, 'achat_immediat', 'Bon état', 0, 'vendu', DATE_SUB(NOW(), INTERVAL 10 DAY), NOW());

INSERT INTO product_images (id_product, chemin_image, image_principale) VALUES
(1, 'frontend/images/produits/montre.jpg', 1),
(2, 'frontend/images/produits/casque.jpg', 1),
(3, 'frontend/images/produits/lithographie.jpg', 1),
(4, 'frontend/images/produits/piece-collection.jpg', 1),
(5, 'frontend/images/produits/sac.jpg', 1),
(6, 'frontend/images/produits/livre.jpg', 1),
(7, 'frontend/images/produits/iphone.jpg', 1),
(8, 'frontend/images/produits/macbook.jpg', 1),
(9, 'frontend/images/produits/camera.jpg', 1),
(10, 'frontend/images/produits/console.jpg', 1),
(11, 'frontend/images/produits/sneakers.jpg', 1),
(12, 'frontend/images/produits/vase.jpg', 1),
(13, 'frontend/images/produits/lampe.jpg', 1),
(14, 'frontend/images/produits/fauteuil.jpg', 1),
(15, 'frontend/images/produits/montre.jpg', 1),
(1, 'frontend/images/produits/sac.jpg', 0),
(7, 'frontend/images/produits/macbook.jpg', 0),
(9, 'frontend/images/produits/console.jpg', 0);

INSERT INTO auctions (id_product, prix_depart, prix_actuel, date_debut, date_fin, statut_enchere) VALUES
(1, 420.00, 520.00, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 3 DAY), 'en_cours'),
(4, 95.00, 155.00, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY), 'en_cours'),
(9, 350.00, 420.00, DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_ADD(NOW(), INTERVAL 5 DAY), 'en_cours'),
(11, 180.00, 230.00, DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 1 DAY), 'en_cours');

INSERT INTO bids (id_auction, id_user, montant, date_bid) VALUES
(1, 3, 460.00, DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(1, 3, 520.00, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(2, 3, 120.00, DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(2, 3, 155.00, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(3, 3, 420.00, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(4, 3, 230.00, DATE_SUB(NOW(), INTERVAL 15 MINUTE));

INSERT INTO negotiations (id_product, id_acheteur, id_vendeur, prix_initial, prix_actuel, statut_negociation, date_debut, date_fin) VALUES
(3, 3, 5, 450.00, 390.00, 'en_cours', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),
(12, 3, 6, 280.00, 235.00, 'en_cours', DATE_SUB(NOW(), INTERVAL 4 HOUR), NULL);

INSERT INTO negotiation_messages (id_negotiation, id_sender, message, offre_prix, date_message) VALUES
(1, 3, 'Bonjour, je suis intéressée par cette lithographie. Seriez-vous ouvert à 370 euros ?', 370.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 5, 'Bonjour, merci pour votre offre. Je peux descendre à 390 euros.', 390.00, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(2, 3, 'Le vase m’intéresse, je propose 220 euros.', 220.00, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(2, 6, 'Je peux accepter 235 euros si la vente est confirmée rapidement.', 235.00, DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO orders (id_acheteur, montant_total, statut_commande, date_commande, adresse_livraison) VALUES
(3, 320.00, 'payee', DATE_SUB(NOW(), INTERVAL 7 DAY), '20 Rue Client, 69000 Lyon');

INSERT INTO order_items (id_order, id_product, id_vendeur, quantite, prix_unitaire) VALUES
(1, 15, 5, 1, 320.00);

INSERT INTO payments (id_order, methode, statut_paiement, montant, date_paiement, transaction_reference) VALUES
(1, 'carte_fictive', 'accepte', 320.00, DATE_SUB(NOW(), INTERVAL 7 DAY), 'PAY-DEMO-0001');

INSERT INTO reports (id_user, id_product, motif, description, statut, date_report) VALUES
(3, 11, 'Authenticité à vérifier', 'Je souhaite que l’administrateur vérifie la description des sneakers.', 'ouvert', DATE_SUB(NOW(), INTERVAL 1 HOUR));

INSERT INTO notifications (id_user, titre, message, type_notification, lien, est_lue, date_creation) VALUES
(3, 'Bienvenue sur Mercato Nova', 'Votre compte acheteur est prêt.', 'compte', 'espace-client.php', 0, NOW()),
(3, 'Enchère suivie', 'Votre dernière offre sur une enchère est bien enregistrée.', 'enchere', 'mes-encheres.php', 0, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(3, 'Contre-offre reçue', 'Le vendeur a répondu à votre négociation.', 'negociation', 'mes-negociations.php', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'Espace vendeur actif', 'Vos annonces sont publiées.', 'compte', 'espace-vendeur.php', 0, NOW()),
(5, 'Nouvelle négociation', 'Un acheteur a lancé une négociation sur une lithographie.', 'negociation', 'mes-negociations.php', 0, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(6, 'Nouvelle contre-offre', 'Une négociation attend votre réponse.', 'negociation', 'mes-negociations.php', 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 'Administration prête', 'Vous pouvez modérer les annonces et gérer les utilisateurs.', 'admin', 'espace-admin.php', 0, NOW()),
(1, 'Nouveau signalement', 'Une annonce a été signalée et attend une vérification.', 'signalement', 'espace-admin.php', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR));

INSERT INTO favorites (id_user, id_product, date_creation) VALUES
(3, 1, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 7, DATE_SUB(NOW(), INTERVAL 1 HOUR));

INSERT INTO admin_logs (id_admin, action, details, date_action) VALUES
(1, 'demo_initialisation', 'Base de démonstration initialisée', NOW());
