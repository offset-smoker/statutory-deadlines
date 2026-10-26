<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Explain;

use OffsetSmoker\PaBusinessDays\Holidays\Observance;
use OffsetSmoker\PaBusinessDays\LocalDate;

final readonly class Step
{
    /**
     * @param  int|null  $day  For a counted day, its number within the period (1, 2, ...).
     * @param  list<Observance>  $holidays  The holidays or closures behind a skipped day.
     */
    public function __construct(
        public StepKind $kind,
        public LocalDate $date,
        public string $description,
        public ?int $day = null,
        public array $holidays = [],
    ) {}

    /**
     * @return array{kind: string, date: string, description: string, day: int|null, holidays: list<array{name: string, date: string, observed: string, citation: string, observed_citation: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'date' => $this->date->toString(),
            'description' => $this->description,
            'day' => $this->day,
            'holidays' => array_map(static fn(Observance $holiday): array => $holiday->toArray(), $this->holidays),
        ];
    }
}
