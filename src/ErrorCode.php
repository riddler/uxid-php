<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * The name of a generation refusal: a closed set of names, not messages,
 * from the option that caused it. Each value is the name the wire format
 * and its conformance corpus give the refusal.
 */
enum ErrorCode: string
{
    /** A compact time whose first character would be the deterministic marker. */
    case CompactTimeOutOfRange = 'compact_time_out_of_range';

    /** Fixed random bytes whose count is not the request's random field width. */
    case RandWidthMismatch = 'rand_width_mismatch';

    /** An explicit random field width above 10 bytes. */
    case RandSizeTooLarge = 'rand_size_too_large';

    /** A deterministic body asked for with monotonic generation. */
    case FromWithMonotonic = 'from_with_monotonic';

    /** Fixed random bytes asked for with a deterministic body. */
    case RandWithFrom = 'rand_with_from';

    /** Fixed random bytes asked for with monotonic generation. */
    case RandWithMonotonic = 'rand_with_monotonic';
}
