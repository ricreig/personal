<?php
// === Helpers de assets (versionado por filemtime y URLs correctas) ===

if (!function_exists('asset__project_root')) {
  function asset__project_root(): string {
    // __DIR__ = /unificado/public/includes → project_root = /unificado/public
    return dirname(__DIR__);
  }
}

if (!function_exists('asset__web_base')) {
  function asset__web_base(): string {
    // Detecta si ya estamos sirviendo desde /public (p.ej. /public/login.php)
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $scriptDir = rtrim(str_replace('\\','/', dirname($script)), '/');  // p.ej. /public
    // Si la ruta del script YA empieza con /public → base vacía (para no duplicar /public)
    if (strpos($scriptDir, '/public') === 0) {
      return '';            // genera /assets/...
    }
    // Si el host apunta a /unificado (raíz) y rediriges a /public/index.php,
    // los assets deben prefix con /public
    return '/public';        // genera /public/assets/...
  }
}

if (!function_exists('asset__add_or_replace_query')) {
  function asset__add_or_replace_query(string $url, string $key, string $value): string {
    $parts = parse_url($url); $query = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $query);
    $query[$key] = $value; $parts['query'] = http_build_query($query);
    $path = $parts['path'] ?? '';
    $q    = $parts['query'] ? '?' . $parts['query'] : '';
    $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return $path.$q.$frag;
  }
}



if (!function_exists('css_tag')) {
  function css_tag($item): void {
    if (is_string($item)) {
      echo '<link rel="stylesheet" href="'.asset_version($item).'">'.PHP_EOL;
    } elseif (is_array($item)) {
      $href = asset_version($item['href'] ?? '');
      $int  = !empty($item['integrity'])      ? ' integrity="'.$item['integrity'].'"'           : '';
      $cr   = !empty($item['crossorigin'])    ? ' crossorigin="'.$item['crossorigin'].'"'       : '';
      $ref  = !empty($item['referrerpolicy']) ? ' referrerpolicy="'.$item['referrerpolicy'].'"' : '';
      echo '<link rel="stylesheet" href="'.$href.'"'.$int.$cr.$ref.'>'.PHP_EOL;
    }
  }
}

if (!function_exists('js_tag')) {
  function js_tag($item): void {
    if (is_string($item)) {
      echo '<script src="'.asset_version($item).'" defer></script>'.PHP_EOL;
    } elseif (is_array($item)) {
      $src = asset_version($item['src'] ?? '');
      $int = !empty($item['integrity'])      ? ' integrity="'.$item['integrity'].'"'            : '';
      $cr  = !empty($item['crossorigin'])    ? ' crossorigin="'.$item['crossorigin'].'"'        : '';
      $ref = !empty($item['referrerpolicy']) ? ' referrerpolicy="'.$item['referrerpolicy'].'"'  : '';
      $def = (isset($item['defer']) && $item['defer']) ? ' defer' : '';
      echo '<script src="'.$src.'"'.$int.$cr.$ref.$def.'></script>'.PHP_EOL;
    }
  }
}

