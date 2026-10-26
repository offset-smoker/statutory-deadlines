<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Holidays;

use OffsetSmoker\PaBusinessDays\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\PaBusinessDays\LocalDate;

/**
 * A single day an office is closed that no yearly rule describes: a
 * governor's proclamation, an emergency closure, an extra day off the
 * agency granted.
 */
final readonly class Closure implements HolidayRule
{
    public function __construct(
        private LocalDate $date,
        private string $reason,
        private string $citation = 'Closure added to this calendar',
    ) {
        if (trim($reason) === '') {
            throw new InvalidDeadlineArgument('A closure needs a reason, so the explanation can say why the day was skipped.');
        }
    }

    public function name(): string
    {
        return $this->reason;
    }

    public function citation(): string
    {
        return $this->citation;
    }

    public function occurrencesIn(int $year): array
    {
        if ($year !== $this->date->year) {
            return [];
        }

        return [new Observance($this->reason, $this->date, $this->date, $this->citation)];
    }
}
