<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines;

/**
 * The built-in calendars by a stable string name, for configuration files.
 */
enum BuiltInCalendar: string
{
    case Pennsylvania = 'pennsylvania';
    case Federal = 'federal';
    case PennsylvaniaAndFederal = 'pennsylvania+federal';

    public function calendar(): Calendar
    {
        return match ($this) {
            self::Pennsylvania => Calendar::pennsylvania(),
            self::Federal => Calendar::federal(),
            self::PennsylvaniaAndFederal => Calendar::pennsylvaniaAndFederal(),
        };
    }
}
