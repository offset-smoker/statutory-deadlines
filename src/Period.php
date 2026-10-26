<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines;

use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;

/**
 * A length of time to count: "5 business days", "30 calendar days".
 *
 * $rollForward applies to calendar days only: when the last day is not a
 * business day, the period ends on the next business day instead (the rule
 * of 1 Pa.C.S. § 1908). A business-day period always ends on a business day.
 * $citation names the provision the period comes from, for explanations.
 */
final readonly class Period
{
    /** Ten years of days; a bound on the work one deadline can ask for. */
    public const int MAX_DAYS = 3660;

    public function __construct(
        public int $count,
        public Unit $unit,
        public bool $rollForward = true,
        public ?string $citation = null,
    ) {
        if ($count < 1 || $count > self::MAX_DAYS) {
            throw new InvalidDeadlineArgument(sprintf('A period must be between 1 and %d days; got %d.', self::MAX_DAYS, $count));
        }
    }

    public function describe(): string
    {
        return $this->unit->label($this->count);
    }
}
