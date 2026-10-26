<?php

declare(strict_types=1);

use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\LocalDate;
use OffsetSmoker\StatutoryDeadlines\Weekday;

it('parses strict Y-m-d dates', function (): void {
    $date = LocalDate::parse('2026-07-03');

    expect($date->year)->toBe(2026)
        ->and($date->month)->toBe(7)
        ->and($date->day)->toBe(3)
        ->and((string) $date)->toBe('2026-07-03');
});

it('refuses malformed and impossible dates', function (string $input): void {
    LocalDate::parse($input);
})->throws(InvalidDeadlineArgument::class)->with([
    'no padding' => '2026-7-3',
    'time attached' => '2026-07-03 10:00',
    'trailing newline' => "2026-07-03\n",
    'not a date' => 'tomorrow',
    'month 13' => '2026-13-01',
    'April 31' => '2026-04-31',
    'Feb 29 in a common year' => '2026-02-29',
    'Feb 29 in 1900 (not a leap year)' => '1900-02-29',
    'year zero' => '0000-01-01',
    'empty' => '',
]);

it('knows the Gregorian leap-year rule', function (int $year, bool $leap): void {
    expect(LocalDate::isLeapYear($year))->toBe($leap)
        ->and(LocalDate::daysInMonth($year, 2))->toBe($leap ? 29 : 28);
})->with([
    [2024, true],
    [2026, false],
    [2028, true],
    [1900, false],
    [2000, true],
    [2100, false],
]);

it('accepts February 29 in leap years', function (): void {
    expect(LocalDate::parse('2028-02-29')->weekday())->toBe(Weekday::Tuesday)
        ->and(LocalDate::parse('2000-02-29')->weekday())->toBe(Weekday::Tuesday);
});

it('agrees with PHP on the day number and weekday of every day from 1999 to 2032', function (): void {
    $utc = new DateTimeZone('UTC');
    $moment = new DateTimeImmutable('1999-01-01', $utc);
    $epoch = new DateTimeImmutable('1970-01-01', $utc);
    $mismatches = [];

    while ($moment->format('Y') !== '2033') {
        $date = LocalDate::of($moment);
        $expectedDayNumber = intdiv($moment->getTimestamp() - $epoch->getTimestamp(), 86400);

        if ($date->toDayNumber() !== $expectedDayNumber
            || $date->weekday()->value !== (int) $moment->format('N')
            || ! LocalDate::fromDayNumber($expectedDayNumber)->equals($date)) {
            $mismatches[] = $moment->format('Y-m-d');
        }

        $moment = $moment->modify('+1 day');
    }

    expect($mismatches)->toBe([]);
});

it('adds days across month, year and leap-day boundaries', function (string $from, int $days, string $expected): void {
    expect(LocalDate::parse($from)->addDays($days)->toString())->toBe($expected);
})->with([
    'into March of a leap year' => ['2028-02-28', 1, '2028-02-29'],
    'past the leap day' => ['2028-02-29', 1, '2028-03-01'],
    'common year' => ['2027-02-28', 1, '2027-03-01'],
    'year end' => ['2026-12-31', 1, '2027-01-01'],
    'backwards over year end' => ['2027-01-01', -1, '2026-12-31'],
    'a leap year is 366 days' => ['2028-01-01', 366, '2029-01-01'],
    'zero' => ['2026-10-26', 0, '2026-10-26'],
]);

it('compares and measures distance between dates', function (): void {
    $a = LocalDate::parse('2026-12-30');
    $b = LocalDate::parse('2027-01-02');

    expect($a->isBefore($b))->toBeTrue()
        ->and($b->isAfter($a))->toBeTrue()
        ->and($a->daysUntil($b))->toBe(3)
        ->and($a->equals(LocalDate::parse('2026-12-30')))->toBeTrue()
        ->and($a->compare($a))->toBe(0);
});

it('reads the wall-clock date of a moment in its own timezone', function (): void {
    $utc = new DateTimeImmutable('2026-03-07 03:00', new DateTimeZone('UTC'));

    expect(LocalDate::of($utc)->toString())->toBe('2026-03-07')
        ->and(LocalDate::of($utc->setTimezone(new DateTimeZone('America/New_York')))->toString())->toBe('2026-03-06');
});

it('describes a date in words', function (): void {
    expect(LocalDate::parse('2026-07-03')->describe())->toBe('Friday 3 July 2026');
});
