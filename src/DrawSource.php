<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * Where a {@see Monotonic} generator takes its random values from: a fresh
 * random field, the seed, and the step that moves the field on within a
 * millisecond.
 *
 * A generator built without one draws both from each call's byte source, as
 * {@see Monotonic} describes. A draw source passed to the constructor
 * replaces those draws, and the byte source is then never called. It exists
 * to replay known values, as a test or a conformance case does, and is never
 * a source of randomness for real ids: a monotonic id is exactly as hard to
 * guess as its draws.
 */
interface DrawSource
{
    /**
     * Returns a fresh random value for a random field of `$width` bytes: a
     * string of exactly `$width` bytes, read as one big-endian unsigned
     * integer.
     *
     * @param int $width the random field's width in bytes, from 1 to 10
     *
     * @return string exactly `$width` bytes
     */
    public function seed(int $width): string;

    /**
     * Returns a step drawn uniformly from 1 to 2^(4 x `$width`) inclusive,
     * half the random field's bit width.
     *
     * @param int $width the random field's width in bytes, from 1 to 10
     *
     * @return int the step, from 1 to 2^(4 x `$width`)
     */
    public function step(int $width): int;
}
