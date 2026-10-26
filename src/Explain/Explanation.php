<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Explain;

use OffsetSmoker\PaBusinessDays\LocalDate;
use Stringable;

/**
 * How a due date was reached, day by day: when the request was received,
 * whether that day counted, each day counted, each day skipped and why.
 */
final readonly class Explanation implements Stringable
{
    /**
     * @param  list<Step>  $steps
     */
    public function __construct(
        public LocalDate $due,
        public string $calendar,
        public string $timezone,
        public array $steps,
    ) {}

    /**
     * The days passed over, each with the weekend day, holiday or closure
     * that caused it.
     *
     * @return list<Step>
     */
    public function skipped(): array
    {
        return $this->ofKind(StepKind::Skipped);
    }

    /**
     * @return list<Step>
     */
    public function ofKind(StepKind $kind): array
    {
        return array_values(array_filter($this->steps, static fn(Step $step): bool => $step->kind === $kind));
    }

    /**
     * @return array{due: string, calendar: string, timezone: string, steps: list<array{kind: string, date: string, description: string, day: int|null, holidays: list<array{name: string, date: string, observed: string, citation: string, observed_citation: string|null}>}>}
     */
    public function toArray(): array
    {
        return [
            'due' => $this->due->toString(),
            'calendar' => $this->calendar,
            'timezone' => $this->timezone,
            'steps' => array_map(static fn(Step $step): array => $step->toArray(), $this->steps),
        ];
    }

    public function toString(): string
    {
        $lines = [sprintf('Calendar: %s. Timezone: %s.', $this->calendar, $this->timezone)];

        foreach ($this->steps as $step) {
            $lines[] = sprintf('%s  %-14s %s', $step->date->toString(), $step->kind->value, $step->description);
        }

        return implode("\n", $lines);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
