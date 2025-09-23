<?php
declare(strict_types=1);
// /unificado/lib/db.php
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $db_host = "mysql.hostinger.mx";
  $db_user = "u695435470_reg";
  $db_pass = "basededatos";
  $db_name = "u695435470_reg";

  $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
  $opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
  ];
  $pdo = new PDO($dsn, $db_user, $db_pass, $opt);
  return $pdo;
}