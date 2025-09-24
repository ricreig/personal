#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/write_out.php';

$root = dirname(__DIR__);
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$hits = [];
foreach ($rii as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php') {
        continue;
    }
    $rel = substr($path, strlen($root) + 1);
    if ($rel === 'tools/scan_oaci_estacion.php') {
        continue;
    }
    $code = file_get_contents($path);
    if ($code === false) {
        continue;
    }
    $pattern = '/\be\.oaci\b/';
    if (!preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($matches[0] as $match) {
        $offset = $match[1];
        $line = substr_count(substr($code, 0, $offset), "\n") + 1;
        $hits[] = [$rel, $line];
    }
}

if (!$hits) {
    writeOut("No e.oaci usages found." . PHP_EOL);
    exit(0);
}
foreach ($hits as [$file, $line]) {
    writeOut(sprintf("%s:%d -> revisar, usar e.estacion + JOIN estaciones\n", $file, $line));
}
exit(1);
