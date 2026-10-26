<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Laravel;

use InvalidArgumentException;

/**
 * The `pa-business-days` config holds a value the package cannot use. The
 * message names the key; it never echoes anything but the offending value
 * of that key.
 */
final class InvalidConfiguration extends InvalidArgumentException
{
    public static function forKey(string $key, string $expected): self
    {
        return new self(sprintf('Config value pa-business-days.%s must be %s.', $key, $expected));
    }
}
