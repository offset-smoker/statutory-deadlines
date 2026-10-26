<?php

declare(strict_types=1);

/*
 * Encoding another statute's clock and another jurisdiction's holidays
 * without forking the library. "Example State" and its code are made up.
 *
 *     php examples/custom-rule.php
 */

use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Holidays\FixedDateHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\NthWeekdayHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\WeekendShift;
use OffsetSmoker\StatutoryDeadlines\Period;
use OffsetSmoker\StatutoryDeadlines\Rules\DeadlineRule;
use OffsetSmoker\StatutoryDeadlines\Unit;
use OffsetSmoker\StatutoryDeadlines\Weekday;

require __DIR__ . '/../vendor/autoload.php';

$exampleState = Calendar::federal()->merge(new Calendar('Example State', [
    new FixedDateHoliday(
        name: 'Statehood Day',
        month: 3,
        day: 15,
        citation: 'Example Code § 1-1',
        shift: WeekendShift::SundayToMonday,
        shiftCitation: 'Example Code § 1-2',
    ),
    new NthWeekdayHoliday('Day after Thanksgiving', 11, Weekday::Thursday, 4, 'Example Code § 1-1', offsetDays: 1),
]), 'Example State and United States federal');

$rule = new DeadlineRule(
    name: 'Example Public Records Act, response',
    citation: 'Example Code § 5-101',
    period: new Period(10, Unit::BusinessDays),
    extensions: [new Period(10, Unit::BusinessDays, citation: 'Example Code § 5-102')],
);

$deadline = $rule->apply(
    new DateTimeImmutable('2026-11-16 09:30', new DateTimeZone('America/Chicago')),
    $exampleState,
);

echo $deadline->dueDate()->describe(), PHP_EOL, PHP_EOL;
echo $deadline->explain(), PHP_EOL;
