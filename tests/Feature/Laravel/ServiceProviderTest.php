<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\DeadlineFactory;
use OffsetSmoker\StatutoryDeadlines\Laravel\Facades\Deadlines;
use OffsetSmoker\StatutoryDeadlines\Laravel\InvalidConfiguration;
use OffsetSmoker\StatutoryDeadlines\Laravel\StatutoryDeadlinesServiceProvider;
use OffsetSmoker\StatutoryDeadlines\LocalDate;
use OffsetSmoker\StatutoryDeadlines\Period;
use OffsetSmoker\StatutoryDeadlines\Rules\DeadlineRule;
use OffsetSmoker\StatutoryDeadlines\Unit;

/**
 * Rebuilds the factory after changing config, the way a fresh request would.
 */
function freshFactory(): DeadlineFactory
{
    app()->forgetInstance(DeadlineFactory::class);
    app()->forgetInstance(Calendar::class);
    Deadlines::clearResolvedInstances();

    return app(DeadlineFactory::class);
}

it('merges the default config', function (): void {
    expect(config('statutory-deadlines.calendar'))->toBe('pennsylvania')
        ->and(config('statutory-deadlines.timezone'))->toBe('America/New_York')
        ->and(config('statutory-deadlines.cutoff'))->toBeNull()
        ->and(config('statutory-deadlines.count_receipt_day'))->toBeFalse()
        ->and(config('statutory-deadlines.closures'))->toBe([]);
});

it('resolves one factory and its calendar from the container', function (): void {
    $factory = app(DeadlineFactory::class);

    expect(app(DeadlineFactory::class))->toBe($factory)
        ->and(app(Calendar::class)->name)->toBe('Pennsylvania')
        ->and($factory->timezone()->getName())->toBe('America/New_York')
        ->and($factory->cutoff())->toBeNull()
        ->and($factory->countsReceiptDay())->toBeFalse();
});

it('starts deadlines through the facade with the configured settings', function (): void {
    config()->set('statutory-deadlines.cutoff', '16:30');
    freshFactory();

    // 21:00 UTC is 17:00 in New York, after the cutoff.
    $deadline = Deadlines::from(new DateTimeImmutable('2026-10-29 21:00', new DateTimeZone('UTC')))->businessDays(5);

    expect($deadline->deemedReceived()->toString())->toBe('2026-10-30')
        ->and($deadline->dueDate()->toString())->toBe('2026-11-09')
        ->and($deadline->timezone()->getName())->toBe('America/New_York');
});

it('applies configured closures', function (): void {
    config()->set('statutory-deadlines.closures', [['date' => '2026-11-27', 'reason' => 'Office closed']]);

    $response = freshFactory()->rightToKnow()->response(new DateTimeImmutable('2026-11-20 12:00', new DateTimeZone('America/New_York')));

    expect($response->dueDate()->toString())->toBe('2026-12-01')
        ->and(app(Calendar::class)->isBusinessDay(LocalDate::parse('2026-11-27')))->toBeFalse();
});

it('builds the federal and combined calendars and counts the receipt day when configured', function (): void {
    config()->set('statutory-deadlines.calendar', 'pennsylvania+federal');
    config()->set('statutory-deadlines.count_receipt_day', true);
    $factory = freshFactory();

    expect($factory->calendar()->name)->toBe('Pennsylvania and United States federal')
        ->and($factory->from(new DateTimeImmutable('2026-10-26 10:00', new DateTimeZone('America/New_York')))->businessDays(5)->dueDate()->toString())->toBe('2026-10-30');
});

it('applies a rule with the configured calendar and timezone', function (): void {
    $rule = new DeadlineRule('Example', 'Example Code § 1', new Period(3, Unit::BusinessDays));

    expect(Deadlines::apply($rule, new DateTimeImmutable('2026-12-23 10:00', new DateTimeZone('America/New_York')))->dueDate()->toString())->toBe('2026-12-29');
});

it('refuses invalid config values when the factory is resolved', function (string $key, mixed $value, string $message): void {
    config()->set("statutory-deadlines.{$key}", $value);

    expect(fn(): DeadlineFactory => freshFactory())->toThrow(InvalidConfiguration::class, $message);
})->with([
    'unknown calendar' => ['calendar', 'ohio', 'statutory-deadlines.calendar must be one of'],
    'calendar not a string' => ['calendar', 1, 'statutory-deadlines.calendar'],
    'unknown timezone' => ['timezone', 'Eastern', 'statutory-deadlines.timezone'],
    'timezone not a string' => ['timezone', null, 'statutory-deadlines.timezone'],
    'cutoff with seconds' => ['cutoff', '16:30:00', 'statutory-deadlines.cutoff'],
    'cutoff not a string' => ['cutoff', 1630, 'statutory-deadlines.cutoff'],
    'receipt flag not a bool' => ['count_receipt_day', 'yes', 'statutory-deadlines.count_receipt_day'],
    'closures not a list' => ['closures', ['date' => '2026-12-24'], 'statutory-deadlines.closures'],
    'closure without a reason' => ['closures', [['date' => '2026-12-24']], 'statutory-deadlines.closures.0'],
    'closure with a bad date' => ['closures', [['date' => '12/24/2026', 'reason' => 'Closed']], 'statutory-deadlines.closures.0'],
    'closure with an empty reason' => ['closures', [['date' => '2026-12-24', 'reason' => '']], 'statutory-deadlines.closures.0'],
]);

it('treats an empty cutoff as none', function (): void {
    config()->set('statutory-deadlines.cutoff', '');

    expect(freshFactory()->cutoff())->toBeNull();
});

it('offers the config file for publishing', function (): void {
    $paths = ServiceProvider::pathsToPublish(StatutoryDeadlinesServiceProvider::class, 'statutory-deadlines-config');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/statutory-deadlines.php')
        ->and(array_values($paths)[0])->toBe(config_path('statutory-deadlines.php'));
});
