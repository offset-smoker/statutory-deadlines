<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays;

use DateTimeInterface;
use OffsetSmoker\PaBusinessDays\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\PaBusinessDays\Exceptions\UnsupportedYear;
use OffsetSmoker\PaBusinessDays\Holidays\Closure;
use OffsetSmoker\PaBusinessDays\Holidays\FederalHolidays;
use OffsetSmoker\PaBusinessDays\Holidays\HolidayRule;
use OffsetSmoker\PaBusinessDays\Holidays\Observance;
use OffsetSmoker\PaBusinessDays\Holidays\PennsylvaniaHolidays;

/**
 * Which days are business days: every day that is not a weekend day and on
 * which no holiday or closure is observed.
 *
 * Immutable: every `with...()` method returns a new calendar.
 */
final readonly class Calendar
{
    /** @var list<HolidayRule> */
    private array $rules;

    /** @var list<Weekday> */
    private array $weekend;

    /**
     * @param  list<HolidayRule>  $rules
     * @param  list<Weekday>  $weekend
     */
    public function __construct(
        public string $name,
        array $rules,
        array $weekend = [Weekday::Saturday, Weekday::Sunday],
        public int $earliestYear = 1,
    ) {
        if (trim($name) === '') {
            throw new InvalidDeadlineArgument('A calendar needs a name.');
        }

        $unique = array_values(array_unique(array_map(static fn(Weekday $day): int => $day->value, $weekend)));

        if (count($unique) !== count($weekend) || count($weekend) > 6) {
            throw new InvalidDeadlineArgument('The weekend must list each day at most once and leave at least one working day.');
        }

        if ($earliestYear < 1 || $earliestYear > 9999) {
            throw new InvalidDeadlineArgument('earliestYear must be between 1 and 9999.');
        }

        $this->rules = $rules;
        $this->weekend = $weekend;
    }

    /**
     * The legal public holidays of 5 U.S.C. § 6103(a), with the federal
     * weekend rule (Saturday to Friday, Sunday to Monday). See FederalHolidays.
     */
    public static function federal(): self
    {
        return new self('United States federal', FederalHolidays::rules(), earliestYear: FederalHolidays::EARLIEST_YEAR);
    }

    /**
     * The legal holidays of Pennsylvania's Legal Holiday Law (44 P.S. § 11),
     * with its weekend rule (Sunday to Monday only). See PennsylvaniaHolidays.
     */
    public static function pennsylvania(): self
    {
        return new self('Pennsylvania', PennsylvaniaHolidays::rules(), earliestYear: PennsylvaniaHolidays::EARLIEST_YEAR);
    }

    /**
     * Holidays of either calendar. Pennsylvania's Statutory Construction Act
     * omits from a period's last day "any day made a legal holiday by the
     * laws of this Commonwealth or of the United States" (1 Pa.C.S. § 1908);
     * this is the calendar for that reading.
     */
    public static function pennsylvaniaAndFederal(): self
    {
        return self::pennsylvania()->merge(self::federal(), 'Pennsylvania and United States federal');
    }

    public function withRule(HolidayRule $rule): self
    {
        return new self($this->name, [...$this->rules, $rule], $this->weekend, $this->earliestYear);
    }

    /**
     * Marks one day closed, for example a day an agency closed by proclamation.
     */
    public function withClosure(DateTimeInterface|LocalDate|string $date, string $reason): self
    {
        $day = match (true) {
            $date instanceof LocalDate => $date,
            $date instanceof DateTimeInterface => LocalDate::of($date),
            default => LocalDate::parse($date),
        };

        return $this->withRule(new Closure($day, $reason));
    }

    /**
     * @param  list<Weekday>  $weekend
     */
    public function withWeekend(array $weekend): self
    {
        return new self($this->name, $this->rules, $weekend, $this->earliestYear);
    }

    /**
     * A calendar closed on the holidays of both. It keeps this calendar's
     * weekend and the later of the two earliest years.
     */
    public function merge(self $other, ?string $name = null): self
    {
        return new self(
            $name ?? "{$this->name} and {$other->name}",
            [...$this->rules, ...$other->rules],
            $this->weekend,
            max($this->earliestYear, $other->earliestYear),
        );
    }

    /**
     * @return list<HolidayRule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * @return list<Weekday>
     */
    public function weekend(): array
    {
        return $this->weekend;
    }

    public function isWeekend(LocalDate $date): bool
    {
        return in_array($date->weekday(), $this->weekend, true);
    }

    public function isBusinessDay(DateTimeInterface|LocalDate $date): bool
    {
        $day = $date instanceof LocalDate ? $date : LocalDate::of($date);

        return ! $this->isWeekend($day) && $this->observedOn($day) === [];
    }

    public function nextBusinessDay(LocalDate $after): LocalDate
    {
        $day = $after->addDays(1);

        while (! $this->isBusinessDay($day)) {
            $day = $day->addDays(1);
        }

        return $day;
    }

    /**
     * Holidays and closures observed on this date.
     *
     * @return list<Observance>
     */
    public function observedOn(LocalDate $date): array
    {
        $this->guardYear($date->year);

        // An observed date can sit in the year before its holiday (a Saturday
        // January 1 observed on December 31) or, in principle, the year after.
        return array_values(array_filter(
            $this->occurrencesBetween($date->year - 1, $date->year + 1),
            static fn(Observance $observance): bool => $observance->observed->equals($date),
        ));
    }

    /**
     * Holidays whose own date is this date, wherever they are observed. On a
     * Saturday July 4 this is Independence Day, observed elsewhere.
     *
     * @return list<Observance>
     */
    public function holidaysFallingOn(LocalDate $date): array
    {
        $this->guardYear($date->year);

        return array_values(array_filter(
            $this->occurrencesBetween($date->year, $date->year),
            static fn(Observance $observance): bool => $observance->date->equals($date),
        ));
    }

    /**
     * Every holiday and closure observed in $year, in date order. A holiday
     * observed in the previous December (New Year's Day 2022, observed
     * federally on Friday 31 December 2021) is listed under the year it is
     * observed.
     *
     * @return list<Observance>
     */
    public function observedIn(int $year): array
    {
        $this->guardYear($year);

        $observed = array_values(array_filter(
            $this->occurrencesBetween($year - 1, $year + 1),
            static fn(Observance $observance): bool => $observance->observed->year === $year,
        ));

        usort($observed, static function (Observance $a, Observance $b): int {
            $byDate = $a->observed->compare($b->observed);

            return $byDate !== 0 ? $byDate : strcmp($a->name, $b->name);
        });

        return $observed;
    }

    /**
     * @return list<Observance>
     */
    private function occurrencesBetween(int $fromYear, int $toYear): array
    {
        $occurrences = [];

        for ($year = max(1, $fromYear); $year <= min(9999, $toYear); $year++) {
            foreach ($this->rules as $rule) {
                foreach ($rule->occurrencesIn($year) as $observance) {
                    $occurrences[] = $observance;
                }
            }
        }

        return $occurrences;
    }

    private function guardYear(int $year): void
    {
        if ($year < $this->earliestYear) {
            throw UnsupportedYear::before($year, $this->earliestYear, $this->name);
        }
    }
}
