<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines;

use DateTimeInterface;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use Stringable;

/**
 * A calendar date with no time and no timezone.
 *
 * Deadline arithmetic happens on these, never on timestamps, so a
 * daylight-saving transition (a 23- or 25-hour day) cannot move a deadline.
 * The day arithmetic is plain integer math on a proleptic Gregorian day
 * number (days since 1970-01-01), which needs no extension and no timezone.
 */
final readonly class LocalDate implements Stringable
{
    private const array MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
        if ($year < 1 || $year > 9999 || ! checkdate($month, $day, $year)) {
            throw new InvalidDeadlineArgument(sprintf('%04d-%02d-%02d is not a valid date.', $year, $month, $day));
        }
    }

    /**
     * Parses a strict `Y-m-d` string, such as `2026-07-03`.
     */
    public static function parse(string $date): self
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\z/', $date, $parts) !== 1) {
            throw new InvalidDeadlineArgument(sprintf('"%s" is not a date in Y-m-d form.', $date));
        }

        return new self((int) $parts[1], (int) $parts[2], (int) $parts[3]);
    }

    /**
     * The wall-clock date of the given moment in that moment's own timezone.
     * Convert the moment to the timezone you mean before calling this.
     */
    public static function of(DateTimeInterface $moment): self
    {
        return new self((int) $moment->format('Y'), (int) $moment->format('n'), (int) $moment->format('j'));
    }

    public static function fromDayNumber(int $dayNumber): self
    {
        // Inverse of toDayNumber(); see Howard Hinnant, "chrono-Compatible
        // Low-Level Date Algorithms", civil_from_days.
        $z = $dayNumber + 719468;
        $era = intdiv($z >= 0 ? $z : $z - 146096, 146097);
        $dayOfEra = $z - $era * 146097;
        $yearOfEra = intdiv($dayOfEra - intdiv($dayOfEra, 1460) + intdiv($dayOfEra, 36524) - intdiv($dayOfEra, 146096), 365);
        $dayOfYear = $dayOfEra - (365 * $yearOfEra + intdiv($yearOfEra, 4) - intdiv($yearOfEra, 100));
        $monthPrime = intdiv(5 * $dayOfYear + 2, 153);
        $day = $dayOfYear - intdiv(153 * $monthPrime + 2, 5) + 1;
        $month = $monthPrime < 10 ? $monthPrime + 3 : $monthPrime - 9;
        $year = $yearOfEra + $era * 400 + ($month <= 2 ? 1 : 0);

        return new self($year, $month, $day);
    }

    /**
     * Days since 1970-01-01 (negative before it); Hinnant's days_from_civil.
     */
    public function toDayNumber(): int
    {
        $year = $this->month <= 2 ? $this->year - 1 : $this->year;
        $era = intdiv($year >= 0 ? $year : $year - 399, 400);
        $yearOfEra = $year - $era * 400;
        $dayOfYear = intdiv(153 * ($this->month > 2 ? $this->month - 3 : $this->month + 9) + 2, 5) + $this->day - 1;
        $dayOfEra = $yearOfEra * 365 + intdiv($yearOfEra, 4) - intdiv($yearOfEra, 100) + $dayOfYear;

        return $era * 146097 + $dayOfEra - 719468;
    }

    public function addDays(int $days): self
    {
        return $days === 0 ? $this : self::fromDayNumber($this->toDayNumber() + $days);
    }

    public function weekday(): Weekday
    {
        // 1970-01-01 was a Thursday (ISO 4).
        return Weekday::from((($this->toDayNumber() % 7) + 7 + 3) % 7 + 1);
    }

    public function compare(self $other): int
    {
        return $this->toDayNumber() <=> $other->toDayNumber();
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function isBefore(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function daysUntil(self $other): int
    {
        return $other->toDayNumber() - $this->toDayNumber();
    }

    public static function daysInMonth(int $year, int $month): int
    {
        return match ($month) {
            2 => self::isLeapYear($year) ? 29 : 28,
            4, 6, 9, 11 => 30,
            default => 31,
        };
    }

    public static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    /**
     * For example `Friday 3 July 2026`.
     */
    public function describe(): string
    {
        return sprintf('%s %d %s %d', $this->weekday()->name, $this->day, self::MONTHS[$this->month], $this->year);
    }

    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
