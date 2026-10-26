<?php

declare(strict_types=1);

use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\Holidays\Closure;
use OffsetSmoker\StatutoryDeadlines\Holidays\EasterHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\FixedDateHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\NthWeekdayHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\Observance;
use OffsetSmoker\StatutoryDeadlines\Holidays\WeekendShift;
use OffsetSmoker\StatutoryDeadlines\LocalDate;
use OffsetSmoker\StatutoryDeadlines\Weekday;

it('finds the nth weekday of a month', function (int $year, int $month, Weekday $weekday, int $nth, string $expected): void {
    $rule = new NthWeekdayHoliday('Test', $month, $weekday, $nth, 'test');

    expect($rule->occurrencesIn($year)[0]->date->toString())->toBe($expected);
})->with([
    'month starts on the weekday' => [2026, 6, Weekday::Monday, 1, '2026-06-01'],
    'month starts the day after' => [2026, 9, Weekday::Monday, 1, '2026-09-07'],
    'fourth Thursday, earliest possible' => [2029, 11, Weekday::Thursday, 4, '2029-11-22'],
    'fourth Thursday, latest possible' => [2027, 11, Weekday::Thursday, 4, '2027-11-25'],
    'last Monday when May has five' => [2026, 5, Weekday::Monday, NthWeekdayHoliday::LAST, '2026-05-25'],
    'last Monday on the 31st' => [2027, 5, Weekday::Monday, NthWeekdayHoliday::LAST, '2027-05-31'],
    'last day of a leap February' => [2028, 2, Weekday::Tuesday, NthWeekdayHoliday::LAST, '2028-02-29'],
]);

it('applies a day offset, as for the Tuesday after the first Monday', function (int $year, string $expected): void {
    $rule = new NthWeekdayHoliday('Election Day', 11, Weekday::Monday, 1, 'test', offsetDays: 1);

    expect($rule->occurrencesIn($year)[0]->date->toString())->toBe($expected);
})->with([
    // November 1 is a Tuesday: the first Monday is the 7th, so Election Day is the 8th, not the 1st.
    'Nov 1 is a Tuesday' => [2022, '2022-11-08'],
    'Nov 1 is a Monday' => [2027, '2027-11-02'],
    'Nov 1 is a Sunday' => [2026, '2026-11-03'],
]);

it('computes Gregorian Easter', function (int $year, string $expected): void {
    expect(EasterHoliday::easterSunday($year)->toString())->toBe($expected);
})->with([
    [2000, '2000-04-23'],
    [2008, '2008-03-23'],
    [2011, '2011-04-24'],
    [2019, '2019-04-21'],
    [2024, '2024-03-31'],
    [2025, '2025-04-20'],
    [2026, '2026-04-05'],
    [2027, '2027-03-28'],
    [2038, '2038-04-25'],
]);

it('matches the calendar extension\'s Easter for every year from 1986 to 2100', function (): void {
    $mismatches = [];

    for ($year = 1986; $year <= 2100; $year++) {
        $expected = LocalDate::parse("{$year}-03-21")->addDays(easter_days($year, CAL_EASTER_ALWAYS_GREGORIAN));

        if (! EasterHoliday::easterSunday($year)->equals($expected)) {
            $mismatches[] = $year;
        }
    }

    expect($mismatches)->toBe([]);
})->skip(! extension_loaded('calendar'), 'Needs ext-calendar as an independent check.');

it('puts Good Friday two days before Easter', function (): void {
    $goodFriday = new EasterHoliday('Good Friday', -2, 'test');

    expect($goodFriday->occurrencesIn(2026)[0]->date->toString())->toBe('2026-04-03')
        ->and($goodFriday->occurrencesIn(2026)[0]->date->weekday())->toBe(Weekday::Friday);
});

it('shifts fixed-date holidays off weekends by the chosen rule', function (WeekendShift $shift, string $date, string $observed): void {
    $parsed = LocalDate::parse($date);
    $rule = new FixedDateHoliday('Test', $parsed->month, $parsed->day, 'test', $shift);

    expect($rule->occurrencesIn($parsed->year)[0]->observed->toString())->toBe($observed);
})->with([
    'federal, Saturday to Friday' => [WeekendShift::SaturdayToFridaySundayToMonday, '2026-07-04', '2026-07-03'],
    'federal, Sunday to Monday' => [WeekendShift::SaturdayToFridaySundayToMonday, '2027-07-04', '2027-07-05'],
    'federal, weekday stays' => [WeekendShift::SaturdayToFridaySundayToMonday, '2028-07-04', '2028-07-04'],
    'federal, Saturday Jan 1 goes to the previous year' => [WeekendShift::SaturdayToFridaySundayToMonday, '2022-01-01', '2021-12-31'],
    'Pennsylvania, Saturday stays' => [WeekendShift::SundayToMonday, '2026-07-04', '2026-07-04'],
    'Pennsylvania, Sunday to Monday' => [WeekendShift::SundayToMonday, '2027-07-04', '2027-07-05'],
    'no shift, Sunday stays' => [WeekendShift::None, '2027-07-04', '2027-07-04'],
]);

