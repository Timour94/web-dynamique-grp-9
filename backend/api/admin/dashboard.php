<?php
require_once __DIR__ . '/../init.php';
require_role('admin');
json_response(['success' => true, 'stats' => get_dashboard_stats()]);
