<?php
require_once __DIR__ . '/../init.php';
require_role('admin');
close_ended_auctions();
finish_response(true, 'Enchères expirées clôturées.', 'espace-admin.php');
