<?php
define('APP_DEBUG', true);
if (!defined('APP_LOADED')) {
    define('APP_LOADED', true);
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/config/session.php';
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/core/security.php';
    require_once __DIR__ . '/core/validators.php';
    require_once __DIR__ . '/core/auth.php';
    require_once __DIR__ . '/core/permissions.php';
    require_once __DIR__ . '/core/upload.php';
    require_once __DIR__ . '/core/functions.php';
}
