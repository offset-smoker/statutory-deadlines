<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Cutoff;
use OffsetSmoker\StatutoryDeadlines\Deadline;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\Explain\Step;
use OffsetSmoker\StatutoryDeadlines\Explain\StepKind;
use OffsetSmoker\StatutoryDeadlines\Unit;

function pa(string $time, int $businessDays = 5): Deadline
{
    return Deadline::from(ny($time), Calendar::pennsylvania())->businessDays($businessDays);
}

/**
 * @return list<string>
 */
function skippedDates(Deadline $deadline): array
{
    return array_map(static fn(Step $step): string => $step->date->toString(), $deadline->explain()->skipped());
}

describe('business days', function (): void {
    it('excludes the day of receipt by default', function (): void {
        expect(pa('2026-10-26 10:00')->dueDate()->toString())->toBe('2026-11-02');
    });

    it('counts the day of receipt when asked', function (): void {
        expect(pa('2026-10-26 10:00')->countReceiptDay()->dueDate()->toString())->toBe('2026-10-30')
            ->and(pa('2026-10-26 10:00')->countReceiptDay()->countReceiptDay(false)->dueDate()->toString())->toBe('2026-11-02');
    });

    it('skips weekends and holidays, saying which and why', function (): void {
        // Election Day (first Tuesday after the first Monday) is a Pennsylvania holiday.
        $deadline = pa('2026-10-30 10:00');

        expect($deadline->dueDate()->toString())->toBe('2026-11-09')
            ->and(skippedDates($deadline))->toBe(['2026-10-31', '2026-11-01', '2026-11-03', '2026-11-07', '2026-11-08'])
            ->and($deadline->explain()->skipped()[2]->description)->toBe('Skipped Tuesday 3 November 2026: Election Day.')
            ->and($deadline->explain()->skipped()[2]->holidays[0]->name)->toBe('Election Day')
            ->and($deadline->explain()->skipped()[0]->description)->toBe('Skipped Saturday 31 October 2026: Saturday (weekend).');
    });

    it('numbers each counted day', function (): void {
        $counted = pa('2026-10-30 10:00')->explain()->ofKind(StepKind::Counted);

        expect(array_map(static fn(Step $step): ?int => $step->day, $counted))->toBe([1, 2, 3, 4, 5])
            ->and($counted[4]->description)->toBe('Business day 5 of 5.');
    });

    it('starts counting after a weekend receipt when there is no cutoff', function (): void {
        expect(pa('2026-10-24 11:00')->dueDate()->toString())->toBe('2026-10-30');
    });

    it('does not let a weekend receipt day count as day 1', function (): void {
        $deadline = pa('2026-10-24 11:00')->countReceiptDay();

        expect($deadline->dueDate()->toString())->toBe('2026-10-30')
            ->and($deadline->explain()->ofKind(StepKind::ReceiptDay)[0]->description)->toContain('not a business day');
    });

    it('crosses a year boundary over New Year\'s Day', function (): void {
        expect(pa('2026-12-29 09:00')->dueDate()->toString())->toBe('2027-01-06');
    });

    it('applies each calendar\'s own observance rule at a year boundary', function (): void {
        // New Year's Day 2028 is a Saturday: federally observed Friday 31 December 2027, not in Pennsylvania.
        $federal = Deadline::from(ny('2027-12-30 09:00'), Calendar::federal())->businessDays(5);
        $pennsylvania = pa('2027-12-30 09:00');

        expect($federal->dueDate()->toString())->toBe('2028-01-07')
            ->and($pennsylvania->dueDate()->toString())->toBe('2028-01-06')
            ->and($federal->explain()->skipped()[0]->description)
            ->toBe("Skipped Friday 31 December 2027: New Year's Day (Saturday 1 January 2028, observed Friday 31 December 2027) under 5 U.S.C. § 6103(b)(1) (Saturday to Friday); Executive Order 11582, § 3(a) (Sunday to Monday).")
            ->and($federal->explain()->skipped()[1]->description)
            ->toBe("Skipped Saturday 1 January 2028: Saturday (weekend); New Year's Day falls on this day and is observed Friday 31 December 2027.");
    });

    it('counts across February 29 in a leap year', function (): void {
        expect(pa('2028-02-25 09:00')->dueDate()->toString())->toBe('2028-03-03');
    });

    it('refuses period lengths outside 1 to 3660 days', function (int $count): void {
        pa('2026-10-26 10:00', $count);
    })->throws(InvalidDeadlineArgument::class)->with([0, -5, 3661]);

    it('handles a long period without drifting', function (): void {
        // 250 business days from Monday 5 January 2026 on the federal calendar.
        $deadline = Deadline::from(ny('2026-01-05 09:00'), Calendar::federal())->businessDays(250);

        expect($deadline->dueDate()->toString())->toBe('2027-01-05')
            ->and($deadline->explain()->ofKind(StepKind::Counted))->toHaveCount(250);
    });
});

