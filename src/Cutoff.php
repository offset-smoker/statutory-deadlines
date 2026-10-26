<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines;

use DateTimeInterface;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use Stringable;

/**
 * The time of day after which a receipt counts as received on the next
 * business day, in the deadline's timezone. A receipt at exactly the cutoff
 * is after hours: with a 16:30 cutoff, 16:29:59 is the last on-time second.
 */
final readonly class Cutoff implements Stringable
{
    public function __construct(
        public int $hour,
        public int $minute = 0,
    ) {
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new InvalidDeadlineArgument(sprintf('%d:%d is not a time of day.', $hour, $minute));
        }
    }

    /**
     * Parses a 24-hour `HH:MM`, such as `16:30`.
     */
    public static function parse(string $time): self
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)\z/', $time, $parts) !== 1) {
            throw new InvalidDeadlineArgument(sprintf('"%s" is not a 24-hour time in HH:MM form.', $time));
        }

        return new self((int) $parts[1], (int) $parts[2]);
    }

    /**
     * Whether the wall-clock time of $moment, in its own timezone, is at or
     * after the cutoff.
     */
    public function isReachedBy(DateTimeInterface $moment): bool
    {
        $seconds = (int) $moment->format('G') * 3600 + (int) $moment->format('i') * 60 + (int) $moment->format('s');

        return $seconds >= $this->hour * 3600 + $this->minute * 60;
    }

    public function toString(): string
    {
        return sprintf('%02d:%02d', $this->hour, $this->minute);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
