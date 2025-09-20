<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/db.php';
$sql = file_get_contents(__DIR__.'/setup.sql');
try { $pdo->exec($sql); echo "OK: Tablas creadas/actualizadas\n"; }
catch (Throwable $e) { http_response_code(500); echo "ERROR: ".$e->getMessage(); }