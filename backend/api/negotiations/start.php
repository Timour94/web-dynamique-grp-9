<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$result = start_negotiation((int)($_POST['id_product'] ?? 0), current_user_id(), (float)str_replace(',', '.', $_POST['offre_prix'] ?? 0), $_POST['message'] ?? '');
finish_response($result['success'], $result['message'], $result['success'] ? 'negotiation.php?id=' . (int)$result['id_negotiation'] : 'catalogue.php');
