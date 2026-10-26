<?php

declare(strict_types=1);

/*
 * Pennsylvania legal holidays, worked out from the text of the Legal Holiday Law
 * (Act of May 31, 1893, P.L. 188, No. 138, §§ 1 and 2(a)) by a separate script, not
 * by this library: fixed dates move from Sunday to Monday and never off a Saturday;
 * Election Day is the first Tuesday after the first Monday of November; Good Friday
 * is two days before Gregorian Easter (2024-03-31, 2025-04-20, 2026-04-05, 2027-03-28,
 * 2028-04-16, 2029-04-01, 2030-04-21; the tests check these against PHP's calendar
 * extension when it is loaded).
 *
 * Keyed by year, then holiday name; each date is the day observed.
 */

return [
    2024 => [
        'New Year\'s Day' => '2024-01-01',
        'Dr. Martin Luther King, Jr. Day' => '2024-01-15',
        'Presidents\' Day' => '2024-02-19',
        'Good Friday' => '2024-03-29',
        'Memorial Day' => '2024-05-27',
        'Flag Day' => '2024-06-14',
        'Independence Day' => '2024-07-04',
        'Labor Day' => '2024-09-02',
        'Columbus Day' => '2024-10-14',
        'Election Day' => '2024-11-05',
        'Veterans\' Day' => '2024-11-11',
        'Thanksgiving Day' => '2024-11-28',
        'Christmas Day' => '2024-12-25',
    ],
    2025 => [
        'New Year\'s Day' => '2025-01-01',
        'Dr. Martin Luther King, Jr. Day' => '2025-01-20',
        'Presidents\' Day' => '2025-02-17',
        'Good Friday' => '2025-04-18',
        'Memorial Day' => '2025-05-26',
        'Flag Day' => '2025-06-14',
        'Independence Day' => '2025-07-04',
        'Labor Day' => '2025-09-01',
        'Columbus Day' => '2025-10-13',
        'Election Day' => '2025-11-04',
        'Veterans\' Day' => '2025-11-11',
        'Thanksgiving Day' => '2025-11-27',
        'Christmas Day' => '2025-12-25',
    ],
    2026 => [
        'New Year\'s Day' => '2026-01-01',
        'Dr. Martin Luther King, Jr. Day' => '2026-01-19',
        'Presidents\' Day' => '2026-02-16',
        'Good Friday' => '2026-04-03',
        'Memorial Day' => '2026-05-25',
        'Flag Day' => '2026-06-15',
        'Independence Day' => '2026-07-04',
        'Labor Day' => '2026-09-07',
        'Columbus Day' => '2026-10-12',
        'Election Day' => '2026-11-03',
        'Veterans\' Day' => '2026-11-11',
        'Thanksgiving Day' => '2026-11-26',
        'Christmas Day' => '2026-12-25',
    ],
    2027 => [
        'New Year\'s Day' => '2027-01-01',
        'Dr. Martin Luther King, Jr. Day' => '2027-01-18',
        'Presidents\' Day' => '2027-02-15',
        'Good Friday' => '2027-03-26',
        'Memorial Day' => '2027-05-31',
        'Flag Day' => '2027-06-14',
        'Independence Day' => '2027-07-05',
        'Labor Day' => '2027-09-06',
        'Columbus Day' => '2027-10-11',
        'Election Day' => '2027-11-02',
        'Veterans\' Day' => '2027-11-11',
        'Thanksgiving Day' => '2027-11-25',
        'Christmas Day' => '2027-12-25',
    ],
    2028 => [
        'New Year\'s Day' => '2028-01-01',
        'Dr. Martin Luther King, Jr. Day' => '2028-01-17',
        'Presidents\' Day' => '2028-02-21',
        'Good Friday' => '2028-04-14',
        'Memorial Day' => '2028-05-29',
        'Flag Day' => '2028-06-14',
        'Independence Day' => '2028-07-04',
        'Labor Day' => '2028-09-04',
        'Columbus Day' => '2028-10-09',
        'Election Day' => '2028-11-07',
        'Veterans\' Day' => '2028-11-11',
        'Thanksgiving Day' => '2028-11-23',
        'Christmas Day' => '2028-12-25',
    ],
    2029 => [
        'New Year\'s Day' => '2029-01-01',
        'Dr. Martin Luther King, Jr. Day' => '2029-01-15',
        'Presidents\' Day' => '2029-02-19',
        'Good Friday' => '2029-03-30',
        'Memorial Day' => '2029-05-28',
        'Flag Day' => '2029-06-14',
        'Independence Day' => '2029-07-04',
        'Labor Day' => '2029-09-03',
        'Columbus Day' => '2029-10-08',
        'Election Day' => '2029-11-06',
        'Veterans\' Day' => '2029-11-12',
        'Thanksgiving Day' => '2029-11-22',
        'Christmas Day' => '2029-12-25',
    ],
    2030 => [
        'New Year\'s Day' => '2030-01-01',
        'Dr. Martin Luther King, Jr. Day' => '2030-01-21',
        'Presidents\' Day' => '2030-02-18',
        'Good Friday' => '2030-04-19',
        'Memorial Day' => '2030-05-27',
        'Flag Day' => '2030-06-14',
        'Independence Day' => '2030-07-04',
        'Labor Day' => '2030-09-02',
        'Columbus Day' => '2030-10-14',
        'Election Day' => '2030-11-05',
        'Veterans\' Day' => '2030-11-11',
        'Thanksgiving Day' => '2030-11-28',
        'Christmas Day' => '2030-12-25',
    ],
];
