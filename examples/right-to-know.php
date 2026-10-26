<?php

declare(strict_types=1);

/*
 * The README's usage example, run end to end in CI:
 *
 *     php examples/right-to-know.php
 */

use OffsetSmoker\StatutoryDeadlines\Rules\PennsylvaniaRightToKnow;

require __DIR__ . '/../vendor/autoload.php';

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
