<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$id = (int)($_POST['id_product'] ?? 0);
report_product(current_user_id(), $id, $_POST['motif'] ?? 'Signalement', $_POST['description'] ?? '');
finish_response(true, 'Signalement envoyé à la modération.', 'produit.php?id=' . $id);
