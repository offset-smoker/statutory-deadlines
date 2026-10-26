# offset-smoker/statutory-deadlines

Deadline rules as code for PHP. A statute says "five business days from
receipt" or "30 days following"; somebody has to turn that into a date, and
the date is only as good as the holiday list, the receipt rule and the
counting convention behind it. This library makes each of those explicit: a
statute's clock is a named rule with its citation, holidays are generated
from the laws that create them (not typed in per year), and every computed
date comes with an explanation of each day it counted or skipped and why.
It ships with US federal and Pennsylvania holiday calendars and with the
response, extension and appeal clocks of Pennsylvania's Right-to-Know Law.

> **Not legal advice.** This library does arithmetic on dates under the
> assumptions it states. It cannot tell you which calendar your agency keeps,
> whether a court would read a statute the same way, or whether a deadline
> has been changed by agreement, order or later legislation. Check the
> result against the statute and your counsel before relying on it.

## Requirements

- PHP 8.3 or later (CI runs 8.3 and 8.4)
- No extensions beyond a standard PHP build, and no runtime dependencies
- Optional: Laravel 12 or 13 for the service provider and facade (Composer
  refuses older Laravel versions)
- Optional: `nesbot/carbon`, if you want `CarbonImmutable` in and out

## Installation

```bash
composer require offset-smoker/statutory-deadlines
```

## Usage

```php
use OffsetSmoker\StatutoryDeadlines\Rules\PennsylvaniaRightToKnow;

// An agency that treats requests received at or after 4:30 p.m. as received
// the next business day. The Act itself sets no cutoff.
$rtkl = new PennsylvaniaRightToKnow(cutoff: '16:30');

$received = new DateTimeImmutable('2026-07-02 17:05', new DateTimeZone('America/New_York'));

echo $rtkl->response($received)->dueDate()->describe(), PHP_EOL;
// Friday 10 July 2026

echo $rtkl->extendedResponse($received)->dueDate()->describe(), PHP_EOL;
// Monday 10 August 2026

echo $rtkl->appealFromResponse('2026-07-10')->dueDate()->describe(), PHP_EOL;
// Friday 31 July 2026

echo PHP_EOL, $rtkl->extendedResponse($received)->explain(), PHP_EOL;
```

This is [`examples/right-to-know.php`](examples/right-to-know.php). A test
checks that this block is that file, runs it, and checks each printed date
against the comment under it and the explanation against the block below.
The last line prints:

```text
Rule: Pennsylvania Right-to-Know Law, latest response date under an extension notice (65 P.S. § 67.901; 65 P.S. § 67.902(b)(2)).
Calendar: Pennsylvania. Timezone: America/New_York.
2026-07-02  received        Received Thursday 2 July 2026 at 17:05:00 (America/New_York).
2026-07-03  deemed_received Received at or after the 16:30 cutoff, so it is deemed received on the next business day, Friday 3 July 2026.
2026-07-03  receipt_day     The day of receipt is not counted; counting starts the next day.
2026-07-04  skipped         Skipped Saturday 4 July 2026: Saturday (weekend); Independence Day.
2026-07-05  skipped         Skipped Sunday 5 July 2026: Sunday (weekend).
2026-07-06  counted         Business day 1 of 5.
2026-07-07  counted         Business day 2 of 5.
2026-07-08  counted         Business day 3 of 5.
2026-07-09  counted         Business day 4 of 5.
2026-07-10  counted         Business day 5 of 5.
2026-07-10  extended        Extended by 30 calendar days (65 P.S. § 67.902(b)(2)), counted from the day after Friday 10 July 2026.
2026-08-09  counted         30 calendar days counted: Saturday 11 July 2026 through Sunday 9 August 2026.
2026-08-09  skipped         Skipped Sunday 9 August 2026: Sunday (weekend).
2026-08-10  rolled_forward  Day 30 is not a business day, so the period ends on the next business day, Monday 10 August 2026.
2026-08-10  due             Due Monday 10 August 2026.
```

