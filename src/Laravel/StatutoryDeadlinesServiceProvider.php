<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Laravel;

use DateTimeZone;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use OffsetSmoker\StatutoryDeadlines\BuiltInCalendar;
use OffsetSmoker\StatutoryDeadlines\Calendar;
use OffsetSmoker\StatutoryDeadlines\Cutoff;
use OffsetSmoker\StatutoryDeadlines\DeadlineFactory;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\StatutoryDeadlines\LocalDate;

final class StatutoryDeadlinesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/statutory-deadlines.php', 'statutory-deadlines');

        $this->app->singleton(DeadlineFactory::class, static fn(Application $app): DeadlineFactory => self::factoryFrom($app->make(Repository::class)));

        $this->app->singleton(Calendar::class, static fn(Application $app): Calendar => $app->make(DeadlineFactory::class)->calendar());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/statutory-deadlines.php' => $this->app->configPath('statutory-deadlines.php'),
            ], 'statutory-deadlines-config');
        }
    }

    /**
     * Builds the factory from config, validating every value; a bad value
     * fails when the factory is first resolved, never with a wrong deadline.
     */
    public static function factoryFrom(Repository $config): DeadlineFactory
    {
        $name = $config->get('statutory-deadlines.calendar');
        $builtIn = is_string($name) ? BuiltInCalendar::tryFrom($name) : null;

        if ($builtIn === null) {
            throw InvalidConfiguration::forKey('calendar', 'one of "pennsylvania", "federal" or "pennsylvania+federal"');
        }

        $calendar = self::withClosures($builtIn->calendar(), $config->get('statutory-deadlines.closures', []));

        return new DeadlineFactory(
            $calendar,
            self::timezone($config->get('statutory-deadlines.timezone')),
            self::cutoff($config->get('statutory-deadlines.cutoff')),
            self::countReceiptDay($config->get('statutory-deadlines.count_receipt_day', false)),
        );
    }

    private static function timezone(mixed $value): DateTimeZone
    {
        if (! is_string($value) || ! in_array($value, DateTimeZone::listIdentifiers(), true)) {
            throw InvalidConfiguration::forKey('timezone', 'a timezone identifier such as "America/New_York"');
        }

        return new DateTimeZone($value);
    }

    private static function cutoff(mixed $value): ?Cutoff
    {
        if ($value === null || $value === '') {
            return null;
        }

        $invalid = InvalidConfiguration::forKey('cutoff', 'null or a 24-hour time in HH:MM form, such as "16:30"');

        if (! is_string($value)) {
            throw $invalid;
        }

        try {
            return Cutoff::parse($value);
        } catch (InvalidDeadlineArgument) {
            throw $invalid;
        }
    }

    private static function countReceiptDay(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw InvalidConfiguration::forKey('count_receipt_day', 'true or false');
        }

        return $value;
    }

    private static function withClosures(Calendar $calendar, mixed $closures): Calendar
    {
        if (! is_array($closures) || ! array_is_list($closures)) {
            throw InvalidConfiguration::forKey('closures', "a list of ['date' => 'Y-m-d', 'reason' => '...'] entries");
        }

        foreach ($closures as $index => $closure) {
            $date = is_array($closure) ? ($closure['date'] ?? null) : null;
            $reason = is_array($closure) ? ($closure['reason'] ?? null) : null;

            if (! is_string($date) || ! is_string($reason)) {
                throw InvalidConfiguration::forKey("closures.{$index}", "an array with string 'date' (Y-m-d) and 'reason' keys");
            }

            try {
                $calendar = $calendar->withClosure(LocalDate::parse($date), $reason);
            } catch (InvalidDeadlineArgument $invalid) {
                throw InvalidConfiguration::forKey("closures.{$index}", "a valid Y-m-d date and a non-empty reason ({$invalid->getMessage()})");
            }
        }

        return $calendar;
    }
}
