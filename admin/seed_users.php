<?php
declare(strict_types=1);
// /unificado/admin/seed_users.phprequire_once __DIR__ . '/../lib/db.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1) Tabla
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  rol ENUM('superadmin','regional','jefe_estacion','lector') NOT NULL DEFAULT 'lector',
  estaciones_json TEXT NULL,
  control VARCHAR(10) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "OK tabla users\n";

// 2) Usuarios iniciales (CAMBIA estos passwords antes de ejecutar)
$pass1 = '1234';
$pass2 = '1234';
$hash1 = password_hash($pass1, PASSWORD_DEFAULT);
$hash2 = password_hash($pass2, PASSWORD_DEFAULT);

// superadmin
$st = $pdo->prepare("INSERT INTO users (email, pass_hash, nombre, rol, estaciones_json, activo)
VALUES (:e, :h, :n, 'superadmin', '[]', 1)
ON DUPLICATE KEY UPDATE pass_hash=VALUES(pass_hash), nombre=VALUES(nombre), rol='superadmin', estaciones_json='[]', activo=1");
$st->execute([
  ':e' => 'ricardo.reig@gmail.com',
  ':h' => $hash1,
  ':n' => 'Ricardo Reig (Superadmin)',
]);

// regional JRTIJ
$est = json_encode(["MMTJ","MMML","MMPE","MMHO","MMGM"], JSON_UNESCAPED_UNICODE);
$st = $pdo->prepare("INSERT INTO users (email, pass_hash, nombre, rol, estaciones_json, activo)
VALUES (:e, :h, :n, 'regional', :est, 1)
ON DUPLICATE KEY UPDATE pass_hash=VALUES(pass_hash), nombre=VALUES(nombre), rol='regional', estaciones_json=VALUES(estaciones_json), activo=1");
$st->execute([
  ':e'   => 'ricardo.reig@seneam.gob.mx',
  ':h'   => $hash2,
  ':n'   => 'Ricardo Reig (Jefatura TIJ)',
  ':est' => $est,
]);

echo "Usuarios listos:\n";
echo " - superadmin: ricardo.reig@gmail.com / $pass1\n";
echo " - regional  : ricardo.reig@seneam.gob.mx / $pass2\n";
echo "⚠️ Cambia los passwords luego desde la UI y BORRA este archivo.\n";
