<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * The result of {@see Uxid::decode()} for a string that is not a
 * well-formed id: the reason it was refused.
 */
final readonly class Invalid
{
    /**
     * @param DecodeError $reason why the string was refused
     */
    public function __construct(
        /** Why the string was refused, from the closed set in {@see DecodeError}. */
        public DecodeError $reason,
    ) {}
}
