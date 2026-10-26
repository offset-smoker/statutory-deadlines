<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Exceptions;

use InvalidArgumentException;

/**
 * A value passed into the library is malformed or out of range: a date
 * string that is not Y-m-d, a day count outside the allowed range, an
 * unknown timezone, a cutoff that is not HH:MM.
 */
final class InvalidDeadlineArgument extends InvalidArgumentException {}
