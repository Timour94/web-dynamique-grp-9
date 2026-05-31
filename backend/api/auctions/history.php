<?php
require_once __DIR__ . '/../init.php';
$auctionId = (int)($_GET['id_auction'] ?? 0);
json_response(['success' => true, 'bids' => get_bid_history($auctionId)]);
