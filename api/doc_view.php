<?php
declare(strict_types=1);

/**
 * Sirve una vista previa (imagen/pdf) o HEAD para existencia.
 * Uso desde app.js:
 *  - GET  /api/doc_view.php?control=1234&tipo=licencia1
 *  - HEAD /api/doc_view.php?control=1234&tipo=examen_medico
 *  - GET  ...&pdf=1  (fuerza attachment)
 */

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/bootstrap_public.php';

$u = auth_user();
if (!$u) { http_response_code(401); exit; }

$control = trim((string)($_GET['control'] ?? ''));
$tipo    = trim((string)($_GET['tipo'] ?? ''));
$pdfDl   = isset($_GET['pdf']);

if ($control === '' || $tipo === '') { http_response_code(400); exit('Bad request'); }

$pdo = db();
$st = $pdo->prepare("SELECT file_path, mime FROM documentos_personal WHERE control=? AND tipo=? LIMIT 1");
$st->execute([$control, $tipo]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit; }

$abs = $row['file_path'];
if (!preg_match('~^/~', $abs)) {
  // si se guardó relativo, resolver contra /unificado/docs
  $abs = $ROOT . '/docs/' . ltrim($abs, '/');
}
if (!is_file($abs)) { http_response_code(404); exit; }

$mime = $row['mime'] ?: 'application/octet-stream';
$filename = basename($abs);

// HEAD rápido
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($abs));
  header('Cache-Control: private, max-age=60');
  exit;
}

// GET: inline por defecto, o attachment si ?pdf=1
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=60');
$disp = $pdfDl ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($filename) . '"');

$fp = fopen($abs, 'rb');
fpassthru($fp);