describe('calendar days', function (): void {
    it('counts every day and lands on a business day as it is', function (): void {
        expect(Deadline::from(ny('2028-02-15 09:00'), Calendar::pennsylvania())->calendarDays(30)->dueDate()->toString())->toBe('2028-03-16')
            ->and(Deadline::from(ny('2027-02-15 09:00'), Calendar::pennsylvania())->calendarDays(30)->dueDate()->toString())->toBe('2027-03-17');
    });

    it('rolls a last day that falls on a holiday weekend to the next business day', function (): void {
        // Day 30 is Christmas, a Friday; Saturday and Sunday follow.
        $deadline = Deadline::fromDate('2026-11-25', Calendar::pennsylvania(), 'America/New_York')->calendarDays(30);
        $explanation = $deadline->explain();

        expect($deadline->dueDate()->toString())->toBe('2026-12-28')
            ->and(skippedDates($deadline))->toBe(['2026-12-25', '2026-12-26', '2026-12-27'])
            ->and($explanation->ofKind(StepKind::RolledForward)[0]->description)
            ->toBe('Day 30 is not a business day, so the period ends on the next business day, Monday 28 December 2026.');
    });

    it('keeps the last day as it falls when roll-forward is off', function (): void {
        $deadline = Deadline::fromDate('2026-11-25', Calendar::pennsylvania(), 'America/New_York')->calendarDays(30, rollForward: false);

        expect($deadline->dueDate()->toString())->toBe('2026-12-25')
            ->and($deadline->explain()->skipped())->toBe([]);
    });

    it('counts the receipt day as day 1 when asked, even on a weekend, then rolls forward', function (): void {
        $deadline = Deadline::from(ny('2026-10-24 11:00'), Calendar::pennsylvania())->countReceiptDay()->calendarDays(1);

        expect($deadline->dueDate()->toString())->toBe('2026-10-26');
    });
});

