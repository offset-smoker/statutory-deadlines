<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays;

enum Unit: string
{
    /** Days that are neither a weekend day nor a holiday or closure in the calendar. */
    case BusinessDays = 'business_days';

    /** Every day on the calendar, weekends and holidays included. */
    case CalendarDays = 'calendar_days';

    public function label(int $count): string
    {
        $noun = match ($this) {
            self::BusinessDays => 'business day',
            self::CalendarDays => 'calendar day',
        };

        return $count === 1 ? "1 {$noun}" : "{$count} {$noun}s";
    }
}
