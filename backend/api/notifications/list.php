<?php
require_once __DIR__ . '/../init.php';
require_login();
json_response(['success' => true, 'notifications' => get_notifications(current_user_id())]);
