<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/vacaciones_calc.php';
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}
