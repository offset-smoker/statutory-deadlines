<?php

declare(strict_types=1);

use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\Exceptions\UnsupportedYear;
use OffsetSmoker\StatutoryDeadlines\Holidays\FixedDateHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\HolidayRule;
use OffsetSmoker\StatutoryDeadlines\Holidays\NthWeekdayHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\Observance;
use OffsetSmoker\StatutoryDeadlines\Holidays\WeekendShift;
use OffsetSmoker\StatutoryDeadlines\LocalDate;
use OffsetSmoker\StatutoryDeadlines\Weekday;

it('treats Saturday and Sunday as the weekend by default', function (): void {
    $calendar = new Calendar('Plain', []);

    expect($calendar->isBusinessDay(LocalDate::parse('2026-10-24')))->toBeFalse()
        ->and($calendar->isBusinessDay(LocalDate::parse('2026-10-25')))->toBeFalse()
        ->and($calendar->isBusinessDay(LocalDate::parse('2026-10-26')))->toBeTrue();
});

it('accepts a different weekend', function (): void {
    $calendar = (new Calendar('Plain', []))->withWeekend([Weekday::Friday, Weekday::Saturday]);

    expect($calendar->isBusinessDay(LocalDate::parse('2026-10-23')))->toBeFalse()
        ->and($calendar->isBusinessDay(LocalDate::parse('2026-10-25')))->toBeTrue()
        ->and($calendar->weekend())->toBe([Weekday::Friday, Weekday::Saturday]);
});

it('refuses a weekend with repeats or no working day', function (array $weekend): void {
    new Calendar('Bad', [], $weekend);
})->throws(InvalidDeadlineArgument::class)->with([
    'repeat' => [[Weekday::Sunday, Weekday::Sunday]],
    'every day' => [Weekday::cases()],
]);

it('adds closures without changing the original calendar', function (): void {
    $base = Calendar::pennsylvania();
    $closed = $base->withClosure('2026-12-24', 'Closed by proclamation');

    expect($base->isBusinessDay(LocalDate::parse('2026-12-24')))->toBeTrue()
        ->and($closed->isBusinessDay(LocalDate::parse('2026-12-24')))->toBeFalse()
        ->and($closed->observedOn(LocalDate::parse('2026-12-24'))[0]->name)->toBe('Closed by proclamation')
        ->and($closed->isBusinessDay(LocalDate::parse('2027-12-24')))->toBeTrue()
        ->and(count($closed->rules()))->toBe(count($base->rules()) + 1);
});

it('accepts closures as strings, LocalDates or DateTimes', function (): void {
    $calendar = Calendar::pennsylvania()
        ->withClosure('2026-10-27', 'String')
        ->withClosure(LocalDate::parse('2026-10-28'), 'LocalDate')
        ->withClosure(new DateTimeImmutable('2026-10-29 15:00'), 'DateTime');

    foreach (['2026-10-27', '2026-10-28', '2026-10-29'] as $date) {
        expect($calendar->isBusinessDay(LocalDate::parse($date)))->toBeFalse();
    }
});

it('answers for DateTime values by their own wall-clock date', function (): void {
    $calendar = Calendar::pennsylvania();

    expect($calendar->isBusinessDay(new DateTimeImmutable('2026-12-25 09:00', new DateTimeZone('America/New_York'))))->toBeFalse()
        ->and($calendar->isBusinessDay(new DateTimeImmutable('2026-12-24 23:59', new DateTimeZone('America/New_York'))))->toBeTrue();
});

it('finds the next business day across a holiday weekend and a year boundary', function (): void {
    expect(Calendar::pennsylvania()->nextBusinessDay(LocalDate::parse('2026-12-31'))->toString())->toBe('2027-01-04');
});

it('tells observed holidays apart from holidays that merely fall on a day', function (): void {
    $calendar = Calendar::federal();
    $saturday = LocalDate::parse('2026-07-04');

    expect($calendar->observedOn($saturday))->toBe([])
        ->and($calendar->holidaysFallingOn($saturday)[0]->name)->toBe('Independence Day')
        ->and($calendar->holidaysFallingOn($saturday)[0]->observed->toString())->toBe('2026-07-03');
});

it('lets another state be built from the same rules without forking', function (): void {
    // A made-up jurisdiction, to show the shape; not a real holiday list.
    $calendar = new Calendar('Example State', [
        new FixedDateHoliday('Founders Day', 3, 15, 'Example Code § 1', WeekendShift::SaturdayToFridaySundayToMonday, 'Example Code § 2'),
        new NthWeekdayHoliday('Harvest Day', 10, Weekday::Friday, NthWeekdayHoliday::LAST, 'Example Code § 1'),
    ], earliestYear: 2000);

    expect($calendar->isBusinessDay(LocalDate::parse('2026-03-16')))->toBeFalse() // Sunday Mar 15 -> Monday
        ->and($calendar->isBusinessDay(LocalDate::parse('2026-10-30')))->toBeFalse()
        ->and($calendar->merge(Calendar::federal())->isBusinessDay(LocalDate::parse('2026-11-26')))->toBeFalse();
});

it('accepts a custom HolidayRule implementation', function (): void {
    $everyFirstOfMonth = new class implements HolidayRule {
        public function name(): string
        {
            return 'First of the month';
        }

        public function citation(): string
        {
            return 'Office policy';
        }

        public function occurrencesIn(int $year): array
        {
            return array_map(
                fn(int $month): Observance => new Observance($this->name(), new LocalDate($year, $month, 1), new LocalDate($year, $month, 1), $this->citation()),
                range(1, 12),
            );
        }
    };

    $calendar = (new Calendar('Office', []))->withRule($everyFirstOfMonth);

    expect($calendar->isBusinessDay(LocalDate::parse('2026-12-01')))->toBeFalse()
        ->and($calendar->observedIn(2026))->toHaveCount(12);
});

it('keeps the later earliest year when merging', function (): void {
    $old = new Calendar('Old', [], earliestYear: 1950);

    expect($old->merge(Calendar::federal())->earliestYear)->toBe(1986);
});

it('guards every lookup against unsupported years', function (Closure $lookup): void {
    $lookup(Calendar::pennsylvania());
})->throws(UnsupportedYear::class)->with([
    'observedIn' => [fn(Calendar $c): array => $c->observedIn(1985)],
    'observedOn' => [fn(Calendar $c): array => $c->observedOn(LocalDate::parse('1985-07-04'))],
    'holidaysFallingOn' => [fn(Calendar $c): array => $c->holidaysFallingOn(LocalDate::parse('1985-07-04'))],
    'isBusinessDay' => [fn(Calendar $c): bool => $c->isBusinessDay(LocalDate::parse('1985-07-05'))],
]);

it('refuses nameless calendars and impossible earliest years', function (): void {
    expect(fn(): Calendar => new Calendar(' ', []))->toThrow(InvalidDeadlineArgument::class)
        ->and(fn(): Calendar => new Calendar('X', [], earliestYear: 0))->toThrow(InvalidDeadlineArgument::class);
});
