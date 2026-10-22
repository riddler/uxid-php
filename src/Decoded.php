<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * The result of {@see Uxid::decode()} for a well-formed id: its prefix, the
 * size and time form its body length gives, whether its body is
 * deterministic, and the time it carries. The random field and the hash
 * characters are not read back.
 */
final readonly class Decoded
{
    /**
     * @param string|null $prefix        everything before the delimiter the body was split at, or null when there is no delimiter
     * @param Size        $size          the size the body length gives
     * @param bool        $compactTime   whether the body length is a compact-time one (13, 21 or 24)
     * @param bool        $deterministic whether the body starts with the deterministic marker
     * @param int|null    $time          the time in milliseconds since the Unix epoch, or null for a deterministic body
     */
    public function __construct(
        /**
         * Everything before the delimiter the body was split at, or null when
         * the string has no delimiter. A string that starts with the
         * delimiter, which no producer emits, has the empty prefix, best read
         * as no prefix.
         */
        public ?string $prefix,
        /** The size the body length gives. */
        public Size $size,
        /**
         * Whether the body length is a compact-time one (13, 21 or 24). The
         * lengths 10 and 18 are always read as standard time, so a compact
         * XSmall or compact Medium id decodes with this false and a time that
         * is not the one it was generated at.
         */
        public bool $compactTime,
        /** Whether the body starts with the deterministic marker `z` or `Z`. */
        public bool $deterministic,
        /**
         * The time in milliseconds since the Unix epoch, or null for a
         * deterministic body, which carries none. A compact time's high bits
         * come from the decoder's clock, and the time is negative when that
         * clock is in the first 2^40 ms window and the compact time is more
         * than one day ahead of it.
         */
        public ?int $time,
    ) {}
}
