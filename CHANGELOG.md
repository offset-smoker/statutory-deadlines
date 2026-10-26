# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-10-26

### Added

- `Deadline`: business-day and calendar-day periods over immutable dates,
  counted on calendar dates in the office's timezone so daylight-saving
  changes cannot move a result. Returns the date class it was given
  (`CarbonImmutable` stays `CarbonImmutable`).
- Receipt cutoffs (receipt at or after the cutoff, or on a closed day, counts
  as the next business day) and a choice of whether the day of receipt counts.
- Stacked extensions, with optional roll-forward of a calendar-day period
  whose last day is not a business day. A deadline counts at most 3,660
  days, period and extensions together.
- `Explanation`: every step, including each skipped day with its weekend day,
  holiday and observance rule, as text or as an array.
- Holiday calendars computed by rule: US federal (5 U.S.C. § 6103, with the
  Saturday-to-Friday and Sunday-to-Monday observance rules) and Pennsylvania
  (Legal Holiday Law, 44 P.S. § 11, Sunday-to-Monday only), plus closures,
  custom rules, other weekends and merged calendars.
- `DeadlineRule` for encoding a statute's clock with its citation, and
  `PennsylvaniaRightToKnow` with the response (65 P.S. § 67.901), extension
  limit (§ 67.902(b)(2)) and appeal (§ 67.1101(a)(1)) clocks.
- Optional Laravel service provider, `Deadlines` facade and config file.
