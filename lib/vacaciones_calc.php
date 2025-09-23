<?php
declare(strict_types=1);

const VC_BASE_VACACIONES = 20;
const VC_PR_ESCALAS = [
    20 => 10,
    17 => 9,
    14 => 8,
    11 => 7,
    8  => 6,
    5  => 5,
];
const VC_ANT_ESCALAS = [
    35 => 6,
    30 => 5,
    25 => 4,
    20 => 3,
    15 => 2,
    10 => 1,
];

function vc_normalize_date(?string $raw): ?string {
    if ($raw === null) {
        return null;
    }
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $raw)) {
        return $raw;
    }
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    return null;
}

function vc_years_elapsed(?string $inicio, string $fechaCorte): ?int {
    $inicio = vc_normalize_date($inicio);
    if ($inicio === null) {
        return null;
    }
    try {
        $start = new DateTimeImmutable($inicio);
        $cut = new DateTimeImmutable($fechaCorte);
    } catch (Throwable $e) {
        return null;
    }
    if ($start > $cut) {
        return 0;
    }
    return (int)$start->diff($cut)->y;
}

function vc_vac_asignadas(int $year): int {
    unset($year);
    return VC_BASE_VACACIONES;
}

function vc_ant_asignadas(?string $ingreso, string $fechaCorte): int {
    $years = vc_years_elapsed($ingreso, $fechaCorte);
    if ($years === null) {
        return 0;
    }
    foreach (VC_ANT_ESCALAS as $min => $dias) {
        if ($years >= $min) {
            return $dias;
        }
    }
    return 0;
}

function vc_pr_asignadas(?string $ingreso, ?string $puesto, string $fechaCorte): int {
    $puestoNorm = mb_strtoupper(trim((string)$puesto));
    if ($puestoNorm === '' || (mb_strpos($puestoNorm, 'CTA') === false && mb_strpos($puestoNorm, 'CONTROL') === false)) {
        return 0;
    }
    $years = vc_years_elapsed($ingreso, $fechaCorte);
    if ($years === null) {
        return 0;
    }
    foreach (VC_PR_ESCALAS as $min => $dias) {
        if ($years >= $min) {
            return $dias;
        }
    }
    return 0;
}

function vc_sum_movimientos_por_tipo(array $movimientos, int $year): array {
    $sum = ['VAC' => 0, 'PR' => 0, 'ANT' => 0];
    foreach ($movimientos as $row) {
        $rowYear = (int)($row['year'] ?? 0);
        if ($rowYear !== $year) {
            continue;
        }
        $tipo = strtoupper((string)($row['tipo'] ?? ''));
        if (!array_key_exists($tipo, $sum)) {
            continue;
        }
        $sum[$tipo] += (int)($row['dias'] ?? 0);
    }
    return $sum;
}

function vc_leftover(array $asignado, array $usado): array {
    $out = [];
    foreach ($asignado as $key => $value) {
        $used = (int)($usado[$key] ?? 0);
        $out[$key] = max(0, (int)$value - $used);
    }
    return $out;
}

function vc_flags_prev_year(array $persona, array $movimientos, int $year, ?DateTimeImmutable $now = null): array {
    $prevYear = $year - 1;
    $cutPrev = sprintf('%04d-12-31', $prevYear);
    $asignPrev = [
        'VAC' => vc_vac_asignadas($prevYear),
        'PR'  => vc_pr_asignadas($persona['ingreso'] ?? $persona['ant'] ?? null, $persona['puesto'] ?? $persona['tipo1'] ?? null, $cutPrev),
        'ANT' => vc_ant_asignadas($persona['ingreso'] ?? $persona['ant'] ?? null, $cutPrev),
    ];
    $usPrev = vc_sum_movimientos_por_tipo($movimientos, $prevYear);
    $leftPrev = vc_leftover($asignPrev, $usPrev);
    $totalPrev = array_sum($leftPrev);
    if ($totalPrev <= 0) {
        return [];
    }
    $flags = ['warning'];
    $now ??= new DateTimeImmutable('now');
    $windowStart = new DateTimeImmutable(sprintf('%04d-01-01', $year));
    $windowEnd = new DateTimeImmutable(sprintf('%04d-06-30 23:59:59', $year));
    if ($now >= $windowStart && $now <= $windowEnd) {
        $flags[] = 'danger';
    }
    return $flags;
}

function vc_format_control($control): string {
    return str_pad((string)$control, 4, '0', STR_PAD_LEFT);
}

function vc_summary(array $persona, array $movimientos, int $year): array {
    $cutoff = sprintf('%04d-12-31', $year);
    $asignado = [
        'VAC' => vc_vac_asignadas($year),
        'PR'  => vc_pr_asignadas($persona['ingreso'] ?? $persona['ant'] ?? null, $persona['puesto'] ?? $persona['tipo1'] ?? null, $cutoff),
        'ANT' => vc_ant_asignadas($persona['ingreso'] ?? $persona['ant'] ?? null, $cutoff),
    ];
    $usado = vc_sum_movimientos_por_tipo($movimientos, $year);
    $left = vc_leftover($asignado, $usado);
    $flags = vc_flags_prev_year($persona, $movimientos, $year);
    return [
        'year'        => $year,
        'estacion'    => $persona['estacion'] ?? $persona['oaci'] ?? '',
        'control_fmt' => vc_format_control($persona['control'] ?? ''),
        'control'     => (int)($persona['control'] ?? 0),
        'nombres'     => $persona['nombres'] ?? '',
        'dias_asig'   => $asignado['VAC'],
        'pr_asig'     => $asignado['PR'],
        'ant_asig'    => $asignado['ANT'],
        'dias_usados' => $usado['VAC'],
        'pr_usados'   => $usado['PR'],
        'ant_usados'  => $usado['ANT'],
        'dias_left'   => $left['VAC'],
        'pr_left'     => $left['PR'],
        'ant_left'    => $left['ANT'],
        'flags'       => $flags,
    ];
}
