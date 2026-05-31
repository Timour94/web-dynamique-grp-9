<?php
require_once __DIR__ . '/../init.php';
verify_csrf();
$result = register_user($_POST);
if ($result['success']) { finish_response(true, 'Compte créé.', 'espace-client.php'); }
finish_response(false, implode(' ', $result['errors']), 'inscription.php');
