<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\Explain\Explanation;
use OffsetSmoker\StatutoryDeadlines\Explain\Step;
use OffsetSmoker\StatutoryDeadlines\Explain\StepKind;
use OffsetSmoker\StatutoryDeadlines\Support\Memo;
use OffsetSmoker\StatutoryDeadlines\Support\Timezone;

/**
 * A due date counted from the moment something was received.
 *
 *     Deadline::from($received, Calendar::pennsylvania())
 *         ->businessDays(5)
 *         ->extendBy(30, Unit::CalendarDays)
 *         ->due();
 *
 * Immutable: every method returns a new Deadline, and the date passed in is
 * never modified. Counting happens on calendar dates in the deadline's
 * timezone, so daylight-saving changes cannot shift the result. `due()`
 * returns the same class you passed in (a CarbonImmutable stays a
 * CarbonImmutable); a mutable DateTime comes back as a DateTimeImmutable.
 * The period and its extensions may count at most Period::MAX_DAYS days in
 * all, and the result is worked out once per Deadline however often it is
 * read.
 *
 * @template-covariant TDate of DateTimeImmutable
 */
final readonly class Deadline
{
    /** @var Memo<int, Explanation> */
    private Memo $explanation;

    /**
     * @param  TDate  $received  Already in the deadline's timezone.
     * @param  list<Period>  $extensions
     */
    private function __construct(
        private DateTimeImmutable $received,
        private Calendar $calendar,
        private ?Cutoff $cutoff,
        private bool $countReceiptDay,
        private ?Period $period,
        private array $extensions,
        private ?string $rule = null,
        private ?string $ruleCitation = null,
    ) {
        Period::ensureWithinTotal($period === null ? $extensions : [$period, ...$extensions]);

        $this->explanation = new Memo();
    }

    /**
     * @template TFrom of DateTimeInterface
     *
     * @param  TFrom  $received  When the request arrived.
     * @param  DateTimeZone|string|null  $timezone  Where the office is; defaults to $received's own timezone.
     * @return (TFrom is DateTimeImmutable ? self<TFrom> : self<DateTimeImmutable>)
     */
    public static function from(DateTimeInterface $received, Calendar $calendar, DateTimeZone|string|null $timezone = null): self
    {
        $zone = $timezone === null ? null : Timezone::resolve($timezone);

        if ($received instanceof DateTimeImmutable) {
            return new self($zone === null ? $received : $received->setTimezone($zone), $calendar, null, false, null, [], null, null);
        }

        $immutable = DateTimeImmutable::createFromInterface($received);

        return new self($zone === null ? $immutable : $immutable->setTimezone($zone), $calendar, null, false, null, [], null, null);
    }

    /**
     * Starts from a calendar date rather than a moment, for clocks that run
     * from a date (a mailing date, a decision date). The start is midnight of
     * that date in $timezone, so a date never slides to the day before when
     * the timezone is west of UTC.
     *
     * @return self<DateTimeImmutable>
     */
    public static function fromDate(LocalDate|string $date, Calendar $calendar, DateTimeZone|string $timezone): self
    {
        $day = $date instanceof LocalDate ? $date : LocalDate::parse($date);
        $start = (new DateTimeImmutable('now', Timezone::resolve($timezone)))
            ->setDate($day->year, $day->month, $day->day)
            ->setTime(0, 0);

        return new self($start, $calendar, null, false, null, [], null, null);
    }

    /**
     * Counts $count business days. The day of receipt is not counted unless
     * countReceiptDay() says so.
     *
     * @return self<TDate>
     */
    public function businessDays(int $count): self
    {
        return $this->withPeriod(new Period($count, Unit::BusinessDays));
    }

    /**
     * Counts $count calendar days. If the last one is not a business day, the
     * period ends on the next business day unless $rollForward is false.
     *
     * @return self<TDate>
     */
    public function calendarDays(int $count, bool $rollForward = true): self
    {
        return $this->withPeriod(new Period($count, Unit::CalendarDays, $rollForward));
    }

    /**
     * Sets the first period from a Period value (what DeadlineRule uses).
     *
     * @return self<TDate>
     */
    public function counting(Period $period): self
    {
        return $this->withPeriod($period);
    }

    /**
     * Names the statute or rule this deadline implements, for the explanation.
     *
     * @return self<TDate>
     */
    public function governedBy(string $rule, string $citation): self
    {
        if (trim($rule) === '' || trim($citation) === '') {
            throw new InvalidDeadlineArgument('A rule needs a name and a citation.');
        }

        return new self($this->received, $this->calendar, $this->cutoff, $this->countReceiptDay, $this->period, $this->extensions, $rule, $citation);
    }

    /**
     * Adds a period counted from the day after the current due date.
     * Extensions stack in the order they are added. $citation, if given, is
     * shown in the explanation.
     *
     * @return self<TDate>
     */
    public function extendBy(int $count, Unit $unit, bool $rollForward = true, ?string $citation = null): self
    {
        return new self(
            $this->received,
            $this->calendar,
            $this->cutoff,
            $this->countReceiptDay,
            $this->period,
            [...$this->extensions, new Period($count, $unit, $rollForward, $citation)],
            $this->rule,
            $this->ruleCitation,
        );
    }

    /**
     * Receipt at or after this time of day, or on a day that is not a
     * business day, is deemed to be receipt on the next business day.
     *
     * @return self<TDate>
     */
    public function withCutoff(Cutoff|string $cutoff): self
    {
        $parsed = $cutoff instanceof Cutoff ? $cutoff : Cutoff::parse($cutoff);

        return new self($this->received, $this->calendar, $parsed, $this->countReceiptDay, $this->period, $this->extensions, $this->rule, $this->ruleCitation);
    }

    /**
     * @return self<TDate>
     */
    public function withoutCutoff(): self
    {
        return new self($this->received, $this->calendar, null, $this->countReceiptDay, $this->period, $this->extensions, $this->rule, $this->ruleCitation);
    }

    /**
     * Whether the (deemed) day of receipt is day one of the first period,
     * when it is a business day. The default is false: the first day is
     * excluded, as 1 Pa.C.S. § 1908 computes statutory periods.
     *
     * @return self<TDate>
     */
    public function countReceiptDay(bool $counts = true): self
    {
        return new self($this->received, $this->calendar, $this->cutoff, $counts, $this->period, $this->extensions, $this->rule, $this->ruleCitation);
    }

    /**
     * The moment of receipt, in the deadline's timezone.
     *
     * @return TDate
     */
    public function received(): DateTimeImmutable
    {
        return $this->received;
    }

    public function calendar(): Calendar
    {
        return $this->calendar;
    }

    public function timezone(): DateTimeZone
    {
        return $this->received->getTimezone();
    }

    public function rule(): ?string
    {
        return $this->rule;
    }

    /**
     * The date receipt counts from, after the cutoff and closed-day rules.
     */
    public function deemedReceived(): LocalDate
    {
        $steps = [];

        return $this->deem($steps);
    }

    public function dueDate(): LocalDate
    {
        return $this->explain()->due;
    }

    /**
     * The start of the due date in the deadline's timezone. The deadline is
     * met by anything done at any time on that day.
     *
     * @return TDate
     */
    public function due(): DateTimeImmutable
    {
        $due = $this->dueDate();

        return $this->received->setDate($due->year, $due->month, $due->day)->setTime(0, 0);
    }

    /**
     * Whether the deadline has passed at $moment: true from the start of the
     * day after the due date, in the deadline's timezone.
     */
    public function isMissedAt(DateTimeInterface $moment): bool
    {
        $local = DateTimeImmutable::createFromInterface($moment)->setTimezone($this->timezone());

        return LocalDate::of($local)->isAfter($this->dueDate());
    }

    public function explain(): Explanation
    {
        return $this->explanation->remember(0, $this->work(...));
    }

    private function work(): Explanation
    {
        $steps = [new Step(StepKind::Received, LocalDate::of($this->received), sprintf(
            'Received %s at %s (%s).',
            LocalDate::of($this->received)->describe(),
            $this->received->format('H:i:s'),
            $this->timezone()->getName(),
        ))];

        $deemed = $this->deem($steps);
        $due = $deemed;

        if ($this->period !== null) {
            $due = $this->countFirstPeriod($this->period, $deemed, $steps);
        }

        foreach ($this->extensions as $extension) {
            $steps[] = new Step(StepKind::Extended, $due, sprintf(
                'Extended by %s%s, counted from the day after %s.',
                $extension->describe(),
                $extension->citation === null ? '' : " ({$extension->citation})",
                $due->describe(),
            ));
            $due = $this->count($extension, $due->addDays(1), $steps);
        }

        $steps[] = new Step(StepKind::Due, $due, sprintf('Due %s.', $due->describe()));

        return new Explanation($due, $this->calendar->name, $this->timezone()->getName(), $steps, $this->rule, $this->ruleCitation);
    }

    /**
     * @param  list<Step>  $steps
     */
    private function deem(array &$steps): LocalDate
    {
        $receipt = LocalDate::of($this->received);

        if ($this->cutoff === null) {
            return $receipt;
        }

        if (! $this->calendar->isBusinessDay($receipt)) {
            $reason = 'Received on a day that is not a business day';
        } elseif ($this->cutoff->isReachedBy($this->received)) {
            $reason = sprintf('Received at or after the %s cutoff', $this->cutoff->toString());
        } else {
            $steps[] = new Step(StepKind::CutoffMet, $receipt, sprintf(
                'Received before the %s cutoff on a business day, so it counts as received that day.',
                $this->cutoff->toString(),
            ));

            return $receipt;
        }

        $deemed = $this->skipToBusinessDay($receipt->addDays(1), $steps);
        $steps[] = new Step(StepKind::DeemedReceived, $deemed, sprintf(
            '%s, so it is deemed received on the next business day, %s.',
            $reason,
            $deemed->describe(),
        ));

        return $deemed;
    }

    /**
     * @param  list<Step>  $steps
     */
    private function countFirstPeriod(Period $period, LocalDate $deemed, array &$steps): LocalDate
    {
        if (! $this->countReceiptDay) {
            $steps[] = new Step(StepKind::ReceiptDay, $deemed, 'The day of receipt is not counted; counting starts the next day.');

            return $this->count($period, $deemed->addDays(1), $steps);
        }

        if ($period->unit === Unit::BusinessDays && ! $this->calendar->isBusinessDay($deemed)) {
            $steps[] = new Step(StepKind::ReceiptDay, $deemed, 'The day of receipt is not a business day, so it cannot count as day 1.');

            return $this->count($period, $deemed->addDays(1), $steps);
        }

        $steps[] = new Step(StepKind::ReceiptDay, $deemed, 'The day of receipt counts as day 1.');

        return $this->count($period, $deemed, $steps);
    }

    /**
     * Counts $period with $start as its first day.
     *
     * @param  list<Step>  $steps
     */
    private function count(Period $period, LocalDate $start, array &$steps): LocalDate
    {
        if ($period->unit === Unit::CalendarDays) {
            $end = $start->addDays($period->count - 1);
            $steps[] = new Step(StepKind::Counted, $end, sprintf(
                '%s counted: %s through %s.',
                ucfirst($period->describe()),
                $start->describe(),
                $end->describe(),
            ), $period->count);

            if (! $period->rollForward || $this->calendar->isBusinessDay($end)) {
                return $end;
            }

            $rolled = $this->skipToBusinessDay($end, $steps);
            $steps[] = new Step(StepKind::RolledForward, $rolled, sprintf(
                'Day %d is not a business day, so the period ends on the next business day, %s.',
                $period->count,
                $rolled->describe(),
            ));

            return $rolled;
        }

        $day = $start;

        for ($number = 1; ; $number++) {
            $day = $this->skipToBusinessDay($day, $steps);
            $steps[] = new Step(StepKind::Counted, $day, sprintf('Business day %d of %d.', $number, $period->count), $number);

            if ($number === $period->count) {
                return $day;
            }

            $day = $day->addDays(1);
        }
    }

    /**
     * Returns $day if it is a business day, otherwise the next one, recording
     * a Skipped step for every day passed over.
     *
     * @param  list<Step>  $steps
     */
    private function skipToBusinessDay(LocalDate $day, array &$steps): LocalDate
    {
        while (! $this->calendar->isBusinessDay($day)) {
            $steps[] = $this->skipped($day);
            $day = $day->addDays(1);
        }

        return $day;
    }

    private function skipped(LocalDate $day): Step
    {
        $observed = $this->calendar->observedOn($day);
        $reasons = [];
        $holidays = $observed;

        if ($this->calendar->isWeekend($day)) {
            $reasons[] = "{$day->weekday()->name} (weekend)";
        }

        foreach ($observed as $holiday) {
            $reasons[] = $holiday->isShifted() && $holiday->observedCitation !== null
                ? "{$holiday->describe()} under {$holiday->observedCitation}"
                : $holiday->describe();
        }

        foreach ($this->calendar->holidaysFallingOn($day) as $holiday) {
            if ($holiday->isShifted()) {
                $reasons[] = sprintf('%s falls on this day and is observed %s', $holiday->name, $holiday->observed->describe());
                $holidays[] = $holiday;
            }
        }

        return new Step(
            StepKind::Skipped,
            $day,
            sprintf('Skipped %s: %s.', $day->describe(), implode('; ', $reasons)),
            holidays: $holidays,
        );
    }

    /**
     * @return self<TDate>
     */
    private function withPeriod(Period $period): self
    {
        return new self($this->received, $this->calendar, $this->cutoff, $this->countReceiptDay, $period, $this->extensions, $this->rule, $this->ruleCitation);
    }
}
