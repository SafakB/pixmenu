<?php

declare(strict_types=1);

namespace App\Libraries\Billing;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Abonelik dönemi tarih hesapları. Tüm tarihler 'Y-m-d' dizesidir.
 */
final class Period
{
    public const CYCLES = ['trial', 'monthly', 'yearly'];

    /**
     * Dönemin son günü (dahil).
     *  - monthly: başlangıç + 1 ay − 1 gün (ay sonu taşması kırpılır: 31 Ocak başlangıçlı dönem 27/28 Şubat'ta biter)
     *  - yearly : başlangıç + 12 ay − 1 gün
     *  - trial  : başlangıç + trialDays − 1 gün
     */
    public static function end(string $start, string $cycle, ?int $trialDays = null): string
    {
        $date = self::parse($start);

        return match ($cycle) {
            'monthly' => self::addMonths($date, 1)->modify('-1 day')->format('Y-m-d'),
            'yearly'  => self::addMonths($date, 12)->modify('-1 day')->format('Y-m-d'),
            'trial'   => $date->modify('+' . max(1, (int) $trialDays) . ' days')->modify('-1 day')->format('Y-m-d'),
            default   => throw new InvalidArgumentException('Geçersiz dönem: ' . $cycle),
        };
    }

    public static function addDays(string $date, int $days): string
    {
        return self::parse($date)->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    /**
     * $to − $from (gün). $to önceyse negatif.
     */
    public static function daysBetween(string $from, string $to): int
    {
        $diff = self::parse($from)->diff(self::parse($to));

        return (int) $diff->days * ($diff->invert === 1 ? -1 : 1);
    }

    public static function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    public static function today(): string
    {
        return date('Y-m-d');
    }

    private static function parse(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false) {
            throw new InvalidArgumentException('Geçersiz tarih: ' . $date);
        }

        return $parsed;
    }

    /**
     * Ay ekler; hedef ayda gün yoksa ayın son gününe kırpar (31 Ocak + 1 ay = 28/29 Şubat).
     */
    private static function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $day   = (int) $date->format('j');
        $first = $date->modify('first day of this month')->modify('+' . $months . ' months');
        $last  = (int) $first->format('t');

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, $last));
    }
}
