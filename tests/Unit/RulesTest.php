<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Cutoff;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\Explain\StepKind;
use OffsetSmoker\StatutoryDeadlines\Period;
use OffsetSmoker\StatutoryDeadlines\Rules\DeadlineRule;
use OffsetSmoker\StatutoryDeadlines\Rules\PennsylvaniaRightToKnow;
use OffsetSmoker\StatutoryDeadlines\Unit;

describe('PennsylvaniaRightToKnow', function (): void {
    it('gives five business days to respond, excluding the day of receipt', function (): void {
        $response = (new PennsylvaniaRightToKnow())->response(ny('2026-11-20 12:00'));
        $explanation = $response->explain();

        expect($response->dueDate()->toString())->toBe('2026-11-30')
            ->and($explanation->rule)->toBe('Pennsylvania Right-to-Know Law, response')
            ->and($explanation->citation)->toBe('65 P.S. § 67.901')
            ->and($explanation->ofKind(StepKind::ReceiptDay)[0]->description)->toContain('not counted')
            ->and(array_map(static fn($s): string => $s->date->toString(), $explanation->skipped()))
            ->toBe(['2026-11-21', '2026-11-22', '2026-11-26', '2026-11-28', '2026-11-29']);
    });

    it('limits an extension to 30 days after the five business days', function (): void {
        $extended = (new PennsylvaniaRightToKnow())->extendedResponse(ny('2026-11-20 12:00'));

        expect($extended->dueDate()->toString())->toBe('2026-12-30')
            ->and($extended->explain()->citation)->toBe('65 P.S. § 67.901; 65 P.S. § 67.902(b)(2)')
            ->and($extended->explain()->ofKind(StepKind::Extended)[0]->description)->toContain('(65 P.S. § 67.902(b)(2))');
    });

    it('moves a 30th day that falls on a weekend to the next business day, unless told not to', function (): void {
        $received = ny('2026-07-02 17:05');

        expect((new PennsylvaniaRightToKnow(cutoff: '16:30'))->extendedResponse($received)->dueDate()->toString())->toBe('2026-08-10')
            ->and((new PennsylvaniaRightToKnow(cutoff: '16:30', extensionRollsForward: false))->extendedResponse($received)->dueDate()->toString())->toBe('2026-08-09');
    });

    it('has no cutoff unless one is given, because the Act sets none', function (): void {
        $received = ny('2026-10-29 17:00');

        expect((new PennsylvaniaRightToKnow())->response($received)->dueDate()->toString())->toBe('2026-11-06')
            ->and((new PennsylvaniaRightToKnow(cutoff: new Cutoff(16, 30)))->response($received)->dueDate()->toString())->toBe('2026-11-09')
            ->and((new PennsylvaniaRightToKnow())->cutoff)->toBeNull();
    });

    it('counts 15 business days to appeal from the mailing date', function (): void {
        $appeal = (new PennsylvaniaRightToKnow())->appealFromResponse('2026-12-18');

        expect($appeal->dueDate()->toString())->toBe('2027-01-12')
            ->and($appeal->explain()->citation)->toBe('65 P.S. § 67.1101(a)(1)')
            ->and($appeal->received()->format('Y-m-d H:i e'))->toBe('2026-12-18 00:00 America/New_York');
    });

    it('ignores the response cutoff for the appeal clock', function (): void {
        expect((new PennsylvaniaRightToKnow(cutoff: '16:30'))->appealRule()->cutoff)->toBeNull();
    });

    it('uses the calendar it is given, closures included', function (): void {
        $calendar = Calendar::pennsylvania()->withClosure('2026-11-27', 'Office closed');
        $response = (new PennsylvaniaRightToKnow($calendar))->response(ny('2026-11-20 12:00'));

        expect($response->dueDate()->toString())->toBe('2026-12-01')
            ->and((string) $response->explain())->toContain('Skipped Friday 27 November 2026: Office closed.');
    });

    it('can count federal holidays as well', function (): void {
        // Juneteenth, a Friday in 2026, is a federal holiday but not a Pennsylvania one.
        $received = ny('2026-06-17 10:00');

        expect((new PennsylvaniaRightToKnow())->response($received)->dueDate()->toString())->toBe('2026-06-24')
            ->and((new PennsylvaniaRightToKnow(Calendar::pennsylvaniaAndFederal()))->response($received)->dueDate()->toString())->toBe('2026-06-25');
    });

    it('keeps the class of the date it is given', function (): void {
        expect((new PennsylvaniaRightToKnow())->response(CarbonImmutable::parse('2026-11-20 12:00', 'America/New_York'))->due())
            ->toBeInstanceOf(CarbonImmutable::class);
    });

    it('converts receipt times to the office timezone', function (): void {
        // 02:00 UTC on Monday is 21:00 Sunday in New York.
        $utc = new DateTimeImmutable('2026-11-23 02:00', new DateTimeZone('UTC'));

        expect((new PennsylvaniaRightToKnow())->response($utc)->dueDate()->toString())->toBe('2026-11-30')
            ->and((new PennsylvaniaRightToKnow(timezone: 'UTC'))->response($utc)->dueDate()->toString())->toBe('2026-12-01');
    });

    it('refuses an unknown timezone or malformed cutoff when built', function (): void {
        expect(fn(): PennsylvaniaRightToKnow => new PennsylvaniaRightToKnow(timezone: 'Mars/Olympus'))->toThrow(InvalidDeadlineArgument::class)
            ->and(fn(): PennsylvaniaRightToKnow => new PennsylvaniaRightToKnow(cutoff: '5pm'))->toThrow(InvalidDeadlineArgument::class);
    });
});

