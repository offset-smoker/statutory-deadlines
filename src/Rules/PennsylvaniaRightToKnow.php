<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Rules;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Cutoff;
use OffsetSmoker\StatutoryDeadlines\Deadline;
use OffsetSmoker\StatutoryDeadlines\LocalDate;
use OffsetSmoker\StatutoryDeadlines\Period;
use OffsetSmoker\StatutoryDeadlines\Support\Timezone;
use OffsetSmoker\StatutoryDeadlines\Unit;

/**
 * The response and appeal clocks of Pennsylvania's Right-to-Know Law,
 * Act of Feb. 14, 2008, P.L. 6, No. 3 (65 P.S. § 67.101 et seq.), as the
 * Pennsylvania General Assembly publishes it at
 * https://www.palegis.us/statutes/unconsolidated/law-information/view-statute?txtType=PDF&SessYr=2008&ActNum=0003.&SessInd=0
 *
 * What the statute says, and what this class does with it:
 *
 * - § 901 (65 P.S. § 67.901): "The time for response shall not exceed five
 *   business days from the date the written request is received by the
 *   open-records officer for an agency." response() counts five business
 *   days, starting the day after receipt: 1 Pa.C.S. § 1908 computes a
 *   statutory period "to exclude the first and include the last day".
 *
 * - § 902(b)(2) (65 P.S. § 67.902(b)(2)): an agency that invokes an
 *   extension must state "a reasonable date that a response is expected to
 *   be provided"; if that date "is in excess of 30 days, following the five
 *   business days allowed for in section 901, the request for access shall
 *   be deemed denied" unless the requester agreed in writing.
 *   extendedResponse() is therefore the latest date the notice can name
 *   without the requester's written agreement: 30 days after the § 901 due
 *   date. The statute says "30 days" without "business", so they are counted
 *   as calendar days. When the 30th day is a weekend day or holiday the date
 *   moves to the next business day, applying the last-day rule of
 *   1 Pa.C.S. § 1908; pass $extensionRollsForward: false to keep the 30th
 *   day as it falls.
 *
 * - § 1101(a)(1) (65 P.S. § 67.1101(a)(1)): an appeal must be filed "within
 *   15 business days of the mailing date of the agency's response".
 *   appealFromResponse() counts 15 business days from the mailing date.
 *
 * What the statute does not settle, so it is left to the caller:
 *
 * - "Business day" is not defined in the Act. The default calendar is
 *   Pennsylvania's statutory legal holidays (44 P.S. § 11); pass a calendar
 *   with the agency's own closures (withClosure()) or the federal holidays
 *   too (Calendar::pennsylvaniaAndFederal()).
 * - The Act sets no after-hours rule. Some agencies' RTKL policies treat a
 *   request received after the close of business as received the next
 *   business day; pass $cutoff with that agency's closing time to apply one.
 *   A cutoff also deems a request received on a weekend day or holiday to be
 *   received the next business day, which makes the response due one
 *   business day later than with no cutoff. There is no cutoff by default.
 * - The appeal period from a deemed denial (also § 1101(a)(1)) is not
 *   computed, because the Act does not name the day a deemed denial occurs.
 */
final readonly class PennsylvaniaRightToKnow
{
    public const string ACT = 'Pennsylvania Right-to-Know Law';

    public const string RESPONSE_CITATION = '65 P.S. § 67.901';

    public const string EXTENSION_CITATION = '65 P.S. § 67.902(b)(2)';

    public const string APPEAL_CITATION = '65 P.S. § 67.1101(a)(1)';

    public Calendar $calendar;

    public DateTimeZone $timezone;

    public ?Cutoff $cutoff;

    public function __construct(
        ?Calendar $calendar = null,
        DateTimeZone|string $timezone = 'America/New_York',
        Cutoff|string|null $cutoff = null,
        public bool $extensionRollsForward = true,
    ) {
        $this->calendar = $calendar ?? Calendar::pennsylvania();
        $this->timezone = Timezone::resolve($timezone);
        $this->cutoff = is_string($cutoff) ? Cutoff::parse($cutoff) : $cutoff;
    }

    public function responseRule(): DeadlineRule
    {
        return new DeadlineRule(
            name: self::ACT . ', response',
            citation: self::RESPONSE_CITATION,
            period: new Period(5, Unit::BusinessDays, citation: self::RESPONSE_CITATION),
            cutoff: $this->cutoff,
        );
    }

    public function extendedResponseRule(): DeadlineRule
    {
        return new DeadlineRule(
            name: self::ACT . ', latest response date under an extension notice',
            citation: self::RESPONSE_CITATION . '; ' . self::EXTENSION_CITATION,
            period: new Period(5, Unit::BusinessDays, citation: self::RESPONSE_CITATION),
            extensions: [new Period(30, Unit::CalendarDays, $this->extensionRollsForward, self::EXTENSION_CITATION)],
            cutoff: $this->cutoff,
        );
    }

    public function appealRule(): DeadlineRule
    {
        // The clock runs from a mailing date the agency chose, not from a
        // receipt, so no receipt cutoff applies.
        return new DeadlineRule(
            name: self::ACT . ', appeal from a response',
            citation: self::APPEAL_CITATION,
            period: new Period(15, Unit::BusinessDays, citation: self::APPEAL_CITATION),
        );
    }

    /**
     * The § 901 response deadline; also the deadline for a § 902(b)(1)
     * extension notice, which must be sent "within five business days of
     * receipt".
     *
     * @template TFrom of DateTimeInterface
     *
     * @param  TFrom  $received  When the open-records officer received the written request.
     * @return (TFrom is DateTimeImmutable ? Deadline<TFrom> : Deadline<DateTimeImmutable>)
     */
    public function response(DateTimeInterface $received): Deadline
    {
        return $this->responseRule()->apply($received, $this->calendar, $this->timezone);
    }

    /**
     * The latest response date an extension notice can name without the
     * requester's written agreement.
     *
     * @template TFrom of DateTimeInterface
     *
     * @param  TFrom  $received
     * @return (TFrom is DateTimeImmutable ? Deadline<TFrom> : Deadline<DateTimeImmutable>)
     */
    public function extendedResponse(DateTimeInterface $received): Deadline
    {
        return $this->extendedResponseRule()->apply($received, $this->calendar, $this->timezone);
    }

    /**
     * The last day to appeal an agency's response, from the response's
     * mailing date (a date, such as "2026-12-18").
     *
     * @return Deadline<DateTimeImmutable>
     */
    public function appealFromResponse(LocalDate|string $mailedOn): Deadline
    {
        return $this->appealRule()->applyOnDate($mailedOn, $this->calendar, $this->timezone);
    }
}
