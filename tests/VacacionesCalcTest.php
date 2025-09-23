<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VacacionesCalcTest extends TestCase
{
    public function testVacAsignadasEsConstante(): void
    {
        $this->assertSame(20, vc_vac_asignadas(2020));
        $this->assertSame(20, vc_vac_asignadas(2035));
    }

    public function testAntAsignadasPorUmbral(): void
    {
        $this->assertSame(0, vc_ant_asignadas('2020-01-01', '2024-12-31'));
        $this->assertSame(1, vc_ant_asignadas('2010-01-01', '2024-12-31'));
        $this->assertSame(3, vc_ant_asignadas('2000-01-01', '2024-12-31'));
        $this->assertSame(6, vc_ant_asignadas('1980-01-01', '2024-12-31'));
    }

    public function testPrAsignadasSoloParaCta(): void
    {
        $this->assertSame(0, vc_pr_asignadas('2010-01-01', 'Operativo', '2024-12-31'));
        $this->assertSame(5, vc_pr_asignadas('2018-01-01', 'CTA III', '2024-12-31'));
        $this->assertSame(10, vc_pr_asignadas('2000-01-01', 'CTA III', '2024-12-31'));
    }

    public function testResumenCalculaRestantes(): void
    {
        $persona = [
            'control' => 1234,
            'nombres' => 'Test Persona',
            'estacion' => 'MMMX',
            'ant' => '2000-01-01',
            'ingreso' => '2000-01-01',
            'puesto' => 'CTA III',
        ];
        $movimientos = [
            ['year' => 2024, 'tipo' => 'VAC', 'dias' => 5],
            ['year' => 2024, 'tipo' => 'PR', 'dias' => 2],
            ['year' => 2024, 'tipo' => 'ANT', 'dias' => 1],
            ['year' => 2023, 'tipo' => 'VAC', 'dias' => 20],
        ];
        $summary = vc_summary($persona, $movimientos, 2024);
        $this->assertSame(20, $summary['dias_asig']);
        $this->assertSame(10, $summary['pr_asig']);
        $this->assertSame(3, $summary['ant_asig']);
        $this->assertSame(15, $summary['dias_left']);
        $this->assertSame(8, $summary['pr_left']);
        $this->assertSame(2, $summary['ant_left']);
        $this->assertIsArray($summary['flags']);
    }

    public function testFlagsPrevYearDetectaPendientes(): void
    {
        $persona = [
            'control' => 555,
            'nombres' => 'Persona X',
            'estacion' => 'MMMX',
            'ant' => '2015-01-01',
            'ingreso' => '2015-01-01',
            'puesto' => 'Operativo',
        ];
        $movimientos = [
            ['year' => 2023, 'tipo' => 'VAC', 'dias' => 5],
        ];
        $flags = vc_flags_prev_year($persona, $movimientos, 2024, new DateTimeImmutable('2024-03-01'));
        $this->assertSame(['warning', 'danger'], $flags);
    }
}
