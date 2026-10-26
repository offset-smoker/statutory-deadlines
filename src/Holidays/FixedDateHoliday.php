<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Holidays;

use OffsetSmoker\PaBusinessDays\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\PaBusinessDays\LocalDate;

/**
 * A holiday on the same month and day every year (July 4, December 25),
 * optionally moved off a weekend.
 */
final readonly class FixedDateHoliday implements HolidayRule
{
    public function __construct(
        private string $name,
        private int $month,
        private int $day,
        private string $citation,
        private WeekendShift $shift = WeekendShift::None,
        private ?string $shiftCitation = null,
        private ?int $firstYear = null,
    ) {
        if ($name === '') {
            throw new InvalidDeadlineArgument('A holiday needs a name.');
        }

        // February 29 is refused: a holiday must exist every year.
        if (! checkdate($month, $day, 2023)) {
            throw new InvalidDeadlineArgument(sprintf('%02d-%02d is not a month and day that occurs every year.', $month, $day));
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

    public function occurrencesIn(int $year): array
    {
        if ($this->firstYear !== null && $year < $this->firstYear) {
            return [];
        }

        $date = new LocalDate($year, $this->month, $this->day);

        return [new Observance($this->name, $date, $this->shift->observe($date), $this->citation, $this->shiftCitation)];
    }
}
