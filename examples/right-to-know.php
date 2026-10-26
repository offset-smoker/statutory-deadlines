<?php

declare(strict_types=1);

/*
 * A Right-to-Know request received after the office's 16:30 cutoff on a
 * Friday before a holiday weekend, with the 30-day extension added.
 *
 *     php examples/right-to-know.php
 */

use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Deadline;
use OffsetSmoker\StatutoryDeadlines\Unit;

require __DIR__ . '/../vendor/autoload.php';

$received = new DateTimeImmutable('2026-07-02 17:05', new DateTimeZone('America/New_York'));

$response = Deadline::from($received, Calendar::pennsylvania())
    ->withCutoff('16:30')
    ->businessDays(5);

$extended = $response->extendBy(30, Unit::CalendarDays);

echo 'Response due:  ', $response->dueDate()->describe(), PHP_EOL;
echo 'With extension: ', $extended->dueDate()->describe(), PHP_EOL, PHP_EOL;
echo $extended->explain(), PHP_EOL;
