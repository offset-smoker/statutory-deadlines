<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Exceptions;

use OutOfRangeException;

/**
 * The calendar was asked about a year its rules do not describe. The
 * built-in calendars model the holiday statutes as they stand from 1986
 * on; answering for an earlier year would silently apply today's list of
 * holidays to a year when the law was different.
 */
final class UnsupportedYear extends OutOfRangeException
{
    public static function before(int $year, int $earliestYear, string $calendar): self
    {
        return new self(sprintf(
            'The %s calendar describes %d and later; %d is not supported.',
            $calendar,
            $earliestYear,
            $year,
        ));
    }
}
