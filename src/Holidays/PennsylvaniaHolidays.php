<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Holidays;

use OffsetSmoker\PaBusinessDays\Weekday;

/**
 * The legal holidays of the Commonwealth of Pennsylvania.
 *
 * Source: the Legal Holiday Law, Act of May 31, 1893, P.L. 188, No. 138,
 * § 1 (commonly cited as 44 P.S. § 11), as amended Dec. 13, 1985,
 * P.L. 329, No. 88. Its text, from the Pennsylvania General Assembly
 * (https://www.palegis.us/statutes/unconsolidated/law-information/view-statute?txtType=PDF&SessYr=1893&ActNum=0138.&SessInd=0),
 * names: "the first day of January, commonly called New Year's Day, the
 * third Monday of January, known as Dr. Martin Luther King, Jr. Day, the
 * third Monday of February, known as Presidents' Day, Good Friday, the last
 * Monday in May, known as Memorial Day, the fourteenth day of June, known as
 * Flag Day, the fourth of July, called Independence Day, the first Monday of
 * September, known as Labor Day, the second Monday in October, known as
 * Columbus Day, the first Tuesday after the first Monday of November,
 * Election Day, the eleventh day of November, known as Veterans' Day, the
 * fourth Thursday in November, known as Thanksgiving Day, the twenty-fifth
 * day of December, known as Christmas Day".
 *
 * Weekend rule, § 2(a) of the same act: "Whenever the first day of January,
 * the twelfth day of February, the fourteenth day of June, the fourth day of
 * July, the eleventh day of November or the twenty-fifth day of December,
 * shall any of them occur on Sunday, the following day (Monday) shall be
 * deemed and declared a public holiday." The act moves no holiday off a
 * Saturday. February 12 appears in § 2 but is no longer among the § 1
 * holidays, so it is not a holiday here.
 *
 * Not included, because the statute does not make them legal holidays:
 * Juneteenth (2019 Act 9 designates June 19 "Juneteenth National Freedom
 * Day" and § 3 says it does not require anyone to treat it as a legal
 * holiday), the day after Thanksgiving, and days the Governor or the
 * President appoint (§ 1 also covers those; add them as closures). Saturday
 * afternoons, which § 1 makes half holidays, are already weekend days.
 *
 * The rules describe the act as amended in 1985 and are applied from 1986.
 */
final class PennsylvaniaHolidays
{
    public const int EARLIEST_YEAR = 1986;

    private const string LIST = 'Act of May 31, 1893, P.L. 188, No. 138, § 1 (44 P.S. § 11)';

    private const string OBSERVED = 'Act of May 31, 1893, P.L. 188, No. 138, § 2(a) (Sunday to Monday)';

    /**
     * @return list<HolidayRule>
     */
    public static function rules(): array
    {
        $fixed = static fn(string $name, int $month, int $day): FixedDateHoliday => new FixedDateHoliday(
            name: $name,
            month: $month,
            day: $day,
            citation: self::LIST,
            shift: WeekendShift::SundayToMonday,
            shiftCitation: self::OBSERVED,
        );

        return [
            $fixed("New Year's Day", 1, 1),
            new NthWeekdayHoliday('Dr. Martin Luther King, Jr. Day', 1, Weekday::Monday, 3, self::LIST),
            new NthWeekdayHoliday("Presidents' Day", 2, Weekday::Monday, 3, self::LIST),
            new EasterHoliday('Good Friday', -2, self::LIST),
            new NthWeekdayHoliday('Memorial Day', 5, Weekday::Monday, NthWeekdayHoliday::LAST, self::LIST),
            $fixed('Flag Day', 6, 14),
            $fixed('Independence Day', 7, 4),
            new NthWeekdayHoliday('Labor Day', 9, Weekday::Monday, 1, self::LIST),
            new NthWeekdayHoliday('Columbus Day', 10, Weekday::Monday, 2, self::LIST),
            new NthWeekdayHoliday('Election Day', 11, Weekday::Monday, 1, self::LIST, offsetDays: 1),
            $fixed("Veterans' Day", 11, 11),
            new NthWeekdayHoliday('Thanksgiving Day', 11, Weekday::Thursday, 4, self::LIST),
            $fixed('Christmas Day', 12, 25),
        ];
    }
}
