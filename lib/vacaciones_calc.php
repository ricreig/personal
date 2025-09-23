<?php
declare(strict_types=1);
/** Helpers para cálculo de vacaciones/ANT/PR */
function vc_normalize_date(?string $d): ?string {
  $d = trim((string)$d);
  if ($d==='') return null;
  // soporta DD/MM/YYYY o YYYY-MM-DD -> regresa YYYY-MM-DD
  if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $d)) {
    [$dd,$mm,$yy] = explode('/', $d);
    return sprintf('%04d-%02d-%02d', (int)$yy,(int)$mm,(int)$dd);
  }
  if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $d)) return $d;
  return null;
}

/** Días por antigüedad con base a años cumplidos al YYYY-mm-dd ($fechaCorte) */
function vc_dias_ant(?string $ant, string $fechaCorte): string {
  $ant = vc_normalize_date($ant);
  if (!$ant) return 'NO INFO';
  try {
    $d1 = new DateTime($ant);
    $d2 = new DateTime($fechaCorte);
  } catch (Exception $e) { return 'NO INFO'; }
  $years = (int)$d1->diff($d2)->y;
  if ($years >= 35) return '6';
  if ($years >= 30) return '5';
  if ($years >= 25) return '4';
  if ($years >= 20) return '3';
  if ($years >= 15) return '2';
  if ($years >= 10) return '1';
  return '-';
}

/** Periodo de Recuperación (PR) solo para CTA III, con base a años cumplidos */
function vc_dias_pr(?string $ant, ?string $especialidad, string $fechaCorte): string {
  if (trim((string)$especialidad) !== 'CTA III') return '-';
  $ant = vc_normalize_date($ant);
  if (!$ant) return 'NO INFO';
  try {
    $d1 = new DateTime($ant);
    $d2 = new DateTime($fechaCorte);
  } catch (Exception $e) { return 'NO INFO'; }
  $years = (int)$d1->diff($d2)->y;
  if ($years >= 20) return '10';
  if ($years >= 17) return '9';
  if ($years >= 14) return '8';
  if ($years >= 11) return '7';
  if ($years >= 8)  return '6';
  if ($years >= 5)  return '5';
  return '-';
}

/** Calcula restantes (si $base es '-' o 'NO INFO' retorna '-') */
function vc_restantes($base, int $usados): string {
  if ($base === '-' || $base === 'NO INFO') return '-';
  $b = (int)$base;
  $r = max(0, $b - max(0,$usados));
  return (string)$r;
}
