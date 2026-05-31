# Rapport de compromis techniques et décisions de conception

## Objectif
Le projet Mercato Nova a été conçu comme une application web dynamique complète, mais avec une complexité maîtrisée afin de rester maintenable et explicable pendant la soutenance.

## Compromis principaux

- Le paiement est simulé afin de respecter le cadre pédagogique et d'éviter la gestion de données bancaires réelles.
- Le catalogue utilise React pour améliorer l'expérience utilisateur, mais conserve un rendu serveur PHP en fallback.
- Les enchères sont gérées avec un modèle simple mais cohérent : prix actuel, historique des offres, date de fin et gagnant automatique.
- La négociation est limitée à un nombre maximal d'échanges afin d'éviter les discussions infinies et de clarifier les états métier.
- Les images du projet sont locales pour garantir que la démonstration fonctionne sans dépendre d'images externes.

## Sécurité minimale

- Connexion par sessions PHP.
- Mots de passe hashés avec `password_hash`.
- Requêtes SQL préparées avec PDO.
- Jetons CSRF sur les formulaires sensibles.
- Échappement HTML avec `htmlspecialchars`.
- Contrôles de rôles pour les pages acheteur, vendeur et administrateur.

## Limites actuelles

- Le paiement n'est pas relié à un prestataire réel.
- Les notifications sont internes à l'application, sans email externe.
- La messagerie de négociation est volontairement simplifiée.
- La gestion d'images multiples est présente côté affichage, mais l'upload vendeur reste volontairement simple.
