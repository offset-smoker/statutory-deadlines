<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Tests\Laravel;

use OffsetSmoker\StatutoryDeadlines\Laravel\StatutoryDeadlinesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [StatutoryDeadlinesServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Deadlines' => \OffsetSmoker\StatutoryDeadlines\Laravel\Facades\Deadlines::class];
    }
}
