<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Holidays;

/**
 * A rule that generates a holiday (or a closure) for any year.
 *
 * Implement this to add a holiday the built-in rules cannot express; the
 * built-in calendars are made only of the classes in this namespace.
 */
interface HolidayRule
{
    /**
     * The holiday's name, for example "Independence Day".
     */
    public function name(): string;

    /**
     * Where the rule comes from, for example "5 U.S.C. § 6103(a)".
     */
    public function citation(): string;

    /**
     * The occurrences of this holiday whose own date falls in $year (an empty
     * list when the rule did not apply that year). An occurrence's observed
     * date may fall in a neighbouring year: New Year's Day 2022 fell on a
     * Saturday and was observed by federal employees on Friday 31 December 2021.
     *
     * @return list<Observance>
     */
    public function occurrencesIn(int $year): array;
}
