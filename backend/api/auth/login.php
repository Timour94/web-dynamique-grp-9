<?php
require_once __DIR__ . '/../init.php';
verify_csrf();
$ok = login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
finish_response($ok, $ok ? 'Connexion réussie.' : 'Identifiants invalides.', $ok ? 'espace-client.php' : 'connexion.php');
