<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/guard.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/db.php';

if (!is_admin()) { http_response_code(403); exit('Solo admin'); }
$pdo = db();
$user_id = (int)($_POST['user_id'] ?? 0);
$perm = $_POST['perm'] ?? [];
if ($user_id<=0) { header('Location: permisos.php'); exit; }

$pdo->beginTransaction();
$st=$pdo->prepare("DELETE FROM user_station_perms WHERE user_id=?"); $st->execute([$user_id]);
$ins=$pdo->prepare("INSERT INTO user_station_perms (user_id,oaci,can_view,can_edit) VALUES (?,?,?,?)");
foreach ($perm as $oaci=>$flags) {
  $view = !empty($flags['view']) ? 1 : 0;
  $edit = !empty($flags['edit']) ? 1 : 0;
  if ($view || $edit) $ins->execute([$user_id,$oaci,$view,$edit]);
}
$pdo->commit();
header('Location: permisos.php?user_id='.$user_id.'&ok=1');