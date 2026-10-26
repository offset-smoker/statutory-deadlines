<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Holidays;

use OffsetSmoker\PaBusinessDays\LocalDate;
use OffsetSmoker\PaBusinessDays\Weekday;

/**
 * What happens to a fixed-date holiday that falls on a weekend.
 */
enum WeekendShift
{
    /** The holiday stays on its date, weekend or not. */
    case None;

    /**
     * A Sunday holiday is observed the following Monday; a Saturday holiday
     * is not moved. This is Pennsylvania's rule (Act of May 31, 1893,
     * P.L. 188, No. 138, § 2(a)).
     */
    case SundayToMonday;

    /**
     * A Saturday holiday is observed the Friday before (5 U.S.C. § 6103(b)(1))
     * and a Sunday holiday the Monday after (Executive Order 11582, § 3(a)).
     * This is the federal rule for employees whose basic workweek is Monday
     * through Friday.
     */
    case SaturdayToFridaySundayToMonday;

    public function observe(LocalDate $date): LocalDate
    {
        $weekday = $date->weekday();

        return match (true) {
            $this === self::None => $date,
            $weekday === Weekday::Sunday => $date->addDays(1),
            $weekday === Weekday::Saturday && $this === self::SaturdayToFridaySundayToMonday => $date->addDays(-1),
            default => $date,
        };
    }
}
