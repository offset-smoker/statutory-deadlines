<?php

declare(strict_types=1);

use OffsetSmoker\StatutoryDeadlines\Tests\Laravel\TestCase;

/*
 * Only the Laravel bridge tests boot a framework; everything else runs as
 * plain PHP, the way most of the library is meant to be used.
 */
pest()->extend(TestCase::class)->in('Feature/Laravel');

/**
 * @return array<int, array<string, string>>
 */
function fixture(string $name): array
{
    /** @var array<int, array<string, string>> */
    return require __DIR__ . "/Fixtures/{$name}.php";
}

function ny(string $time): DateTimeImmutable
{
    return new DateTimeImmutable($time, new DateTimeZone('America/New_York'));
}
