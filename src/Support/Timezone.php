<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Support;

use DateTimeZone;
use Exception;
use OffsetSmoker\StatutoryDeadlines\Exceptions\InvalidDeadlineArgument;

/**
 * @internal
 */
final class Timezone
{
    public static function resolve(DateTimeZone|string $timezone): DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        try {
            return new DateTimeZone($timezone);
        } catch (Exception) {
            throw new InvalidDeadlineArgument(sprintf('"%s" is not a known timezone.', $timezone));
        }
    }
}
