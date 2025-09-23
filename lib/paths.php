<?php
declare(strict_types=1);
function cr_web_paths(): array {
  $script = $_SERVER['SCRIPT_NAME'] ?? '/';
  // ejemplos:
  //   /unificado/public/index.php
  //   /unificado/admin/usuarios.php
  //   /public/index.php  (si el vhost apunta directo)
  $parts = explode('/', trim($script, '/'));

  $ixPublic = array_search('public', $parts, true);
  $ixAdmin  = array_search('admin',  $parts, true);

  if ($ixPublic !== false) {
    // /algo/.../public/xxx.php → PROJECT_BASE = hasta antes de "public"
    $proj = array_slice($parts, 0, $ixPublic);
  } elseif ($ixAdmin !== false) {
    // /algo/.../admin/xxx.php  → PROJECT_BASE = hasta antes de "admin"
    $proj = array_slice($parts, 0, $ixAdmin);
  } else {
    // fallback: carpeta padre del script
    $proj = array_slice($parts, 0, max(count($parts)-1, 0));
  }

  $PROJECT_BASE = '/' . (count($proj) ? implode('/', $proj) . '/' : '');
  $BASE_URL     = $PROJECT_BASE . 'public/';

  // Detectar ubicación de /api por filesystem (root vs public)
  $ROOT = dirname(__DIR__);            // .../unificado
  $apiInRoot   = is_dir($ROOT . '/api');
  $apiInPublic = is_dir($ROOT . '/public/api');

  if ($apiInRoot)      { $API_BASE = $PROJECT_BASE . 'api/'; }
  elseif ($apiInPublic){ $API_BASE = $BASE_URL . 'api/'; }
  else                 { $API_BASE = $PROJECT_BASE . 'api/'; } // fallback

  return [$PROJECT_BASE, $BASE_URL, $API_BASE];
}

/** Define constantes PHP BASE_URL y API_BASE si aún no existen (útil para redirecciones server-side) */
function cr_define_php_paths(): void {
  [, $BASE_URL, $API_BASE] = cr_web_paths();
  if (!defined('BASE_URL')) define('BASE_URL', $BASE_URL);
  if (!defined('API_BASE')) define('API_BASE', $API_BASE);
}

/** Expone window.BASE_URL y window.API_BASE en la página (para JS) */
function cr_print_js_globals(): void {
  [, $BASE_URL, $API_BASE] = cr_web_paths();
  echo '<script>';
  echo 'window.BASE_URL=' . json_encode($BASE_URL) . ';';
  echo 'window.API_BASE=' . json_encode($API_BASE) . ';';
  echo '</script>';
}

/**
 * Versionador de assets:
 * - Si $path comienza con "http" → lo deja tal cual
 * - Si empieza con "/" → lo toma absoluto y NO le antepone BASE_URL
 * - En cualquier otro caso → lo cuelga de BASE_URL
 * - Para calcular el mtime, usa parse_url($url, PATH) y quita sólo el prefijo PROJECT_BASE (anclado ^)
 */
function asset_version(string $path): string {
  if (preg_match('#^https?://#i', $path)) return $path;

  [$PROJECT_BASE, $BASE_URL] = cr_web_paths();

  // Normalizar URL pública
  if ($path !== '' && $path[0] === '/') {
    $url = preg_replace('#/+#', '/', $path);
  } else {
    $url = preg_replace('#/+#', '/', $BASE_URL . $path);
  }

  // Quitar query para resolver en FS
  $urlPath = parse_url($url, PHP_URL_PATH) ?? $url;

  // Construir ruta física: ROOT + (urlPath - PROJECT_BASE)
  $ROOT = dirname(__DIR__); // .../unificado
  $prefix = '#^' . preg_quote($PROJECT_BASE, '#') . '#';
  $relative = ltrim(preg_replace($prefix, '', $urlPath), '/'); // sólo recorta el prefijo inicial

  $phys = $ROOT . '/' . $relative;

  if (is_file($phys)) {
    $v = (int)@filemtime($phys);
    $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . $v;
  }
  return $url;
}

/** Helpers de render */
function render_css(array $hrefs): void {
  foreach ($hrefs as $h) {
    echo '<link rel="stylesheet" href="' . htmlspecialchars($h) . '">' . "\n";
  }
}
function render_js(array $srcs): void {
  foreach ($srcs as $s) {
    echo '<script src="' . htmlspecialchars($s) . '"></script>' . "\n";
  }
}
