<?php
require_once __DIR__ . '/backend/bootstrap.php';
require_login();
$pageTitle = 'Mon espace - Mercato Nova';
$user = current_user();
$orders = get_user_orders(current_user_id());
$bids = get_user_bids(current_user_id());
$negs = get_user_negotiations(current_user_id());
require_once __DIR__ . '/partials/header.php';
?>
