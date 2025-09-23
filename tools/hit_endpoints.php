#!/usr/bin/env php
<?php
declare(strict_types=1);
$options = getopt('', ['base::','cookie::','control::','year::','stations::']);
$base = rtrim($options['base'] ?? 'http://localhost', '/');
$cookie = $options['cookie'] ?? '';
$control = isset($options['control']) ? (int)$options['control'] : 0;
$year = isset($options['year']) ? (int)$options['year'] : (int)date('Y');
$stationsOpt = $options['stations'] ?? '';
$stations = $stationsOpt !== '' ? array_filter(array_map('trim', explode(',', $stationsOpt))) : [];

function hit(string $base, string $path, array $params = [], string $cookie = ''): array {
    $url = $base . $path;
    if ($params) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($cookie !== '') {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cookie: ' . $cookie]);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['url'=>$url,'status'=>0,'error'=>$err];
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = substr($raw, $headerSize);
    return ['url'=>$url,'status'=>$status,'body'=>$body];
}

$report = [];
$report[] = ['endpoint'=>'filters_meta','response'=>hit($base, '/api/filters_meta.php', [], $cookie)];
if (!$stations && isset($report[0]['response']['body'])) {
    $decoded = json_decode($report[0]['response']['body'], true);
    if (is_array($decoded) && !empty($decoded['estaciones'])) {
        $stations = array_slice(array_map('strval', $decoded['estaciones']), 0, 3);
    }
}
$stationParam = $stations ? implode(',', $stations) : '';

$combos = [
    ['endpoint'=>'pecos_list persona','path'=>'/api/pecos_list.php','params'=>['mode'=>'persona','control'=>$control]],
    ['endpoint'=>'pecos_list anio','path'=>'/api/pecos_list.php','params'=>['mode'=>'anio','year'=>$year,'stations'=>$stationParam]],
    ['endpoint'=>'txt_list persona','path'=>'/api/txt_list.php','params'=>['mode'=>'persona','control'=>$control]],
    ['endpoint'=>'txt_list anio','path'=>'/api/txt_list.php','params'=>['mode'=>'anio','year'=>$year,'stations'=>$stationParam]],
    ['endpoint'=>'vacaciones_list persona','path'=>'/api/vacaciones_list.php','params'=>['mode'=>'persona','control'=>$control,'year'=>$year]],
    ['endpoint'=>'vacaciones_list anio','path'=>'/api/vacaciones_list.php','params'=>['mode'=>'anio','year'=>$year,'stations'=>$stationParam]],
    ['endpoint'=>'incapacidades_list persona','path'=>'/api/incapacidades_list.php','params'=>['mode'=>'persona','control'=>$control]],
    ['endpoint'=>'incapacidades_list anio','path'=>'/api/incapacidades_list.php','params'=>['mode'=>'anio','year'=>$year,'stations'=>$stationParam]],
];
foreach ($combos as $combo) {
    $report[] = ['endpoint'=>$combo['endpoint'],'response'=>hit($base, $combo['path'], $combo['params'], $cookie)];
}

foreach ($report as $entry) {
    $resp = $entry['response'];
    $status = $resp['status'] ?? 0;
    $label = $entry['endpoint'];
    echo sprintf("[%s] %s => HTTP %d\n", $status >= 200 && $status < 400 ? 'OK' : 'FAIL', $label, $status);
    if (!empty($resp['error'])) {
        echo "  Error: {$resp['error']}\n";
    } elseif ($resp['body'] ?? '' !== '') {
        $body = substr($resp['body'], 0, 200);
        $body = str_replace(["\r", "\n"], [' ', ' '], $body);
        echo "  Body: {$body}" . (strlen($resp['body']) > 200 ? '…' : '') . "\n";
    }
}
