<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Billing\Period;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Abonelik dönemi tarih hesapları.
 *
 * @internal
 */
final class PeriodTest extends CIUnitTestCase
{
    public function testMonthlyYearlyAndTrialEnds(): void
    {
        $this->assertSame('2026-11-30', Period::end('2026-11-01', 'monthly'));
        $this->assertSame('2026-12-14', Period::end('2026-11-15', 'monthly'));
        $this->assertSame('2027-10-31', Period::end('2026-11-01', 'yearly'));
        $this->assertSame('2026-11-14', Period::end('2026-11-01', 'trial', 14));
    }

    public function testMonthEndOverflowIsClamped(): void
    {
        // 31 Ocak + 1 ay → 28 Şubat (Mart'a taşmaz), dönem 27 Şubat'ta biter.
        $this->assertSame('2027-02-27', Period::end('2027-01-31', 'monthly'));
        // Artık yıl
        $this->assertSame('2028-02-28', Period::end('2028-01-31', 'monthly'));
        $this->assertSame('2029-02-27', Period::end('2028-02-29', 'yearly'));
    }

    public function testDayArithmetic(): void
    {
        $this->assertSame('2026-12-01', Period::addDays('2026-11-30', 1));
        $this->assertSame('2026-11-24', Period::addDays('2026-12-01', -7));
        $this->assertSame(7, Period::daysBetween('2026-11-23', '2026-11-30'));
        $this->assertSame(-1, Period::daysBetween('2026-12-01', '2026-11-30'));
        $this->assertTrue(Period::isValidDate('2026-02-28'));
        $this->assertFalse(Period::isValidDate('2026-02-30'));
        $this->assertFalse(Period::isValidDate('30.11.2026'));
    }
}
