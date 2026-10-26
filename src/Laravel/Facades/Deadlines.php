<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OffsetSmoker\PaBusinessDays\DeadlineFactory;

/**
 * @method static \OffsetSmoker\PaBusinessDays\Deadline<\DateTimeImmutable> from(\DateTimeInterface $received)
 * @method static \OffsetSmoker\PaBusinessDays\Calendar calendar()
 * @method static \DateTimeZone timezone()
 * @method static \OffsetSmoker\PaBusinessDays\Cutoff|null cutoff()
 * @method static bool countsReceiptDay()
 *
 * @see DeadlineFactory
 */
final class Deadlines extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DeadlineFactory::class;
    }
}
