#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$pattern = "/->\s*prepare\s*\(\s*([\"'])(.*?)\1/s";
$results = [];
foreach ($rii as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php') {
        continue;
    }
    $rel = substr($path, strlen($root) + 1);
    $code = file_get_contents($path);
    if ($code === false) {
        continue;
    }
    if (!preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($matches[2] as $match) {
        $sql = $match[0];
        if (strpos($sql, '?') === false) {
            continue;
        }
        $sqlNoConst = preg_replace('/::[a-z0-9_]+/i', '', $sql);
        if (strpos($sqlNoConst, ':') === false) {
            continue;
        }
        $offset = $match[1];
        $line = substr_count(substr($code, 0, $offset), "\n") + 1;
        $results[] = [$rel, $line, trim($sql)];
    }
}

if (!function_exists('writeOut')) {
    function writeOut(string $message): void
    {
        if (defined('STDOUT')) {
            fwrite(STDOUT, $message);
            return;
        }
        // Some PHP builds (e.g. cgi-fcgi) do not expose STDOUT.
        $handle = fopen('php://output', 'wb');
        if ($handle === false) {
            echo $message;
            return;
        }
        fwrite($handle, $message);
        fclose($handle);
    }
}

if (!$results) {
    writeOut("No mixed named/positional parameters found." . PHP_EOL);
    exit(0);
}
foreach ($results as [$file, $line, $sql]) {
    writeOut(sprintf("%s:%d\n%s\n\n", $file, $line, $sql));
}
exit(1);