it('reports whether an occurrence was shifted and why', function (): void {
    $rule = new FixedDateHoliday('Independence Day', 7, 4, 'list', WeekendShift::SaturdayToFridaySundayToMonday, 'shift rule');
    $saturday = $rule->occurrencesIn(2026)[0];
    $weekday = $rule->occurrencesIn(2028)[0];

    expect($saturday->isShifted())->toBeTrue()
        ->and($saturday->describe())->toBe('Independence Day (Saturday 4 July 2026, observed Friday 3 July 2026)')
        ->and($saturday->toArray()['observed_citation'])->toBe('shift rule')
        ->and($weekday->isShifted())->toBeFalse()
        ->and($weekday->describe())->toBe('Independence Day')
        ->and($weekday->toArray()['observed_citation'])->toBeNull();
});

it('lists an offset that crosses New Year under the year the date falls in', function (): void {
    // The first Monday of January 2024 is 1 January, so six days before it is
    // 26 December 2023; the first Monday of 2025 is 6 January, giving 31 December 2024.
    $before = new NthWeekdayHoliday('Test', 1, Weekday::Monday, 1, 'test', offsetDays: -6);
    // The last Thursday of December 2026 is the 31st, so six days after it is 6 January 2027.
    $after = new NthWeekdayHoliday('Test', 12, Weekday::Thursday, NthWeekdayHoliday::LAST, 'test', offsetDays: 6);

    $dates = static fn(NthWeekdayHoliday $rule, int $year): array => array_map(
        static fn(Observance $observance): string => $observance->date->toString(),
        $rule->occurrencesIn($year),
    );

    expect($dates($before, 2024))->toBe(['2024-12-31'])
        ->and($dates($before, 2023))->toBe(['2023-12-26'])
        ->and($dates($after, 2026))->toBe([])
        ->and($dates($after, 2027))->toBe(['2027-01-06'])
        ->and((new Calendar('Test', [$before]))->holidaysFallingOn(LocalDate::parse('2023-12-26')))->toHaveCount(1);
});

it('produces nothing before a rule\'s first year', function (): void {
    $fixed = new FixedDateHoliday('Juneteenth', 6, 19, 'test', firstYear: 2021);
    $floating = new NthWeekdayHoliday('Test', 1, Weekday::Monday, 3, 'test', firstYear: 1986);
    $easter = new EasterHoliday('Test', 0, 'test', firstYear: 2000);

    expect($fixed->occurrencesIn(2020))->toBe([])
        ->and($fixed->occurrencesIn(2021))->toHaveCount(1)
        ->and($floating->occurrencesIn(1985))->toBe([])
        ->and($easter->occurrencesIn(1999))->toBe([]);
});

it('limits a closure to its own date', function (): void {
    $closure = new Closure(LocalDate::parse('2026-12-24'), 'Closed by proclamation');

    expect($closure->occurrencesIn(2025))->toBe([])
        ->and($closure->occurrencesIn(2026)[0]->observed->toString())->toBe('2026-12-24')
        ->and($closure->name())->toBe('Closed by proclamation');
});

it('refuses rules that cannot describe a yearly holiday', function (): void {
    $invalid = [
        'February 29' => fn(): FixedDateHoliday => new FixedDateHoliday('Leap', 2, 29, 'test'),
        'April 31' => fn(): FixedDateHoliday => new FixedDateHoliday('Bad', 4, 31, 'test'),
        'fifth Monday' => fn(): NthWeekdayHoliday => new NthWeekdayHoliday('Bad', 1, Weekday::Monday, 5, 'test'),
        'zeroth Monday' => fn(): NthWeekdayHoliday => new NthWeekdayHoliday('Bad', 1, Weekday::Monday, 0, 'test'),
        'month 13' => fn(): NthWeekdayHoliday => new NthWeekdayHoliday('Bad', 13, Weekday::Monday, 1, 'test'),
        'week-long offset' => fn(): NthWeekdayHoliday => new NthWeekdayHoliday('Bad', 1, Weekday::Monday, 1, 'test', offsetDays: 7),
        'Easter offset too large' => fn(): EasterHoliday => new EasterHoliday('Bad', 61, 'test'),
        'unnamed holiday' => fn(): FixedDateHoliday => new FixedDateHoliday('', 1, 1, 'test'),
        'closure without a reason' => fn(): Closure => new Closure(LocalDate::parse('2026-01-02'), '  '),
    ];

    foreach ($invalid as $case => $make) {
        expect($make)->toThrow(InvalidDeadlineArgument::class, null, $case);
    }
});
