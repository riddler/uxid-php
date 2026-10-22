<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * A monotonic generator: for one prefix and random field width, each id it
 * makes carries a time and random value greater than the last one's (the
 * time is at least the last time, and when it is equal the random value is
 * greater), also when several ids fall in one millisecond and when the clock
 * moves back. Among ids of one letter case, delimiter and time form, each
 * sorts after the last as a string. It follows the "Monotonic generation"
 * rule of uxid's wire-format specification. A monotonic id is an ordinary
 * time-based id of its size and length: nothing in it says how it was made,
 * and {@see Uxid::decode()} reads it as any other.
 *
 *     $ids = new Monotonic();
 *     $first = $ids->generate(prefix: 'usr', size: Size::Small);
 *     $second = $ids->generate(prefix: 'usr', size: Size::Small); // sorts after $first, also in the same millisecond
 *
 * The generator keeps its state per prefix and per random field width: for
 * each such pair it has made an id for, the last time `T` and the last random
 * value `R`, the random field read as one big-endian unsigned integer of `w`
 * bytes. For a call at time `now` (the call's `$time`, or the system clock
 * read at the call):
 *
 * 1. with no state for the pair, or `now` later than `T`, the id carries
 *    `now` and a fresh random value, the seed;
 * 2. with `now` equal to or earlier than `T`, the id carries `T` and
 *    `R + step`, where the step is drawn uniformly from 1 to 2^(4w)
 *    inclusive, half the field's bit width;
 * 3. when `R + step` is 2^(8w) or more, the id carries `T + 1` and a fresh
 *    seed instead.
 *
 * The random value alone is not monotonic: when the time moves on, by the
 * clock or by an overflow, the id carries a fresh seed, which can be smaller
 * than the last value.
 *
 * The draws come from the {@see DrawSource} the generator was built with or,
 * without one, from each call's byte source (`random_bytes` unless the call
 * gives one): a seed is `w` bytes; a step is the low `4w` bits of
 * `ceil(w / 2)` bytes, read big-endian, plus one. The range of a step holds a
 * power of two values, so the masked bits are exactly uniform over it, with
 * no modulo bias and nothing redrawn. A step is drawn before the seed that
 * replaces it on an overflow.
 *
 * The step is not a plus-one counter: consecutive ids in a millisecond are
 * spread over a window of 2^(4w) values. That stops trivial enumeration, but
 * it is a mitigation, not unpredictability, and a small random field stays
 * small: a standard Small id has 16 random bits. A compact time holds only
 * the low 40 bits of the time, so compact ids keep their order only within
 * one 2^40 ms window, as every compact id does.
 *
 * **No lock.** The state lives in this instance alone; nothing is kept in a
 * static property, two generators never affect each other, and ids from two
 * generators carry no order between them within a millisecond. The instance
 * takes no lock, because PHP's standard runtime shares no object between
 * threads: each request, worker or process has its own objects, and an
 * extension that runs PHP code on threads copies the objects it hands to a
 * thread rather than sharing them, so a lock would protect nothing. Two
 * processes, or two threads holding copies, are two generators. The state for
 * each pair is kept for the generator's lifetime, so it grows by one entry
 * for each distinct prefix and random field width the generator is given.
 *
 * **A refused call leaves the generator as it was.** When
 * {@see generate()} throws, the last time and random value of every pair stay
 * what they were before the call, and a pair that had none still has none;
 * the next call starts from them. Draws the refused call already made are not
 * given back. So after a step that overflows the field onto a compact time
 * the wire format refuses, a later call at or before `T` draws a new step: it
 * is refused again if that step also overflows the field, and with a step
 * that does not, it returns an id at `T`.
 */
final class Monotonic
{
    /** The latest time a time field holds: 2^48 - 1 milliseconds. */
    private const int MAX_TIME = 281474976710655;

    /** The widest random field, in bytes. */
    private const int MAX_RAND_BYTES = 10;

    /**
     * The last time and random field, most significant byte first, for each
     * pair of random field width and prefix, keyed `"<width>:<prefix>"`.
     *
     * @var array<string, array{int, string}>
     */
    private array $state = [];

    /**
     * Builds a generator with no state.
     *
     * @param DrawSource|null $draws the source of every seed and step, in place of each call's byte source, which is then never called; null, the default, draws them from the byte source. A draw source replays known values, as a test or a conformance case does; ids built from known draws are exactly as guessable as those draws.
     */
    public function __construct(
        private readonly ?DrawSource $draws = null,
    ) {}

