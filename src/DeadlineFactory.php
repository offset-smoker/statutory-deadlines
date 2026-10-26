<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

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
