<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * Prefixed, K-sortable identifiers a person can read, wire-compatible with
 * the Elixir uxid package: an optional prefix and delimiter, then a body in
 * Crockford Base32 made of a millisecond time field and a random field, laid
 * out as uxid's wire-format specification defines.
 *
 * Every method is static and the class holds no state; it cannot be
 * instantiated. It needs a 64-bit PHP build, since the time field is 48 bits
 * wide.
 */
final class Uxid
{
    /**
     * The package version. It names no released version until the first
     * release sets it; the release workflow checks a tag against it and
     * refuses any value that is not a plain MAJOR.MINOR.PATCH version, so
     * this pre-release placeholder can never be tagged as a release.
     */
    public const string VERSION = '0.0.0-dev';

    /** The latest time a standard time field holds: 2^48 - 1 milliseconds. */
    private const int MAX_TIME = 281474976710655;

    /** The low 40 bits of the time, the part a compact time field holds. */
    private const int COMPACT_MASK = 1099511627775;

    /** The widest random field at any size, in bytes. */
    private const int MAX_RAND_BYTES = 10;

    /** The deterministic marker's value, which a compact time may not start with. */
    private const int MARKER = 31;

    private const string LOWER_ALPHABET = '0123456789abcdefghjkmnpqrstvwxyz';

    private const string UPPER_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private function __construct() {}

    /**
     * Generates an id: the prefix and the delimiter (neither for no prefix),
     * then the time field and the random field, in one letter case.
     *
     * Call it with named arguments; every argument has a default:
     *
     *     Uxid::generate(prefix: 'usr');                    // usr_01hf7yat0004hmasw9nf6yy093 shaped
     *     Uxid::generate(prefix: 'cus', size: Size::Small); // cus_01hf7yat0004h1 shaped
     *
     * The random field's bytes come from `$byteSource`, which defaults to
     * `random_bytes`, the platform's cryptographically secure source. `$rand`
     * sets the random field to fixed bytes instead, so that an id can be
     * reproduced exactly: it exists for test vectors, and an id built from
     * fixed bytes is only as unpredictable as those bytes.
     *
     * Lengths are counted in bytes: the prefix and the delimiter are byte
     * strings, written as given.
     *
     * @param string                     $prefix      the prefix; the empty string, the default, is no prefix, and the id is then the bare body
     * @param Size|string                $size        the size, or its short (`xs` ... `xl`) or long (`xsmall` ... `xlarge`) spelling; the default is XLarge
     * @param int|null                   $randSize    the random field's width in bytes, 0 to 10, in place of the width the size and time form give
     * @param bool                       $compactTime true for compact time (the low 40 bits of the time, 8 characters), false (the default) for standard time (48 bits, 10 characters)
     * @param LetterCase                 $case        the letter case of the body; the prefix is never changed
     * @param string                     $delimiter   the string between the prefix and the body, `_` unless given; never empty
     * @param int|null                   $time        the time in milliseconds since the Unix epoch, 0 to 2^48 - 1; null, the default, reads the system clock at the call
     * @param string|null                $rand        fixed random bytes, exactly the random field's width, for reproducible test vectors; null, the default, draws them from the byte source
     * @param (callable(int): string)|null $byteSource a source of random bytes, called with a byte count and returning a string of exactly that many bytes; null, the default, is `random_bytes`; it is not called for an empty random field or when `$rand` is given
     *
     * @return string the id
     *
     * @throws UxidException             the request is one the wire format refuses: `rand_size_too_large`, `rand_width_mismatch` or `compact_time_out_of_range` (see {@see ErrorCode})
     * @throws \InvalidArgumentException the size string is no size's spelling, the delimiter is empty, the random field width is negative, or the time (or the system clock) is outside 0 to 2^48 - 1
     * @throws \UnexpectedValueException the byte source returned a value that is not a string, or a string of another length than it was asked for
     * @throws \LogicException           the PHP build is not 64-bit
     */
    public static function generate(
        string $prefix = '',
        Size|string $size = Size::XLarge,
        ?int $randSize = null,
        bool $compactTime = false,
        LetterCase $case = LetterCase::Lower,
        string $delimiter = '_',
        ?int $time = null,
        ?string $rand = null,
        ?callable $byteSource = null,
    ): string {
        self::requireSixtyFourBit(\PHP_INT_SIZE);

        if (\is_string($size)) {
            $size = Size::parse($size);
        }

        if ($delimiter === '') {
            throw new \InvalidArgumentException('The delimiter must be a non-empty string.');
        }

        $time ??= self::now();
        if ($time < 0 || $time > self::MAX_TIME) {
            throw new \InvalidArgumentException(
                'The time must be from 0 to 2^48 - 1 milliseconds since the Unix epoch, got ' . $time . '.',
            );
        }

        $width = self::randBytes($size, $compactTime);
        if ($randSize !== null) {
            if ($randSize < 0) {
                throw new \InvalidArgumentException(
                    'The random field width must not be negative, got ' . $randSize . '.',
                );
            }

            if ($randSize > self::MAX_RAND_BYTES) {
                throw new UxidException(
                    ErrorCode::RandSizeTooLarge,
                    'The random field width must be at most 10 bytes, got ' . $randSize . '.',
                );
            }

            $width = $randSize;
        }

        if ($rand !== null && \strlen($rand) !== $width) {
            throw new UxidException(
                ErrorCode::RandWidthMismatch,
                'The fixed random bytes must be exactly ' . $width . ' bytes for this request, got '
                    . \strlen($rand) . '.',
            );
        }

        $compact = $time & self::COMPACT_MASK;
        if ($compactTime && ($compact >> 35) === self::MARKER) {
            throw new UxidException(
                ErrorCode::CompactTimeOutOfRange,
                'A compact time whose first character would be the deterministic marker cannot be encoded;'
                    . ' use standard time.',
            );
        }

        $rand ??= self::draw($byteSource, $width);
        $alphabet = $case === LetterCase::Upper ? self::UPPER_ALPHABET : self::LOWER_ALPHABET;

        $body = $compactTime
            ? self::encodeInteger($compact, 8, $alphabet)
            : self::encodeInteger($time, 10, $alphabet);
        $body .= self::encodeBytes($rand, $alphabet);

        return $prefix === '' ? $body : $prefix . $delimiter . $body;
    }

