<?php

declare(strict_types=1);

use OffsetSmoker\StatutoryDeadlines\BuiltInCalendar;
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Exceptions\UnsupportedYear;
use OffsetSmoker\StatutoryDeadlines\Holidays\Observance;
use OffsetSmoker\StatutoryDeadlines\LocalDate;

/**
 * @return array<string, string> holiday name => observed date
 */
function observedByName(Calendar $calendar, int $year): array
{
    $byName = [];

    foreach ($calendar->observedIn($year) as $observance) {
        $byName[$observance->name] = $observance->observed->toString();
    }

    return $byName;
}

describe('the federal calendar', function (): void {
    it('matches every date OPM published for each year from 2012 to 2030', function (int $year, array $published): void {
        // OPM lists a Saturday New Year's Day under the year of the holiday,
        // observed on 31 December before it; this library lists a holiday
        // under the year it is observed, so look it up from the holiday's year.
        $calendar = Calendar::federal();
        $actual = [];

        foreach ([$year - 1, $year, $year + 1] as $candidate) {
            foreach ($calendar->observedIn($candidate) as $observance) {
                if ($observance->date->year === $year) {
                    $actual[$observance->name] = $observance->observed->toString();
                }
            }
        }

        ksort($actual);
        ksort($published);

        expect($actual)->toBe($published);
    })->with(function (): Generator {
        foreach (holidayFixture('opm-federal-holidays') as $year => $published) {
            yield (string) $year => [$year, $published];
        }
    });

    it('observes a Saturday New Year\'s Day on the previous December 31', function (): void {
        $calendar = Calendar::federal();
        $december31 = LocalDate::parse('2021-12-31');

        expect($calendar->isBusinessDay($december31))->toBeFalse()
            ->and($calendar->observedOn($december31)[0]->name)->toBe("New Year's Day")
            ->and($calendar->observedOn($december31)[0]->date->toString())->toBe('2022-01-01')
            ->and(observedByName($calendar, 2021)["New Year's Day"])->toBe('2021-12-31')
            ->and(observedByName($calendar, 2022))->not->toHaveKey("New Year's Day");
    });

    it('observes Sunday holidays on Monday and Saturday holidays on Friday', function (string $date, bool $businessDay): void {
        expect(Calendar::federal()->isBusinessDay(LocalDate::parse($date)))->toBe($businessDay);
    })->with([
        'Juneteenth 2022 (Sunday) observed Monday' => ['2022-06-20', false],
        'Independence Day 2026 (Saturday) observed Friday' => ['2026-07-03', false],
        'Veterans Day 2029 (Sunday) observed Monday' => ['2029-11-12', false],
        'Christmas 2027 (Saturday) observed Friday' => ['2027-12-24', false],
        'the Monday after a Saturday holiday is open' => ['2026-07-06', true],
    ]);

    it('has no Juneteenth before 2021', function (): void {
        expect(observedByName(Calendar::federal(), 2020))->not->toHaveKey('Juneteenth National Independence Day')
            ->and(observedByName(Calendar::federal(), 2021)['Juneteenth National Independence Day'])->toBe('2021-06-18');
    });

    it('lists the holidays observed in a year, including one borrowed from the next', function (): void {
        $in2026 = Calendar::federal()->observedIn(2026);
        $in2027 = Calendar::federal()->observedIn(2027);
        $last2027 = $in2027[count($in2027) - 1];

        expect($in2026)->toHaveCount(11)
            // New Year's Day 2028 is a Saturday, observed Friday 31 December 2027.
            ->and($in2027)->toHaveCount(12)
            ->and($last2027->name)->toBe("New Year's Day")
            ->and($last2027->date->toString())->toBe('2028-01-01')
            ->and($last2027->observed->toString())->toBe('2027-12-31');
    });

    it('cites 5 U.S.C. 6103 for every holiday and the observance rules for shifted ones', function (): void {
        foreach (Calendar::federal()->observedIn(2026) as $observance) {
            expect($observance->citation)->toBe('5 U.S.C. § 6103(a)');
        }

        $independenceDay = Calendar::federal()->observedOn(LocalDate::parse('2026-07-03'))[0];

        expect($independenceDay->observedCitation)->toContain('6103(b)(1)')
            ->and($independenceDay->observedCitation)->toContain('Executive Order 11582');
    });

    it('refuses years before 1986', function (): void {
        Calendar::federal()->isBusinessDay(LocalDate::parse('1985-12-31'));
    })->throws(UnsupportedYear::class, 'describes 1986 and later; 1985 is not supported');
});

