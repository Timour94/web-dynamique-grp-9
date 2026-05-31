<?php
require_once __DIR__ . '/../init.php';
$count = is_logged_in() ? unread_notifications_count(current_user_id()) : 0;
json_response(['success' => true, 'count' => $count]);
