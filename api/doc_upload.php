<?php
declare(strict_types=1);

/**
 * Recibe un archivo y lo asocia a (control, tipo).
 * Crea/actualiza registro en `documentos_personal`.
 * Guarda físicamente bajo /unificado/docs/{control}/
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/bootstrap_public.php';

$u = auth_user();
if (!$u) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }

$pdo = db();

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Método no permitido', 405);

  $control = trim((string)($_POST['control'] ?? ''));
  $tipo    = trim((string)($_POST['tipo'] ?? ''));
  $multi   = !empty($_POST['multi']);

  if ($control === '' || $tipo === '') throw new RuntimeException('control/tipo requeridos', 400);
  if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Archivo faltante', 400);

  $f = $_FILES['file'];
  $tmp = $f['tmp_name'];
  $orig = $f['name'];
  $size = (int)$f['size'];

  if ($size <= 0 || $size > 25*1024*1024) throw new RuntimeException('Tamaño inválido (máx 25MB)', 400);

  // MIME permitido (imágenes comunes y PDF)
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string)($fi->file($tmp) ?: 'application/octet-stream');
  $okMime = ['image/webp','image/jpeg','image/png','application/pdf'];
  if (!in_array($mime, $okMime, true)) throw new RuntimeException('MIME no permitido: ' . $mime, 415);

  // Carpeta destino
  $dir = $ROOT . '/docs/' . $control;
  if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('No se pudo crear carpeta');

  // Nombre seguro
  $base = preg_replace('~[^a-zA-Z0-9._-]+~', '_', $orig);
  if ($base === '' || $base === '_') $base = $tipo . '_' . time();
  // Prefijo por tipo para evitar choques
  $base = $tipo . '_' . $base;

  $dest = $dir . '/' . $base;

  if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('No se pudo mover archivo');

  $hash = sha1_file($dest) ?: '';
  $width = $height = null;

  if (str_starts_with($mime, 'image/')) {
    [$w,$h] = @getimagesize($dest) ?: [null,null];
    $width = $w; $height = $h;
  }

  // Si la tabla no existe, créala de forma mínima compatible
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS documentos_personal (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      control VARCHAR(10) NOT NULL,
      tipo VARCHAR(40) NOT NULL,
      file_path VARCHAR(255) NOT NULL,
      thumb_path VARCHAR(255) DEFAULT NULL,
      mime VARCHAR(64) NOT NULL,
      size_bytes INT UNSIGNED NOT NULL,
      width INT UNSIGNED DEFAULT NULL,
      height INT UNSIGNED DEFAULT NULL,
      hash_sha1 CHAR(40) NOT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      updated_by VARCHAR(80) DEFAULT NULL,
      UNIQUE KEY u_control_tipo (control, tipo),
      KEY idx_control (control)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // Guardar registro; si $multi, generamos tipo con sufijo único para no pisar
  $saveTipo = $tipo;
  if ($multi) { $saveTipo = $tipo . '_multi_' . time(); }

  // Para path almacenar relativo a /unificado/docs
  $rel = $control . '/' . basename($dest);

  $ins = $pdo->prepare("
    INSERT INTO documentos_personal
    (control,tipo,file_path,thumb_path,mime,size_bytes,width,height,hash_sha1,updated_by)
    VALUES (?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
      file_path=VALUES(file_path),
      thumb_path=VALUES(thumb_path),
      mime=VALUES(mime),
      size_bytes=VALUES(size_bytes),
      width=VALUES(width),
      height=VALUES(height),
      hash_sha1=VALUES(hash_sha1),
      updated_by=VALUES(updated_by),
      updated_at=CURRENT_TIMESTAMP
  ");

  $who = ($u['email'] ?? $u['nombre'] ?? 'user');
  $ins->execute([
    $control, $saveTipo, $rel, null, $mime, $size, $width, $height, $hash, $who
  ]);

  echo json_encode(['ok'=>true, 'tipo'=>$saveTipo, 'path'=>$rel], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $code = (int)$e->getCode();
  if ($code < 400 || $code > 599) $code = 500;
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}