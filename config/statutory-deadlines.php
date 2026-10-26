<?php

declare(strict_types=1);

return [

    /*
     * Which built-in holiday calendar to use: "pennsylvania" (44 P.S. § 11),
     * "federal" (5 U.S.C. § 6103), or "pennsylvania+federal" (the holidays of
     * both, the reading of 1 Pa.C.S. § 1908).
     */
    'calendar' => env('STATUTORY_DEADLINES_CALENDAR', 'pennsylvania'),

    /*
     * The office's timezone. Receipt times are converted to it before the
     * day of receipt and the cutoff are decided.
     */
    'timezone' => env('STATUTORY_DEADLINES_TIMEZONE', 'America/New_York'),

    /*
     * A 24-hour HH:MM time, or null for no cutoff. With a cutoff, a request
     * received at or after it, or on a day that is not a business day, is
     * deemed received on the next business day.
     */
    'cutoff' => env('STATUTORY_DEADLINES_CUTOFF'),

    /*
     * Whether the day of receipt counts as day one. Statutory periods in
     * Pennsylvania exclude the first day (1 Pa.C.S. § 1908), so the default
     * is false.
     */
    'count_receipt_day' => (bool) env('STATUTORY_DEADLINES_COUNT_RECEIPT_DAY', false),

    /*
     * Extra days the office is closed, each with the reason the explanation
     * will give: [['date' => '2026-12-24', 'reason' => 'Closed by proclamation']].
     */
    'closures' => [],

];
