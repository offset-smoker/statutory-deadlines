<?php

declare(strict_types=1);

namespace OffsetSmoker\StatutoryDeadlines\Support;

/**
 * Remembers values that are pure functions of their key, so an immutable
 * object can compute each one once. The object holding it stays immutable
 * to its callers: the same key always gives the same value.
 *
 * @internal
 *
 * @template TKey of array-key
 * @template TValue
 */
final class Memo
{
    /** @var array<TKey, TValue> */
    private array $values = [];

    /**
     * @param  TKey  $key
     * @param  callable(): TValue  $compute
     * @return TValue
     */
    public function remember(int|string $key, callable $compute): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = $compute();
        }

        return $this->values[$key];
    }
}
