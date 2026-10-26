<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays;

/**
 * ISO-8601 day of the week: Monday is 1, Sunday is 7, matching PHP's
 * `format('N')`.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;
}
