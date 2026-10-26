<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Holidays;

use OffsetSmoker\StatutoryDeadlines\LocalDate;

/**
 * One occurrence of a holiday: the date the law names, and the date it is
 * observed (the same date unless a weekend rule moved it).
 */
final readonly class Observance
{
    public function __construct(
        public string $name,
        public LocalDate $date,
        public LocalDate $observed,
        public string $citation,
        public ?string $observedCitation = null,
    ) {}

    public function isShifted(): bool
    {
        return ! $this->date->equals($this->observed);
    }

    /**
     * For example "Independence Day (Saturday 4 July 2026, observed Friday 3 July 2026)".
     */
    public function describe(): string
    {
        if (! $this->isShifted()) {
            return $this->name;
        }

        return sprintf('%s (%s, observed %s)', $this->name, $this->date->describe(), $this->observed->describe());
    }

    /**
     * @return array{name: string, date: string, observed: string, citation: string, observed_citation: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'date' => $this->date->toString(),
            'observed' => $this->observed->toString(),
            'citation' => $this->citation,
            'observed_citation' => $this->isShifted() ? $this->observedCitation : null,
        ];
    }
}
