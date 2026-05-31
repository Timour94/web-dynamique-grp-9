<?php
require_once __DIR__ . '/../init.php';
require_login();
json_response(['success' => true, 'orders' => get_user_orders(current_user_id())]);
