<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Laravel;

use InvalidArgumentException;

/**
 * The `statutory-deadlines` config holds a value the package cannot use. The
 * message names the key; it never echoes anything but the offending value
 * of that key.
 */
final class InvalidConfiguration extends InvalidArgumentException
{
    public static function forKey(string $key, string $expected): self
    {
        return new self(sprintf('Config value statutory-deadlines.%s must be %s.', $key, $expected));
    }
}