describe('DeadlineRule', function (): void {
    it('encodes another statute\'s clock without new code', function (): void {
        // A made-up statute, to show the shape: ten business days, the day of
        // receipt counted, a 17:00 cutoff, and a 15-business-day extension.
        $rule = new DeadlineRule(
            name: 'Example Records Act, response',
            citation: 'Example Code § 1-101',
            period: new Period(10, Unit::BusinessDays),
            extensions: [new Period(15, Unit::BusinessDays, citation: 'Example Code § 1-102')],
            countReceiptDay: true,
            cutoff: new Cutoff(17),
        );

        $deadline = $rule->apply(ny('2026-10-26 16:00'), Calendar::federal());

        // Day 1 is Monday 26 October, so the response is due 6 November; the
        // extension then skips Veterans Day and Thanksgiving.
        expect($deadline->dueDate()->toString())->toBe('2026-12-01')
            ->and($deadline->explain()->rule)->toBe('Example Records Act, response')
            ->and($deadline->explain()->ofKind(StepKind::ReceiptDay)[0]->description)->toBe('The day of receipt counts as day 1.')
            ->and((string) $deadline->explain())->toContain('(Example Code § 1-102)');
    });

    it('refuses a rule whose period and extensions exceed the bound together', function (): void {
        new DeadlineRule('Example', 'Example Code § 3', new Period(3000, Unit::BusinessDays), [
            new Period(600, Unit::BusinessDays),
            new Period(61, Unit::CalendarDays),
        ]);
    })->throws(InvalidDeadlineArgument::class, 'at most 3660 days in all');

    it('applies from a date as well as from a moment', function (): void {
        $rule = new DeadlineRule('Example', 'Example Code § 2', new Period(3, Unit::BusinessDays));

        expect($rule->applyOnDate('2026-12-23', Calendar::pennsylvania(), 'America/New_York')->dueDate()->toString())->toBe('2026-12-29');
    });

    it('needs a name and a citation', function (): void {
        new DeadlineRule('Example', ' ', new Period(1, Unit::BusinessDays));
    })->throws(InvalidDeadlineArgument::class);
});
