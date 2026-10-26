<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OffsetSmoker\StatutoryDeadlines\DeadlineFactory;

/**
 * @method static \OffsetSmoker\StatutoryDeadlines\Deadline<\DateTimeImmutable> from(\DateTimeInterface $received)
 * @method static \OffsetSmoker\StatutoryDeadlines\Deadline<\DateTimeImmutable> apply(\OffsetSmoker\StatutoryDeadlines\Rules\DeadlineRule $rule, \DateTimeInterface $received)
 * @method static \OffsetSmoker\StatutoryDeadlines\Rules\PennsylvaniaRightToKnow rightToKnow()
 * @method static \OffsetSmoker\StatutoryDeadlines\Calendar calendar()
 * @method static \DateTimeZone timezone()
 * @method static \OffsetSmoker\StatutoryDeadlines\Cutoff|null cutoff()
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