    /**
     * Generates the next id for the call's prefix and random field width,
     * under the rule the class describes.
     *
     * The arguments mean what they mean to {@see Uxid::generate()}, with the
     * same defaults, but one: `$compactTime` left null is compact time at
     * XSmall and standard time at every other size, because a standard XSmall
     * has no random field to step and a compact one has a one-byte field. The
     * random field width (the size's, or `$randSize`) must be at least one
     * byte, so an XSmall with `$compactTime` false and no `$randSize` is
     * refused, and so is a `$randSize` of 0.
     *
     * `$from` and `$rand` exist only to be refused, before anything else is
     * read: a monotonic generator makes no deterministic id, and it chooses
     * the random field itself.
     *
     *     $ids->generate(prefix: 'usr', time: 1700000000000); // usr_01hf7yat00, then 16 random characters
     *     $ids->generate(prefix: 'usr', time: 1700000000000); // the same millisecond, the random field stepped on
     *
     * @param string                       $prefix      the prefix; the empty string, the default, is no prefix
     * @param Size|string                  $size        the size, or its short or long spelling; the default is XLarge
     * @param int|null                     $randSize    the random field's width in bytes, 1 to 10, in place of the width the size and time form give
     * @param bool|null                    $compactTime true for compact time, false for standard time; null, the default, is compact at XSmall and standard otherwise
     * @param LetterCase                   $case        the letter case of the body
     * @param string                       $delimiter   the string between the prefix and the body, `_` unless given; never empty
     * @param int|null                     $time        the current time in milliseconds since the Unix epoch, 0 to 2^48 - 1; null, the default, reads the system clock at the call. Giving the time is how a caller fixes the clock
     * @param string|null                  $rand        always refused with `rand_with_monotonic`
     * @param (callable(int): string)|null $byteSource  a source of random bytes, called with a byte count and returning a string of exactly that many bytes; null, the default, is `random_bytes`; not called when the generator has a draw source
     * @param string|null                  $from        always refused with `from_with_monotonic`
     *
     * @return string the id
     *
     * @throws UxidException             `from_with_monotonic` for a `$from`, then `rand_with_monotonic` for a `$rand`, both checked first; `rand_size_too_large` for a `$randSize` above 10; `compact_time_out_of_range` when the compact time the id would carry starts with the deterministic marker, also when that time is the millisecond after `T`, reached by an overflow (see {@see ErrorCode})
     * @throws \InvalidArgumentException the size string is no size's spelling, the delimiter is empty, the random field width is negative or 0, or the time (or the system clock) is outside 0 to 2^48 - 1
     * @throws \OverflowException        a step overflows the random field in millisecond 2^48 - 1, the last a time field holds
     * @throws \UnexpectedValueException the byte source returned a value that is not a string or a string of another length than it was asked for, or the draw source returned a seed of another length than the field's width or a step outside 1 to 2^(4w)
     * @throws \LogicException           the PHP build is not 64-bit
     */
    public function generate(
        string $prefix = '',
        Size|string $size = Size::XLarge,
        ?int $randSize = null,
        ?bool $compactTime = null,
        LetterCase $case = LetterCase::Lower,
        string $delimiter = '_',
        ?int $time = null,
        ?string $rand = null,
        ?callable $byteSource = null,
        ?string $from = null,
    ): string {
        if ($from !== null) {
            throw new UxidException(
                ErrorCode::FromWithMonotonic,
                'A monotonic generator makes no deterministic id (from): it has no random field to step.',
            );
        }

        if ($rand !== null) {
            throw new UxidException(
                ErrorCode::RandWithMonotonic,
                'A monotonic generator takes no fixed random bytes (rand): it chooses the random field.',
            );
        }

        if (\PHP_INT_SIZE < 8) {
            throw new \LogicException('uxid needs a 64-bit PHP build: the time field is 48 bits wide.');
        }

        if (\is_string($size)) {
            $size = Size::parse($size);
        }

        if ($delimiter === '') {
            throw new \InvalidArgumentException('The delimiter must be a non-empty string.');
        }

        $compact = $compactTime ?? $size === Size::XSmall;
        $width = $randSize ?? self::randBytes($size, $compact);
        if ($width < 0) {
            throw new \InvalidArgumentException('The random field width must not be negative, got ' . $width . '.');
        }

        if ($width > self::MAX_RAND_BYTES) {
            throw new UxidException(
                ErrorCode::RandSizeTooLarge,
                'The random field width must be at most 10 bytes, got ' . $width . '.',
            );
        }

        if ($width === 0) {
            throw new \InvalidArgumentException(
                'A monotonic id needs a random field to step, but this random field width is 0 bytes;'
                    . ' leave compactTime null at XSmall, or give a randSize from 1 to 10.',
            );
        }

        $now = $time ?? (int) (new \DateTimeImmutable())->format('Uv');
        if ($now < 0 || $now > self::MAX_TIME) {
            throw new \InvalidArgumentException(
                'The time must be from 0 to 2^48 - 1 milliseconds since the Unix epoch, got ' . $now . '.',
            );
        }

        $key = $width . ':' . $prefix;
        $last = $this->state[$key] ?? null;
        if ($last !== null && $now <= $last[0]) {
            [$at, $field] = $last;
            $field = self::addStep($field, $this->step($byteSource, $width));
            if ($field === null) {
                if ($at === self::MAX_TIME) {
                    throw new \OverflowException(
                        'The random field overflowed in millisecond 2^48 - 1, the last a time field holds.',
                    );
                }

                $at++;
                $field = $this->seed($byteSource, $width);
            }
        } else {
            $at = $now;
            $field = $this->seed($byteSource, $width);
        }

        $id = Uxid::generate(
            prefix: $prefix,
            size: $size,
            randSize: $width,
            compactTime: $compact,
            case: $case,
            delimiter: $delimiter,
            time: $at,
            rand: $field,
        );

        $this->state[$key] = [$at, $field];

        return $id;
    }