describe('extensions', function (): void {
    it('counts an extension from the day after the current due date', function (): void {
        $deadline = pa('2026-10-26 10:00')->extendBy(30, Unit::CalendarDays);

        expect($deadline->dueDate()->toString())->toBe('2026-12-02')
            ->and($deadline->explain()->ofKind(StepKind::Extended)[0]->description)
            ->toBe('Extended by 30 calendar days, counted from the day after Monday 2 November 2026.');
    });

    it('stacks extensions in the order they are added', function (): void {
        $calendarThenBusiness = pa('2026-10-26 10:00')->extendBy(30, Unit::CalendarDays)->extendBy(10, Unit::BusinessDays);
        $businessThenCalendar = pa('2026-10-26 10:00')->extendBy(10, Unit::BusinessDays)->extendBy(30, Unit::CalendarDays);

        expect($calendarThenBusiness->dueDate()->toString())->toBe('2026-12-16')
            ->and($businessThenCalendar->dueDate()->toString())->toBe('2026-12-18')
            ->and($calendarThenBusiness->explain()->ofKind(StepKind::Extended))->toHaveCount(2);
    });

    it('shows an extension\'s citation in the explanation', function (): void {
        $step = pa('2026-10-26 10:00')->extendBy(1, Unit::BusinessDays, citation: 'Example Code § 9')->explain()->ofKind(StepKind::Extended)[0];

        expect($step->description)->toBe('Extended by 1 business day (Example Code § 9), counted from the day after Monday 2 November 2026.');
    });

    it('rolls a calendar-day extension unless told not to', function (): void {
        $base = Deadline::from(ny('2026-07-02 17:05'), Calendar::pennsylvania())->withCutoff('16:30')->businessDays(5);

        expect($base->dueDate()->toString())->toBe('2026-07-10')
            ->and($base->extendBy(30, Unit::CalendarDays)->dueDate()->toString())->toBe('2026-08-10')
            ->and($base->extendBy(30, Unit::CalendarDays, rollForward: false)->dueDate()->toString())->toBe('2026-08-09');
    });

    it('can extend a deadline that has no first period, from the receipt date', function (): void {
        expect(Deadline::from(ny('2026-10-26 10:00'), Calendar::pennsylvania())->extendBy(2, Unit::BusinessDays)->dueDate()->toString())->toBe('2026-10-28')
            ->and(Deadline::from(ny('2026-10-26 10:00'), Calendar::pennsylvania())->dueDate()->toString())->toBe('2026-10-26');
    });
});

describe('receipt cutoffs', function (): void {
    it('treats receipt one second before the cutoff as that day', function (): void {
        $deadline = pa('2026-10-29 16:29:59')->withCutoff('16:30');

        expect($deadline->deemedReceived()->toString())->toBe('2026-10-29')
            ->and($deadline->dueDate()->toString())->toBe('2026-11-06')
            ->and($deadline->explain()->ofKind(StepKind::CutoffMet)[0]->description)
            ->toBe('Received before the 16:30 cutoff on a business day, so it counts as received that day.');
    });

    it('treats receipt at the cutoff as the next business day', function (): void {
        $deadline = pa('2026-10-29 16:30:00')->withCutoff(new Cutoff(16, 30));

        expect($deadline->deemedReceived()->toString())->toBe('2026-10-30')
            ->and($deadline->dueDate()->toString())->toBe('2026-11-09')
            ->and($deadline->explain()->ofKind(StepKind::DeemedReceived)[0]->description)
            ->toBe('Received at or after the 16:30 cutoff, so it is deemed received on the next business day, Friday 30 October 2026.');
    });

    it('moves after-hours receipt over a holiday', function (): void {
        // Wednesday after hours; Thursday is Thanksgiving, so Friday is the deemed receipt day.
        $wednesday = pa('2026-11-25 17:00')->withCutoff('16:30');

        expect($wednesday->deemedReceived()->toString())->toBe('2026-11-27')
            ->and(skippedDates($wednesday)[0])->toBe('2026-11-26');
    });

    it('moves receipt on a closed day to the next business day', function (): void {
        $deadline = pa('2026-10-24 11:00')->withCutoff('16:30');

        expect($deadline->deemedReceived()->toString())->toBe('2026-10-26')
            ->and($deadline->dueDate()->toString())->toBe('2026-11-02')
            ->and($deadline->explain()->ofKind(StepKind::DeemedReceived)[0]->description)->toStartWith('Received on a day that is not a business day');
    });

    it('can be removed again', function (): void {
        expect(pa('2026-10-29 17:00')->withCutoff('16:30')->withoutCutoff()->dueDate()->toString())->toBe('2026-11-06');
    });

    it('refuses cutoffs that are not HH:MM 24-hour times', function (string $time): void {
        Cutoff::parse($time);
    })->throws(InvalidDeadlineArgument::class)->with(['4:30', '16:30:00', '4:30pm', '24:00', '12:60', '', "16:30\n"]);

    it('refuses impossible hours and minutes', function (): void {
        expect(fn(): Cutoff => new Cutoff(24))->toThrow(InvalidDeadlineArgument::class)
            ->and(fn(): Cutoff => new Cutoff(9, -1))->toThrow(InvalidDeadlineArgument::class)
            ->and((string) new Cutoff(9, 5))->toBe('09:05');
    });
});

