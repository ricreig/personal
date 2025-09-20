<?php
declare(strict_types=1);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/session.php';  session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';
require_once $ROOT . '/lib/guard.php';
require_once $ROOT . '/lib/paths.php';    // define BASE_URL y API_BASE