<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Rules;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Cutoff;
use OffsetSmoker\StatutoryDeadlines\Deadline;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\LocalDate;
use OffsetSmoker\StatutoryDeadlines\Period;

/**
 * A statute's clock as a value: its name, its citation, the period it
 * grants, any extensions, and how it treats the day and time of receipt.
 *
 *     $rule = new DeadlineRule(
 *         name: 'Example Records Act, response',
 *         citation: 'Example Code § 1-101',
 *         period: new Period(10, Unit::BusinessDays),
 *     );
 *     $rule->apply($received, Calendar::federal(), 'America/Chicago')->due();
 */
final readonly class DeadlineRule
{
    /** @var list<Period> */
    public array $extensions;

    /**
     * @param  list<Period>  $extensions  Added in order after $period, each counted from the day after the previous due date.
     * @param  bool  $countReceiptDay  Whether the day of receipt is day one (most statutes exclude it).
     */
    public function __construct(
        public string $name,
        public string $citation,
        public Period $period,
        array $extensions = [],
        public bool $countReceiptDay = false,
        public ?Cutoff $cutoff = null,
    ) {
        if (trim($name) === '' || trim($citation) === '') {
            throw new InvalidDeadlineArgument('A rule needs a name and a citation.');
        }

        Period::ensureWithinTotal([$period, ...$extensions]);

        $this->extensions = $extensions;
    }

    /**
     * @template TFrom of DateTimeInterface
     *
     * @param  TFrom  $received
     * @return (TFrom is DateTimeImmutable ? Deadline<TFrom> : Deadline<DateTimeImmutable>)
     */
    public function apply(DateTimeInterface $received, Calendar $calendar, DateTimeZone|string|null $timezone = null): Deadline
    {
        return $this->configure(Deadline::from($received, $calendar, $timezone));
    }

    /**
     * Applies the rule from a calendar date (see Deadline::fromDate()).
     *
     * @return Deadline<DateTimeImmutable>
     */
    public function applyOnDate(LocalDate|string $date, Calendar $calendar, DateTimeZone|string $timezone): Deadline
    {
        return $this->configure(Deadline::fromDate($date, $calendar, $timezone));
    }

    /**
     * @template TDate of DateTimeImmutable
     *
     * @param  Deadline<TDate>  $deadline
     * @return Deadline<TDate>
     */
    private function configure(Deadline $deadline): Deadline
    {
        $deadline = $deadline
            ->governedBy($this->name, $this->citation)
            ->countReceiptDay($this->countReceiptDay)
            ->counting($this->period);

        if ($this->cutoff !== null) {
            $deadline = $deadline->withCutoff($this->cutoff);
        }

        foreach ($this->extensions as $extension) {
            $deadline = $deadline->extendBy($extension->count, $extension->unit, $extension->rollForward, $extension->citation);
        }

        return $deadline;
    }
}
