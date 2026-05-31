<?php
require_once __DIR__ . '/../init.php';
require_login();
verify_csrf();
$id = (int)($_POST['id_negotiation'] ?? 0);
$result = send_counter_offer($id, current_user_id(), (float)str_replace(',', '.', $_POST['offre_prix'] ?? 0), $_POST['message'] ?? '');
finish_response($result['success'], $result['message'], 'negotiation.php?id=' . $id);