describe('timezones and daylight saving', function (): void {
    it('decides the day of receipt in the deadline\'s timezone', function (): void {
        // 03:00 UTC on Friday is 23:00 Thursday in New York.
        $utc = new DateTimeImmutable('2026-10-30 03:00', new DateTimeZone('UTC'));

        expect(Deadline::from($utc, Calendar::pennsylvania(), 'America/New_York')->businessDays(5)->dueDate()->toString())->toBe('2026-11-06')
            ->and(Deadline::from($utc, Calendar::pennsylvania())->businessDays(5)->dueDate()->toString())->toBe('2026-11-09')
            ->and(Deadline::from($utc, Calendar::pennsylvania(), new DateTimeZone('America/New_York'))->received()->format('Y-m-d H:i'))->toBe('2026-10-29 23:00');
    });

    it('applies the cutoff in the deadline\'s timezone', function (): void {
        // 20:45 UTC is 16:45 in New York during daylight time.
        $utc = new DateTimeImmutable('2026-10-29 20:45', new DateTimeZone('UTC'));

        expect(Deadline::from($utc, Calendar::pennsylvania(), 'America/New_York')->withCutoff('16:30')->deemedReceived()->toString())->toBe('2026-10-30');
    });

    it('is unaffected by the spring-forward day', function (): void {
        // Daylight time begins Sunday 8 March 2026 in New York.
        $deadline = pa('2026-03-06 12:00');

        expect($deadline->dueDate()->toString())->toBe('2026-03-13')
            ->and($deadline->due()->format('Y-m-d H:i P'))->toBe('2026-03-13 00:00 -04:00')
            ->and($deadline->received()->format('P'))->toBe('-05:00');
    });

    it('is unaffected by the fall-back day', function (): void {
        // Daylight time ends Sunday 1 November 2026 in New York.
        $deadline = pa('2026-10-30 12:00');

        expect($deadline->due()->format('Y-m-d H:i P'))->toBe('2026-11-09 00:00 -05:00');
    });

    it('counts calendar days across a 23-hour day by date, not by hours', function (): void {
        $deadline = Deadline::from(ny('2026-03-07 23:30'), Calendar::pennsylvania())->calendarDays(2);

        expect($deadline->due()->format('Y-m-d H:i P'))->toBe('2026-03-09 00:00 -04:00');
    });

    it('refuses an unknown timezone', function (): void {
        Deadline::from(ny('2026-10-26 10:00'), Calendar::pennsylvania(), 'America/Nowhere');
    })->throws(InvalidDeadlineArgument::class, '"America/Nowhere" is not a known timezone.');
});

