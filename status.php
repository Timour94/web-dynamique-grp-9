<?php
require_once __DIR__ . '/../init.php';
$auctionId = (int)($_GET['id_auction'] ?? 0);
$status = $auctionId > 0 ? get_auction_status($auctionId) : null;
if (!$status) {
    json_response(['success' => false, 'message' => 'Enchère introuvable.'], 404);
}
json_response(['success' => true, 'auction' => $status]);
