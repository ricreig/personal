<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/bootstrap_public.php';
// /unificado/api/bootstrap.php


// Forzar JSON siempre
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
}

// Manejo de errores → JSON (temporal para diagnóstico; luego puedes bajar el detalle)
set_error_handler(function($severity, $message, $file, $line){
  throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function(Throwable $e){
  http_response_code(500);
  $out = [
    'ok'    => false,
    'error' => $e->getMessage(),
  ];
  // Modo debug opcional ?debug=1 para ver traza
  if (!empty($_GET['debug'])) {
    $out['trace'] = $e->getTraceAsString();
    $out['file']  = $e->getFile();
    $out['line']  = $e->getLine();
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  // También registra al log del servidor
  error_log('[API] '. $e->getMessage() .' @ '. $e->getFile() .':'. $e->getLine());
});

$ROOT = dirname(__DIR__, 1);  // .../unificado
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/auth.php';
require_once $ROOT . '/lib/guard.php';

// (si este endpoint requiere login, valida aquí)
// $u = auth_user(); if (!$u) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit; }