    /**
     * The random field's width in bytes for a size and time form.
     */
    private static function randBytes(Size $size, bool $compactTime): int
    {
        return match ($size) {
            Size::XSmall => $compactTime ? 1 : 0,
            Size::Small => $compactTime ? 3 : 2,
            Size::Medium => $compactTime ? 6 : 5,
            Size::Large => $compactTime ? 8 : 7,
            Size::XLarge => 10,
        };
    }

    /**
     * Adds a step to a big-endian unsigned field, byte by byte from the
     * least significant end; null when the sum is 2^(8w) or more.
     */
    private static function addStep(string $field, int $step): ?string
    {
        $carry = $step;
        for ($index = \strlen($field) - 1; $index >= 0 && $carry > 0; $index--) {
            $sum = \ord($field[$index]) + ($carry & 0xFF);
            $field[$index] = \chr($sum & 0xFF);
            $carry = ($carry >> 8) + ($sum >> 8);
        }

        return $carry > 0 ? null : $field;
    }

    /**
     * A fresh random field of `$width` bytes: from the draw source when the
     * generator has one, otherwise from the byte source.
     *
     * @param (callable(int): mixed)|null $byteSource
     * @param int<1, 10>                  $width
     *
     * @throws \UnexpectedValueException when the source returns a value of the wrong type or length
     */
    private function seed(?callable $byteSource, int $width): string
    {
        if ($this->draws === null) {
            return self::draw($byteSource, $width);
        }

        $seed = $this->draws->seed($width);
        if (\strlen($seed) !== $width) {
            throw new \UnexpectedValueException(
                'The draw source returned a seed of ' . \strlen($seed) . ' bytes for a random field of ' . $width . '.',
            );
        }

        return $seed;
    }

    /**
     * A step from 1 to 2^(4w): from the draw source when the generator has
     * one, otherwise the low `4w` bits of `ceil(w / 2)` bytes from the byte
     * source, read big-endian, plus one.
     *
     * @param (callable(int): mixed)|null $byteSource
     * @param int<1, 10>                  $width
     *
     * @throws \UnexpectedValueException when the source returns a value of the wrong type or length, or a step out of range
     */
    private function step(?callable $byteSource, int $width): int
    {
        $bits = 4 * $width;
        if ($this->draws !== null) {
            $step = $this->draws->step($width);
            if ($step < 1 || $step > 1 << $bits) {
                throw new \UnexpectedValueException(
                    'The draw source returned the step ' . $step . ', outside 1 to 2^' . $bits . '.',
                );
            }

            return $step;
        }

        $value = 0;
        $bytes = self::draw($byteSource, ($width + 1) >> 1);
        for ($index = 0, $length = \strlen($bytes); $index < $length; $index++) {
            $value = ($value << 8) | \ord($bytes[$index]);
        }

        return ($value & ((1 << $bits) - 1)) + 1;
    }

    /**
     * Draws `$count` bytes from the caller's byte source, or from
     * `random_bytes` when there is none.
     *
     * @param (callable(int): mixed)|null $byteSource
     * @param int<1, max>                $count
     *
     * @throws \UnexpectedValueException when the source returns a value that is not a string, or another number of bytes
     */
    private static function draw(?callable $byteSource, int $count): string
    {
        if ($byteSource === null) {
            return random_bytes($count);
        }

        $bytes = $byteSource($count);
        if (!\is_string($bytes)) {
            throw new \UnexpectedValueException(
                'The byte source returned ' . get_debug_type($bytes) . ' when asked for ' . $count . ' bytes; it must return a string.',
            );
        }

        if (\strlen($bytes) !== $count) {
            throw new \UnexpectedValueException(
                'The byte source returned ' . \strlen($bytes) . ' bytes when asked for ' . $count . '.',
            );
        }

        return $bytes;
    }
}
