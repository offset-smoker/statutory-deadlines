<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Holidays;

use OffsetSmoker\StatutoryDeadlines\Weekday;

/**
 * The legal public holidays of the United States.
 *
 * Source: 5 U.S.C. § 6103(a), which lists the eleven holidays and their
 * dates, and the U.S. Office of Personnel Management's "Federal Holidays"
 * page (https://www.opm.gov/policy-data-oversight/pay-leave/federal-holidays/),
 * which states the observance rules: "If a holiday falls on a Saturday, for
 * most Federal employees, the preceding Friday will be treated as a holiday
 * for pay and leave purposes. (See 5 U.S.C. 6103(b).) If a holiday falls on a
 * Sunday, for most Federal employees, the following Monday will be treated as
 * a holiday for pay and leave purposes. (See Section 3(a) of Executive Order
 * 11582, February 11, 1971.)"
 *
 * Inauguration Day (5 U.S.C. § 6103(c)) is not included: it is a holiday only
 * for employees in the Washington, D.C. area.
 *
 * The list is complete from 1986, the first year the Birthday of Martin
 * Luther King, Jr. was observed (Pub. L. 98-144). Juneteenth National
 * Independence Day applies from 2021 (Pub. L. 117-17, enacted 17 June 2021;
 * that year's June 19 was a Saturday, so it was observed Friday 18 June).
 */
final class FederalHolidays
{
    public const int EARLIEST_YEAR = 1986;

    private const string LIST = '5 U.S.C. § 6103(a)';

    private const string OBSERVED = '5 U.S.C. § 6103(b)(1) (Saturday to Friday); Executive Order 11582, § 3(a) (Sunday to Monday)';

    /**
     * @return list<HolidayRule>
     */
    public static function rules(): array
    {
        $fixed = static fn(string $name, int $month, int $day, ?int $firstYear = null): FixedDateHoliday => new FixedDateHoliday(
            name: $name,
            month: $month,
            day: $day,
            citation: self::LIST,
            shift: WeekendShift::SaturdayToFridaySundayToMonday,
            shiftCitation: self::OBSERVED,
            firstYear: $firstYear,
        );

        return [
            $fixed("New Year's Day", 1, 1),
            new NthWeekdayHoliday('Birthday of Martin Luther King, Jr.', 1, Weekday::Monday, 3, self::LIST, firstYear: 1986),
            new NthWeekdayHoliday("Washington's Birthday", 2, Weekday::Monday, 3, self::LIST),
            new NthWeekdayHoliday('Memorial Day', 5, Weekday::Monday, NthWeekdayHoliday::LAST, self::LIST),
            $fixed('Juneteenth National Independence Day', 6, 19, 2021),
            $fixed('Independence Day', 7, 4),
            new NthWeekdayHoliday('Labor Day', 9, Weekday::Monday, 1, self::LIST),
            new NthWeekdayHoliday('Columbus Day', 10, Weekday::Monday, 2, self::LIST),
            $fixed('Veterans Day', 11, 11),
            new NthWeekdayHoliday('Thanksgiving Day', 11, Weekday::Thursday, 4, self::LIST),
            $fixed('Christmas Day', 12, 25),
        ];
    }
}