describe('the Pennsylvania calendar', function (): void {
    it('matches the Legal Holiday Law for each year from 2024 to 2030', function (int $year, array $expected): void {
        $actual = observedByName(Calendar::pennsylvania(), $year);

        ksort($actual);
        ksort($expected);

        expect($actual)->toBe($expected);
    })->with(function (): Generator {
        foreach (holidayFixture('pennsylvania-holidays') as $year => $expected) {
            yield (string) $year => [$year, $expected];
        }
    });

    it('moves Sunday holidays to Monday', function (string $sunday, string $monday): void {
        $calendar = Calendar::pennsylvania();

        expect(LocalDate::parse($sunday)->weekday()->name)->toBe('Sunday')
            ->and($calendar->isBusinessDay(LocalDate::parse($monday)))->toBeFalse()
            ->and($calendar->observedOn(LocalDate::parse($monday))[0]->observedCitation)->toContain('§ 2(a)');
    })->with([
        'Flag Day 2026' => ['2026-06-14', '2026-06-15'],
        'Independence Day 2027' => ['2027-07-04', '2027-07-05'],
        'Veterans\' Day 2029' => ['2029-11-11', '2029-11-12'],
        'New Year\'s Day 2023' => ['2023-01-01', '2023-01-02'],
        'Christmas Day 2022' => ['2022-12-25', '2022-12-26'],
    ]);

    it('does not move Saturday holidays to Friday', function (string $saturday, string $friday): void {
        $calendar = Calendar::pennsylvania();

        expect($calendar->isBusinessDay(LocalDate::parse($friday)))->toBeTrue()
            ->and($calendar->observedOn(LocalDate::parse($saturday))[0]->isShifted())->toBeFalse();
    })->with([
        'Independence Day 2026' => ['2026-07-04', '2026-07-03'],
        'Christmas Day 2027' => ['2027-12-25', '2027-12-24'],
        'New Year\'s Day 2028' => ['2028-01-01', '2027-12-31'],
    ]);

    it('includes the holidays the federal list lacks and leaves out Juneteenth', function (): void {
        $holidays = observedByName(Calendar::pennsylvania(), 2026);

        expect($holidays)->toHaveKeys(['Good Friday', 'Flag Day', 'Election Day'])
            ->and($holidays)->not->toHaveKey('Juneteenth National Independence Day')
            ->and(Calendar::pennsylvania()->isBusinessDay(LocalDate::parse('2026-06-19')))->toBeTrue();
    });

    it('puts Election Day on the Tuesday after the first Monday even when November starts on a Tuesday', function (): void {
        expect(observedByName(Calendar::pennsylvania(), 2022)['Election Day'])->toBe('2022-11-08');
    });

    it('cites the Legal Holiday Law', function (): void {
        foreach (Calendar::pennsylvania()->observedIn(2026) as $observance) {
            expect($observance->citation)->toContain('P.L. 188, No. 138')
                ->and($observance->citation)->toContain('44 P.S. § 11');
        }
    });
});

describe('the combined calendar', function (): void {
    it('is closed on the holidays of both', function (): void {
        $combined = Calendar::pennsylvaniaAndFederal();

        expect($combined->isBusinessDay(LocalDate::parse('2026-06-19')))->toBeFalse() // Juneteenth (federal)
            ->and($combined->isBusinessDay(LocalDate::parse('2026-04-03')))->toBeFalse() // Good Friday (Pennsylvania)
            ->and($combined->isBusinessDay(LocalDate::parse('2026-07-03')))->toBeFalse() // federal Friday observance
            ->and($combined->observedOn(LocalDate::parse('2026-12-25')))->toHaveCount(2)
            ->and($combined->name)->toBe('Pennsylvania and United States federal');
    });

    it('lists the same day once per calendar that observes it', function (): void {
        $names = array_map(static fn(Observance $o): string => $o->citation, Calendar::pennsylvaniaAndFederal()->observedOn(LocalDate::parse('2026-01-01')));

        expect($names)->toHaveCount(2);
    });
});

it('builds every built-in calendar from its config name', function (string $name, string $calendarName): void {
    expect(BuiltInCalendar::from($name)->calendar()->name)->toBe($calendarName);
})->with([
    ['pennsylvania', 'Pennsylvania'],
    ['federal', 'United States federal'],
    ['pennsylvania+federal', 'Pennsylvania and United States federal'],
]);
