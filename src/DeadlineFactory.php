<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OffsetSmoker\StatutoryDeadlines\Rules\DeadlineRule;
use OffsetSmoker\StatutoryDeadlines\Rules\PennsylvaniaRightToKnow;

/**
 * Starts deadlines with an office's settings applied once: its calendar,
 * timezone, receipt cutoff and whether the day of receipt counts. The
 * Laravel service provider builds one of these from config.
 */
final readonly class DeadlineFactory
{
    public function __construct(
        private Calendar $calendar,
        private DateTimeZone $timezone,
        private ?Cutoff $cutoff = null,
        private bool $countReceiptDay = false,
    ) {}

    /**
     * @template TFrom of DateTimeInterface
     *
     * @param  TFrom  $received
     * @return (TFrom is DateTimeImmutable ? Deadline<TFrom> : Deadline<DateTimeImmutable>)
     */
    public function from(DateTimeInterface $received): Deadline
    {
        $deadline = Deadline::from($received, $this->calendar, $this->timezone)->countReceiptDay($this->countReceiptDay);

        return $this->cutoff === null ? $deadline : $deadline->withCutoff($this->cutoff);
    }

    /**
     * Applies a rule with this office's calendar and timezone. The rule's own
     * receipt settings (cutoff, receipt day) are used, not the factory's.
     *
     * @template TFrom of DateTimeInterface
     *
     * @param  TFrom  $received
     * @return (TFrom is DateTimeImmutable ? Deadline<TFrom> : Deadline<DateTimeImmutable>)
     */
    public function apply(DeadlineRule $rule, DateTimeInterface $received): Deadline
    {
        return $rule->apply($received, $this->calendar, $this->timezone);
    }

    /**
     * The Right-to-Know Law clocks with this office's calendar, timezone and cutoff.
     */
    public function rightToKnow(): PennsylvaniaRightToKnow
    {
        return new PennsylvaniaRightToKnow($this->calendar, $this->timezone, $this->cutoff);
    }

    public function calendar(): Calendar
    {
        return $this->calendar;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function cutoff(): ?Cutoff
    {
        return $this->cutoff;
    }

    public function countsReceiptDay(): bool
    {
        return $this->countReceiptDay;
    }
}