`explain()->toArray()` gives the same steps as data (each skipped day carries
the holiday's name, its date, its observed date and both citations), ready
for JSON or for storing beside the record the deadline belongs to.

### Building a deadline directly

```php
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Deadline;
use OffsetSmoker\StatutoryDeadlines\Unit;

$received = new DateTimeImmutable('2026-07-02 17:05', new DateTimeZone('America/New_York'));
$now = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));

$deadline = Deadline::from($received, Calendar::pennsylvania(), 'America/New_York')
    ->withCutoff('16:30')          // optional
    ->countReceiptDay(false)       // the default: day of receipt is excluded
    ->businessDays(5)
    ->extendBy(30, Unit::CalendarDays);

$deadline->due();                  // start of the due day, same class you passed in
$deadline->dueDate();              // LocalDate; (string) gives "2026-08-10"
$deadline->isMissedAt($now);       // true from the day after the due date
$deadline->explain()->skipped();   // the days passed over, each with its reason
```

Every method returns a new object; the date you pass in is never modified. A
`CarbonImmutable` comes back as a `CarbonImmutable`, a `DateTimeImmutable` as
a `DateTimeImmutable`, and a mutable `DateTime` or `Carbon` as a
`DateTimeImmutable`. Counting happens on calendar dates in the deadline's
timezone, so a 23- or 25-hour day at a daylight-saving change cannot move a
result. For a clock that runs from a date rather than a moment (a mailing
date), use `Deadline::fromDate('2026-07-10', $calendar, 'America/New_York')`:
a bare `new DateTimeImmutable('2026-07-10')` is midnight UTC, which is the
evening before in New York.

### How receipt is handled

- **Day of receipt.** Not counted by default, following Pennsylvania's
  Statutory Construction Act: a statutory period is computed "so as to
  exclude the first and include the last day" (1 Pa.C.S. § 1908).
  `countReceiptDay()` counts it as day 1 when it is a business day.
- **Cutoff.** With `withCutoff('16:30')`, a receipt at or after 16:30, or on
  a day that is not a business day, is deemed received on the next business
  day. The explanation records the check either way. There is no cutoff
  unless you set one.
- **Weekend and holiday receipt.** Setting a cutoff, at any time of day,
  also switches on the closed-day rule above, and that moves the result.
  A request received on Saturday 24 October 2026 with five business days
  and no cutoff is due Friday 30 October: Saturday is the excluded day of
  receipt and Monday is day 1. With any cutoff it is deemed received on
  Monday, Monday is excluded, and it is due Monday 2 November. The two
  cannot be set separately; the explanation's `deemed_received` step says
  when it happened.
- **Last day.** A calendar-day period whose last day is a weekend day or
  holiday ends on the next business day (the last-day rule of 1 Pa.C.S.
  § 1908). Pass `rollForward: false` to keep the day as it falls. A
  business-day period always ends on a business day.

## The Pennsylvania Right-to-Know Law rule

`PennsylvaniaRightToKnow` encodes three clocks from the Right-to-Know Law,
Act of Feb. 14, 2008, P.L. 6, No. 3, 65 P.S. § 67.101 et seq., checked
against the text the
[Pennsylvania General Assembly publishes](https://www.palegis.us/statutes/unconsolidated/law-information/view-statute?txtType=PDF&SessYr=2008&ActNum=0003.&SessInd=0)
(sections 901, 902 and 1101 carry no amendments there):

| Method | Statute | What it computes |
|---|---|---|
| `response($received)` | § 901, 65 P.S. § 67.901: "The time for response shall not exceed five business days from the date the written request is received by the open-records officer" | Five business days, day of receipt excluded. Also the deadline for an extension notice, which § 902(b)(1) requires "within five business days of receipt". |
| `extendedResponse($received)` | § 902(b)(2), 65 P.S. § 67.902(b)(2): if the expected response date "is in excess of 30 days, following the five business days allowed for in section 901, the request for access shall be deemed denied unless the requester has agreed in writing" | The latest response date an extension notice can name without the requester's written agreement: 30 calendar days after the § 901 date, moved off a weekend or holiday unless `extensionRollsForward: false`. |
| `appealFromResponse($mailedOn)` | § 1101(a)(1), 65 P.S. § 67.1101(a)(1): an appeal is filed "within 15 business days of the mailing date of the agency's response" | Fifteen business days from the mailing date. |

What the statute leaves open, and is therefore a constructor argument rather
than a built-in assumption:

- **Which days are business days.** The Act does not define "business day".
  The default calendar is Pennsylvania's statutory legal holidays. Pass
  `Calendar::pennsylvaniaAndFederal()` to skip federal holidays too, and add
  your agency's own closures with `withClosure()`.
- **After-hours receipt.** The Act sets no cutoff. Some agencies' RTKL
  policies treat a request received after the close of business as received
  the next business day; pass that agency's closing time as `cutoff`. A
  cutoff also deems weekend and holiday receipts to the next business day
  (see [How receipt is handled](#how-receipt-is-handled)).
- **The 30 days.** § 902(b)(2) says "30 days", not "business days", so they
  are counted as calendar days.
- **Not computed:** the 15-business-day appeal period after a *deemed*
  denial (also § 1101(a)(1)), because the Act does not name the day a deemed
  denial occurs.

## Holiday calendars

Both built-in calendars are generated by rule for any year from 1986 on, and
refuse earlier years (`UnsupportedYear`) rather than apply today's list to a
year when the law was different.

**United States federal** (`Calendar::federal()`): the eleven legal public
holidays of [5 U.S.C. § 6103(a)](https://www.law.cornell.edu/uscode/text/5/6103).
The observance rules are the ones the Office of Personnel Management states
on its [Federal Holidays page](https://www.opm.gov/policy-data-oversight/pay-leave/federal-holidays/):
"If a holiday falls on a Saturday, for most Federal employees, the preceding
Friday will be treated as a holiday for pay and leave purposes. (See 5 U.S.C.
6103(b).) If a holiday falls on a Sunday, for most Federal employees, the
following Monday will be treated as a holiday for pay and leave purposes.
(See Section 3(a) of Executive Order 11582, February 11, 1971.)" A Saturday
New Year's Day is therefore observed on the previous 31 December. Juneteenth
National Independence Day applies from 2021. Inauguration Day (§ 6103(c)) is
left out because it is a holiday only in the Washington, D.C. area. The
tests check the rules against every date OPM publishes for 2012 through 2030.

**Pennsylvania** (`Calendar::pennsylvania()`): the legal holidays of the
Legal Holiday Law, Act of May 31, 1893, P.L. 188, No. 138, § 1 (44 P.S.
§ 11), as amended in 1985, from the
[General Assembly's text](https://www.palegis.us/statutes/unconsolidated/law-information/view-statute?txtType=PDF&SessYr=1893&ActNum=0138.&SessInd=0):
New Year's Day, Dr. Martin Luther King, Jr. Day (third Monday of January),
Presidents' Day (third Monday of February), Good Friday, Memorial Day (last
Monday in May), Flag Day (June 14), Independence Day, Labor Day (first
Monday of September), Columbus Day (second Monday in October), Election Day
(the first Tuesday after the first Monday of November), Veterans' Day
(November 11), Thanksgiving Day (fourth Thursday in November) and Christmas
Day. Under § 2(a), a January 1, June 14, July 4, November 11 or December 25
that falls on a Sunday is observed the following Monday. **The Act moves no
holiday off a Saturday**, so Friday 3 July 2026 is a business day on this
calendar even though it is a federal holiday. Juneteenth is not included:
2019 Act 9 designates June 19 "Juneteenth National Freedom Day" and says it
does not require anyone to treat the day as a legal holiday. Days the
Governor or the President appoint are covered by § 1 but cannot be
predicted; add them as closures when they are proclaimed.

**Both** (`Calendar::pennsylvaniaAndFederal()`): closed on the holidays of
either. 1 Pa.C.S. § 1908 omits from a period's last day "any day made a
legal holiday by the laws of this Commonwealth or of the United States".

### Adding another state, a closure or a custom rule

No fork needed. A calendar is a list of `HolidayRule`s:

```php
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Holidays\EasterHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\FixedDateHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\NthWeekdayHoliday;
use OffsetSmoker\StatutoryDeadlines\Holidays\WeekendShift;
use OffsetSmoker\StatutoryDeadlines\Weekday;

$state = new Calendar('Example State', [
    new FixedDateHoliday('Statehood Day', 3, 15, 'Example Code § 1-1', WeekendShift::SundayToMonday, 'Example Code § 1-2'),
    new NthWeekdayHoliday('Day after Thanksgiving', 11, Weekday::Thursday, 4, 'Example Code § 1-1', offsetDays: 1),
    new EasterHoliday('Good Friday', -2, 'Example Code § 1-1'),
], earliestYear: 2000);

$office = $state
    ->merge(Calendar::federal())
    ->withClosure('2026-12-24', 'Closed by proclamation');
```

`FixedDateHoliday`, `NthWeekdayHoliday` (nth or last weekday of a month, with
a day offset), `EasterHoliday` (days from Gregorian Easter) and `Closure`
(one date) cover the common shapes; implement `HolidayRule` for anything
else. `withWeekend()` changes the weekend days; the `WeekendShift` rules
still assume a Saturday and Sunday weekend (a Sunday holiday moves to
Monday whatever the calendar's weekend), so pair a different weekend with
`WeekendShift::None` and explicit observed days. A statute's clock is a
`DeadlineRule`:

```php
use OffsetSmoker\StatutoryDeadlines\Period;
use OffsetSmoker\StatutoryDeadlines\Rules\DeadlineRule;
use OffsetSmoker\StatutoryDeadlines\Unit;

$rule = new DeadlineRule(
    name: 'Example Public Records Act, response',
    citation: 'Example Code § 5-101',
    period: new Period(10, Unit::BusinessDays),
    extensions: [new Period(10, Unit::BusinessDays, citation: 'Example Code § 5-102')],
);

$received = new DateTimeImmutable('2026-10-26 10:00', new DateTimeZone('America/Chicago'));

$due = $rule->apply($received, $office, 'America/Chicago')->due();
```

Run it after the calendar block above, which defines `$office`.
[`examples/custom-rule.php`](examples/custom-rule.php) runs a version of
this. "Example State" and its code are made up.

## Laravel

The service provider and the `Deadlines` facade register themselves through
package discovery. Publish the config if you want to change it:

```bash
php artisan vendor:publish --tag=statutory-deadlines-config
```

| Key | Env | Default | Meaning |
|---|---|---|---|
| `calendar` | `STATUTORY_DEADLINES_CALENDAR` | `pennsylvania` | `pennsylvania`, `federal` or `pennsylvania+federal` |
| `timezone` | `STATUTORY_DEADLINES_TIMEZONE` | `America/New_York` | The office's timezone |
| `cutoff` | `STATUTORY_DEADLINES_CUTOFF` | none | `HH:MM`, 24-hour |
| `count_receipt_day` | `STATUTORY_DEADLINES_COUNT_RECEIPT_DAY` | `false` | Whether the day of receipt is day 1; `true` or `false` only (`no`, `off` or `1` is refused) |
| `closures` | | `[]` | `[['date' => 'Y-m-d', 'reason' => '...']]` |

```php
use OffsetSmoker\StatutoryDeadlines\Laravel\Facades\Deadlines;

// received_at must be a date: cast it with 'received_at' => 'datetime' on the model.
Deadlines::from($request->received_at)->businessDays(5)->due();
Deadlines::rightToKnow()->response($request->received_at)->explain()->toArray();
Deadlines::apply($rule, $request->received_at);
```

Every config value is validated when the factory is first resolved; a bad
value throws `InvalidConfiguration` naming the key, rather than producing a
wrong date. The core never touches Laravel; an architecture test enforces it.

## Why not cmixin/business-day?

[cmixin/business-day](https://github.com/kylekatarnls/business-day) is a
Carbon mixin for general business-day math with a large set of regional
holiday lists, and it is the better choice for that. This library does
something narrower: it models statutes. A rule carries its citation, the
holiday calendars cite the laws they come from and refuse years they do not
describe, receipt cutoffs and the first-day and last-day conventions are
explicit options, and every result explains itself day by day. It has no
runtime dependency, Carbon included.

## Testing

```bash
composer install
vendor/bin/pest                      # tests
composer analyse                     # PHPStan level max: core alone, then everything with Larastan
vendor/bin/pint --test               # code style
```

The suite checks the federal rules against every holiday date OPM published
for 2012 through 2030 (`tests/Fixtures/opm-federal-holidays.php`), the
Pennsylvania rules against dates worked out separately from the statute text
for 2024 through 2030, Easter against PHP's calendar extension for 1986
through 2100, and covers observed dates, year boundaries, leap years,
daylight-saving changes, cutoffs, timezones and stacked extensions. The CI
workflow runs it on PHP 8.3 and 8.4 against Laravel 12 and 13. Before the
first push, all four combinations were run locally (PHP 8.3.36 and 8.4.27,
Laravel 12.70 and 13.38): tests and both PHPStan configurations pass on each.

## Status and limitations

Version 0.1.0. What it does not do:

- It knows the holidays the statutes name, not the days a particular office
  is closed. An office may close on days the Legal Holiday Law does not list,
  such as a Friday in place of a Saturday holiday or a day added by
  proclamation. Add those as closures; the library cannot know them.
- Federal observance follows OPM's rule for most employees with a
  Monday-to-Friday workweek. Other schedules (5 U.S.C. § 6103(b)(2) and (3)),
  agency-specific "in lieu of" days and Inauguration Day are not modelled.
- Years before 1986 are refused. Later changes to either statute need a new
  release; nothing updates the rules automatically.
- Whether the Legal Holiday Law's holidays are "business days" for a given
  statute is a legal question. Section 4 of that Act says its holidays are
  "secular or business days, for all other purposes than those mentioned in
  this act". The library lets you choose the calendar; it does not decide.
- Only the Right-to-Know Law clocks listed above are built in.
- A deadline counts at most 3,660 days, its period and every extension
  together; longer ones throw `InvalidDeadlineArgument`. That bounds the
  work a deadline built from user input can ask for.

Name: the package started as `pa-business-days` and was renamed
`statutory-deadlines` before release, because the rules and calendars are
not specific to Pennsylvania.

## How this was built

Written with Claude Code under Brandon Wagner's direction and review.

## License

MIT. See [LICENSE](LICENSE).