    /**
     * Refuses a PHP build whose integers are narrower than 64 bits, which
     * cannot hold a 48-bit time.
     *
     * @throws \LogicException when the integer size is under 8 bytes
     */
    private static function requireSixtyFourBit(int $intSize): void
    {
        if ($intSize < 8) {
            throw new \LogicException('uxid needs a 64-bit PHP build: the time field is 48 bits wide.');
        }
    }

    /**
     * The system clock in whole milliseconds since the Unix epoch.
     */
    private static function now(): int
    {
        return (int) (new \DateTimeImmutable())->format('Uv');
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
     * Draws `$count` bytes from the caller's source, or from `random_bytes`
     * when there is none. An empty field draws nothing.
     *
     * The source is typed as returning anything, since a caller's untyped
     * closure may return a value that is not a string; that is refused like
     * a string of the wrong length.
     *
     * @param (callable(int): mixed)|null $byteSource
     *
     * @throws \UnexpectedValueException when the source returns a value that is not a string, or another number of bytes
     */
    private static function draw(?callable $byteSource, int $count): string
    {
        if ($count < 1) {
            return '';
        }

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

    /**
     * Writes the low `5 x $chars` bits of a non-negative integer as `$chars`
     * characters, most significant first. A 48-bit time in 10 characters
     * starts with its 3-bit short group, whose top 2 bits are zero.
     */
    private static function encodeInteger(int $value, int $chars, string $alphabet): string
    {
        $text = '';
        for ($group = $chars - 1; $group >= 0; $group--) {
            $text .= $alphabet[($value >> (5 * $group)) & 31];
        }

        return $text;
    }

    /**
     * Writes bytes as `ceil(8 x count / 5)` characters, most significant bit
     * first; when the bit count is not a multiple of 5 the last character is
     * the short group's raw value, not shifted or padded.
     */
    private static function encodeBytes(string $bytes, string $alphabet): string
    {
        $text = '';
        $buffer = 0;
        $bits = 0;
        $length = \strlen($bytes);
        for ($index = 0; $index < $length; $index++) {
            $buffer = ($buffer << 8) | \ord($bytes[$index]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $text .= $alphabet[($buffer >> $bits) & 31];
            }

            $buffer &= (1 << $bits) - 1;
        }

        if ($bits > 0) {
            $text .= $alphabet[$buffer];
        }

        return $text;
    }
}
