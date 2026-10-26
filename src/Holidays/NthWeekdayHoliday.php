<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Holidays;

use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\LocalDate;
use OffsetSmoker\StatutoryDeadlines\Weekday;

/**
 * A floating holiday: the nth (or last) given weekday of a month, plus an
 * optional offset in days.
 *
 * Thanksgiving is the fourth Thursday in November (n = 4); Memorial Day is
 * the last Monday in May (n = -1); Pennsylvania's Election Day, "the first
 * Tuesday after the first Monday of November", is the first Monday of
 * November plus one day.
 */
final readonly class NthWeekdayHoliday implements HolidayRule
{
    public const int LAST = -1;

    public function __construct(
        private string $name,
        private int $month,
        private Weekday $weekday,
        private int $nth,
        private string $citation,
        private int $offsetDays = 0,
        private ?int $firstYear = null,
    ) {
        if ($name === '') {
            throw new InvalidDeadlineArgument('A holiday needs a name.');
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidDeadlineArgument(sprintf('%d is not a month.', $month));
        }

        // A fifth weekday does not occur in every month, so it cannot define a yearly holiday.
        if ($nth !== self::LAST && ($nth < 1 || $nth > 4)) {
            throw new InvalidDeadlineArgument('nth must be 1 to 4, or NthWeekdayHoliday::LAST.');
        }

        if ($offsetDays < -6 || $offsetDays > 6) {
            throw new InvalidDeadlineArgument('offsetDays must be between -6 and 6.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function citation(): string
    {
        return $this->citation;
    }

    /**
     * An offset can carry the date across New Year (six days before the first
     * Monday of January can be in December), so the occurrences for $year are
     * generated from the neighbouring years too and kept when their own date
     * falls in $year. The first year applies to the year the rule is resolved in.
     */
    public function occurrencesIn(int $year): array
    {
        $firstDay = (new LocalDate($year, 1, 1))->toDayNumber();
        $lastDay = (new LocalDate($year, 12, 31))->toDayNumber();
        $occurrences = [];

        foreach ([$year - 1, $year, $year + 1] as $from) {
            if ($from < 1 || $from > 9999 || ($this->firstYear !== null && $from < $this->firstYear)) {
                continue;
            }

            $dayNumber = $this->resolve($from)->toDayNumber() + $this->offsetDays;

            if ($dayNumber < $firstDay || $dayNumber > $lastDay) {
                continue;
            }

            $date = LocalDate::fromDayNumber($dayNumber);
            $occurrences[] = new Observance($this->name, $date, $date, $this->citation);
        }

        return $occurrences;
    }

    private function resolve(int $year): LocalDate
    {
        if ($this->nth === self::LAST) {
            $last = new LocalDate($year, $this->month, LocalDate::daysInMonth($year, $this->month));
            $back = ($last->weekday()->value - $this->weekday->value + 7) % 7;

            return $last->addDays(-$back);
        }

        $first = new LocalDate($year, $this->month, 1);
        $forward = ($this->weekday->value - $first->weekday()->value + 7) % 7;

        return $first->addDays($forward + 7 * ($this->nth - 1));
    }
}
