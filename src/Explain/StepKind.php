<?php

declare(strict_types=1);

namespace OffsetSmoker\PaBusinessDays\Explain;

enum StepKind: string
{
    /** The moment the request arrived, in the deadline's timezone. */
    case Received = 'received';

    /** Receipt moved to the next business day (after the cutoff, or on a closed day). */
    case DeemedReceived = 'deemed_received';

    /** The day of receipt, and whether it counts as day one. */
    case ReceiptDay = 'receipt_day';

    /** A day counted toward a period. */
    case Counted = 'counted';

    /** A day passed over because it is not a business day. */
    case Skipped = 'skipped';

    /** A period ended on a day that is not a business day and moved to the next one. */
    case RolledForward = 'rolled_forward';

    /** An extension began. */
    case Extended = 'extended';

    /** The final due date. */
    case Due = 'due';
}
