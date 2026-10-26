<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Laravel;

use DateTimeZone;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use OffsetSmoker\PaBusinessDays\BuiltInCalendar;
use OffsetSmoker\PaBusinessDays\Calendar;
use OffsetSmoker\PaBusinessDays\Cutoff;
use OffsetSmoker\PaBusinessDays\DeadlineFactory;
use OffsetSmoker\PaBusinessDays\Exceptions\InvalidDeadlineArgument;
use OffsetSmoker\PaBusinessDays\LocalDate;

final class PaBusinessDaysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/pa-business-days.php', 'pa-business-days');

        $this->app->singleton(DeadlineFactory::class, static fn(Application $app): DeadlineFactory => self::factoryFrom($app->make(Repository::class)));

        $this->app->singleton(Calendar::class, static fn(Application $app): Calendar => $app->make(DeadlineFactory::class)->calendar());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/pa-business-days.php' => $this->app->configPath('pa-business-days.php'),
            ], 'pa-business-days-config');
        }
    }

    /**
     * Builds the factory from config, validating every value; a bad value
     * fails when the factory is first resolved, never with a wrong deadline.
     */
    public static function factoryFrom(Repository $config): DeadlineFactory
    {
        $name = $config->get('pa-business-days.calendar');
        $builtIn = is_string($name) ? BuiltInCalendar::tryFrom($name) : null;

        if ($builtIn === null) {
            throw InvalidConfiguration::forKey('calendar', 'one of "pennsylvania", "federal" or "pennsylvania+federal"');
        }

        $calendar = self::withClosures($builtIn->calendar(), $config->get('pa-business-days.closures', []));

        return new DeadlineFactory(
            $calendar,
            self::timezone($config->get('pa-business-days.timezone')),
            self::cutoff($config->get('pa-business-days.cutoff')),
            self::countReceiptDay($config->get('pa-business-days.count_receipt_day', false)),
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
