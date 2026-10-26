<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Holidays;

use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\LocalDate;

/**
 * A holiday a fixed number of days from Western (Gregorian) Easter Sunday.
 * Good Friday, a Pennsylvania legal holiday, is Easter minus two days.
 */
final readonly class EasterHoliday implements HolidayRule
{
    public function __construct(
        private string $name,
        private int $offsetDays,
        private string $citation,
        private ?int $firstYear = null,
    ) {
        if ($name === '') {
            throw new InvalidDeadlineArgument('A holiday needs a name.');
        }

        if ($offsetDays < -60 || $offsetDays > 60) {
            throw new InvalidDeadlineArgument('offsetDays must be between -60 and 60.');
        }
    }

    /**
     * Easter Sunday in the Gregorian calendar, by the anonymous Gregorian
     * algorithm (Meeus/Jones/Butcher), valid for every Gregorian year.
     */
    public static function easterSunday(int $year): LocalDate
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new LocalDate($year, $month, $day);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function citation(): string
    {
        return $this->citation;
    }

    public function occurrencesIn(int $year): array
    {
        if ($this->firstYear !== null && $year < $this->firstYear) {
            return [];
        }

        $date = self::easterSunday($year)->addDays($this->offsetDays);

        return [new Observance($this->name, $date, $date, $this->citation)];
    }
}