describe('dates in and out', function (): void {
    it('never modifies a mutable DateTime and returns a DateTimeImmutable', function (): void {
        $received = new DateTime('2026-10-26 10:00', new DateTimeZone('America/New_York'));
        $deadline = Deadline::from($received, Calendar::pennsylvania(), 'UTC')->businessDays(5);

        expect($deadline->due())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($deadline->due()::class)->toBe(DateTimeImmutable::class)
            ->and($received->format('Y-m-d H:i e'))->toBe('2026-10-26 10:00 America/New_York');
    });

    it('returns CarbonImmutable for CarbonImmutable input, and leaves the input alone', function (): void {
        $received = CarbonImmutable::parse('2026-10-26 10:00', 'America/New_York');
        $due = Deadline::from($received, Calendar::pennsylvania())->businessDays(5)->due();

        expect($due)->toBeInstanceOf(CarbonImmutable::class)
            ->and($due->toDateString())->toBe('2026-11-02')
            ->and($received->toDateTimeString())->toBe('2026-10-26 10:00:00');
    });

    it('accepts mutable Carbon without changing it', function (): void {
        $received = Carbon::parse('2026-10-26 10:00', 'America/New_York');
        Deadline::from($received, Calendar::pennsylvania(), 'UTC')->businessDays(5)->due();

        expect($received->toDateTimeString())->toBe('2026-10-26 10:00:00')
            ->and($received->getTimezone()->getName())->toBe('America/New_York');
    });

    it('starts date-based clocks at midnight in the given timezone', function (): void {
        $deadline = Deadline::fromDate('2026-12-18', Calendar::pennsylvania(), 'America/New_York');

        expect($deadline->received()->format('Y-m-d H:i e'))->toBe('2026-12-18 00:00 America/New_York')
            ->and(fn(): Deadline => Deadline::fromDate('2026-12-32', Calendar::pennsylvania(), 'UTC'))->toThrow(InvalidDeadlineArgument::class);
    });

    it('reports whether the deadline was missed at a moment, in its own timezone', function (): void {
        $deadline = pa('2026-10-26 10:00');

        expect($deadline->isMissedAt(ny('2026-11-02 23:59:59')))->toBeFalse()
            ->and($deadline->isMissedAt(ny('2026-11-03 00:00')))->toBeTrue()
            // 03:00 UTC on 3 November is still 2 November in New York.
            ->and($deadline->isMissedAt(new DateTimeImmutable('2026-11-03 03:00', new DateTimeZone('UTC'))))->toBeFalse();
    });

    it('is immutable: every method returns a new deadline', function (): void {
        $base = pa('2026-10-26 10:00');
        $base->extendBy(30, Unit::CalendarDays);
        $base->withCutoff('09:00');
        $base->countReceiptDay();

        expect($base->dueDate()->toString())->toBe('2026-11-02');
    });
});

describe('the explanation', function (): void {
    it('reads as text, step by step', function (): void {
        $text = (string) pa('2026-11-20 12:00')->governedBy('Example rule', 'Example Code § 1')->explain();

        expect($text)->toContain('Rule: Example rule (Example Code § 1).')
            ->and($text)->toContain('Calendar: Pennsylvania. Timezone: America/New_York.')
            ->and($text)->toContain('2026-11-20  received        Received Friday 20 November 2026 at 12:00:00 (America/New_York).')
            ->and($text)->toContain('2026-11-26  skipped         Skipped Thursday 26 November 2026: Thanksgiving Day.')
            ->and($text)->toContain('2026-11-30  due             Due Monday 30 November 2026.');
    });

    it('lines up the step text in one column, whatever the step kind', function (): void {
        $text = (string) pa('2026-07-02 17:05')->withCutoff('16:30')->businessDays(5)->extendBy(30, Unit::CalendarDays)->explain();
        $stepLines = array_values(array_filter(explode("\n", $text), static fn(string $line): bool => preg_match('/^\d{4}-\d{2}-\d{2}  /', $line) === 1));
        // Where the description starts: after the date, the kind and the padding.
        $columns = array_unique(array_map(static function (string $line): int {
            preg_match('/^\S+  \S+ +/', $line, $match);

            return strlen($match[0]);
        }, $stepLines));

        expect($text)->toContain('deemed_received')
            ->and($text)->toContain('rolled_forward')
            ->and($columns)->toHaveCount(1);
    });

    it('exports a JSON-ready array', function (): void {
        $array = pa('2026-11-20 12:00')->explain()->toArray();
        $json = json_encode($array, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        expect($array['due'])->toBe('2026-11-30')
            ->and($array['rule'])->toBeNull()
            ->and($array['steps'][0]['kind'])->toBe('received')
            ->and($json)->toContain('"name":"Thanksgiving Day"')
            ->and($json)->toContain('"citation":"Act of May 31, 1893, P.L. 188, No. 138, § 1 (44 P.S. § 11)"');
    });

    it('ends with the due date', function (): void {
        $steps = pa('2026-11-20 12:00')->explain()->steps;

        expect($steps[count($steps) - 1]->kind)->toBe(StepKind::Due)
            ->and($steps[0]->kind)->toBe(StepKind::Received);
    });

    it('refuses an unnamed rule', function (): void {
        pa('2026-11-20 12:00')->governedBy('', 'x');
    })->throws(InvalidDeadlineArgument::class);
});
