<?php
declare(strict_types=1);
require_once dirname(__DIR__). '/lib/db.php';

function mkuser(PDO $pdo, $email, $nombre, $role, $pass, $control=null, $active=1) {
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $st = $pdo->prepare("INSERT INTO app_users (email,nombre,pass_hash,role,control,is_active)
                       VALUES (?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), role=VALUES(role), control=VALUES(control), is_active=VALUES(is_active)");
  $st->execute([$email,$nombre,$hash,$role,$control,$active]);
  $st = $pdo->prepare("SELECT id FROM app_users WHERE email=?"); $st->execute([$email]);
  return (int)$st->fetchColumn();
}

try {
  $pdo->beginTransaction();
  $tempPass = 'Temporal#2025';

  // Super admin
  $id1 = mkuser($pdo, 'ricardo.reig@gmail.com', 'Super Admin', 'admin', $tempPass, null, 1);

  // JRTIJ regional
  $id2 = mkuser($pdo, 'ricardo.reig@seneam.gob.mx', 'Jefe Regional Tijuana', 'regional', $tempPass, null, 1);

  // Permisos para JRTIJ regional (MMTJ, MMML, MMPE, MMHO, MMGM)
  $pdo->prepare("DELETE FROM user_station_perms WHERE user_id=?")->execute([$id2]);
  $ins = $pdo->prepare("INSERT INTO user_station_perms (user_id,oaci,can_view,can_edit) VALUES (?,?,1,1)");
  foreach (['MMTJ','MMML','MMPE','MMHO','MMGM'] as $oaci) { $ins->execute([$id2,$oaci]); }

  $pdo->commit();
  echo "OK: usuarios creados/actualizados. Password temporal: Temporal#2025\n";
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo "ERROR: ".$e->getMessage();
}