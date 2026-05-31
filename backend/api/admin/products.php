<?php
require_once __DIR__ . '/../init.php';
require_role('admin');
json_response(['success' => true, 'pending' => get_pending_products(), 'reports' => get_reports()]);
