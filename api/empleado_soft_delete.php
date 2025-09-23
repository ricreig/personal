<?php
declare(strict_types=1);
// /api/empleado_soft_delete.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$ROOT = dirname(__DIR__);

require_once $ROOT . '/lib/session.php';
session_boot();
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';

function _json($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$ctrl = (int)($_GET['ctrl'] ?? 0);
if ($ctrl <= 0) { http_response_code(400); _json(['ok'=>false,'error'=>'ctrl requerido']); }

$pdo = db();
$pdo->beginTransaction();
try {
  // Obtener registro
  $st = $pdo->prepare("SELECT * FROM empleados WHERE control=? LIMIT 1");
  $st->execute([$ctrl]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { throw new Exception('No encontrado'); }

  // OACI para permiso
  $oaci = null;
  if (!empty($row['estacion'])) {
    $es = $pdo->prepare("SELECT oaci FROM estaciones WHERE id_estacion=? LIMIT 1");
    $es->execute([ (int)$row['estacion'] ]);
    $oaci = strtoupper((string)($es->fetchColumn() ?: ''));
  }
  $canEdit = true;
  if (function_exists('has_access_oaci')) {
    $canEdit = has_access_oaci((string)$oaci, true);
  } elseif (function_exists('user_station_matrix')) {
    $u = auth_user();
    $mat = user_station_matrix($pdo, (int)($u['id'] ?? 0));
    if (is_array($mat) && $mat) { $canEdit = !empty($mat[$oaci]); }
  }
  if (!$canEdit) { throw new Exception('Sin permiso'); }

  // Insertar en cambios_estacion
  $cols = array_keys($row);
  $insCols = implode(',', $cols);
  $place  = implode(',', array_fill(0, count($cols), '?'));
  $ins = $pdo->prepare("INSERT INTO cambios_estacion ($insCols) VALUES ($place)");
  $ins->execute(array_values($row));

  // Borrar de empleados
  $del = $pdo->prepare("DELETE FROM empleados WHERE control=? LIMIT 1");
  $del->execute([$ctrl]);

  $pdo->commit();
  _json(['ok'=>true]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(400);
  _json(['ok'=>false,'error'=>$e->getMessage()]);
}
