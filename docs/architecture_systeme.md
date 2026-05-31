# Architecture du système

Navigateur utilisateur
→ pages PHP / composants React / CSS / JS
→ backend PHP
→ requêtes SQL préparées via PDO
→ base MySQL `mercato_nova`
→ retour HTML ou JSON.

Le frontend et le backend sont séparés :
- `frontend/` contient CSS, JS, React et images.
- `backend/` contient configuration, sécurité, contrôleurs logiques et API.
- `database/` contient les scripts SQL.
