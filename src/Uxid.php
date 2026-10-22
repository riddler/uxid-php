<?php

declare(strict_types=1);

namespace Riddler\Uxid;

/**
 * Prefixed, K-sortable identifiers a person can read, wire-compatible with
 * the Elixir uxid package: an optional prefix and delimiter, then a body in
 * Crockford Base32 made of a millisecond time field and a random field, laid
 * out as uxid's wire-format specification defines.
 *
 * Given a `from` string instead, it derives the body from a SHA-256 hash of
 * the prefix and that string, so the same input always gives the same id. A
 * deterministic id is exactly as guessable as its input.
 *
 * {@see decode()} reads an id back to a {@see Decoded} result, or to an
 * {@see Invalid} one naming why a string is not an id, and never throws on
 * malformed input; {@see isValid()} and {@see isDeterministic()} answer the
 * same question as a boolean.
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

    /** How far ahead of the decoder's clock a compact time may be: one day, in milliseconds. */
    private const int ONE_DAY = 86400000;

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
     * With `$from` the id is deterministic: the body is the marker `z` (`Z` in
     * upper case) and then the first `5 x count` bits of the SHA-256 digest
     * of the prefix, a zero byte, `$from`, a zero byte and one byte holding
     * `count`, as `count` characters; `count` is the size's number of hash
     * characters (9, 13, 17, 21 or 25), so the body has a standard body's
     * length. The same prefix, `$from` and size always give the same id, and
     * the letter case and the delimiter are not part of the hash input. There
     * is no compact deterministic form. A deterministic id is exactly as
     * guessable as its input. `$from` and the prefix are hashed as the bytes
     * given, with no validation: pass text as UTF-8. The empty string is an
     * input like any other. The size is still parsed and the delimiter still
     * checked, but `$time`, the clock, `$compactTime`, `$randSize` and
     * `$byteSource` are not read.
     *
     *     Uxid::generate(prefix: 'usr', from: 'alice@example.com'); // usr_zcvt7epac0t1ebcsjfyf7cwz25
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
     * @param string|null                $from        the input of a deterministic id, hashed as the bytes given; null, the default, makes a time-based id; refused with `$rand`
     *
     * @return string the id
     *
     * @throws UxidException             the request is one the wire format refuses: `rand_size_too_large`, `rand_width_mismatch`, `compact_time_out_of_range` or `rand_with_from` (see {@see ErrorCode})
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
        ?string $from = null,
    ): string {
        self::requireSixtyFourBit(\PHP_INT_SIZE);

        if (\is_string($size)) {
            $size = Size::parse($size);
        }

        self::checkDelimiter($delimiter);

        if ($from !== null) {
            if ($rand !== null) {
                throw new UxidException(
                    ErrorCode::RandWithFrom,
                    'Fixed random bytes cannot be combined with a deterministic body (from).',
                );
            }

            return self::deterministic($prefix, $size, $case, $delimiter, $from);
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
     * Reads a string as an id: a {@see Decoded} for a well-formed id, an
     * {@see Invalid} naming the reason for any other string. It never throws
     * on the string, however long or malformed; what it is given is a value
     * to classify.
     *
     *     Uxid::decode('usr_01hf7yat0004h1');        // Decoded: prefix usr, Size::Small, time 1700000000000
     *     Uxid::decode('usr_');                      // Invalid: DecodeError::Empty
     *     Uxid::decode('cus-01hf7yat00', delimiter: '-');
     *
     * The body is everything after the last delimiter, and the prefix
     * everything before it; a string with no delimiter is a body with no
     * prefix (null). The body is refused for the first rule it breaks, in
     * this order: it is empty; its length in bytes is not 10, 13, 14, 18, 21,
     * 22, 24 or 26; a byte is not a Crockford Base32 symbol in either case
     * (no reading substitutions, no hyphens). The length gives the size and
     * the time form; 10 and 18 are always read as standard time, so a
     * compact XSmall or compact Medium id decodes as standard, with a time
     * read from its first 10 characters. A body starting with `z` or `Z` is
     * deterministic and carries no time.
     *
     * A compact body carries only the low 40 bits of its time, and the high
     * bits come from `$clock`: the clock's bits above bit 40 are combined
     * with the 40 decoded bits, and when that is more than one day
     * (86400000 ms) ahead of the clock, one window of 2^40 ms is subtracted,
     * with no floor at zero, so a clock in the first window can give a
     * negative time. The time is exact while the id and the clock fall in
     * the same 2^40 ms window (about 34.8 years) and the id is no more than
     * one day ahead of the clock. A fixed clock, such as
     * `fn(): int => 1700000000000`, makes a compact time's reading
     * reproducible.
     *
     * @param string               $id        the string to read
     * @param string               $delimiter the delimiter the id was generated with, `_` unless given; never empty
     * @param (callable(): int)|null $clock   the decoder's clock, returning the time in milliseconds since the Unix epoch, 0 to 2^48 - 1; null, the default, reads the system clock; it is called once for a compact time-based body and not at all otherwise
     *
     * @return Decoded|Invalid what the string says, or why it is not an id
     *
     * @throws \InvalidArgumentException the delimiter is empty
     * @throws \UnexpectedValueException the clock returned a value that is not an integer from 0 to 2^48 - 1
     * @throws \LogicException           the PHP build is not 64-bit
     */
    public static function decode(string $id, string $delimiter = '_', ?callable $clock = null): Decoded|Invalid
    {
        self::requireSixtyFourBit(\PHP_INT_SIZE);
        self::checkDelimiter($delimiter);

        [$prefix, $body] = self::split($id, $delimiter);
        $form = self::form($body);
        if ($form instanceof DecodeError) {
            return new Invalid($form);
        }

        [$size, $compact] = $form;
        $lower = strtolower($body);
        if ($lower[0] === 'z') {
            return new Decoded($prefix, $size, $compact, true, null);
        }

        $time = $compact
            ? self::compactTime(self::readInteger($lower, 0, 8), $clock)
            : ((self::readInteger($lower, 0, 1) & 7) << 45) | self::readInteger($lower, 1, 9);

        return new Decoded($prefix, $size, $compact, false, $time);
    }

    /**
     * Answers whether a string is a well-formed id under a delimiter, one
     * {@see decode()} would decode, and, given a required prefix, whether it
     * carries exactly that prefix. This checks structure, not origin: any
     * well-formed string passes. It reads no clock and never throws on the
     * string.
     *
     *     Uxid::isValid('usr_01hf7yat0004h1');                         // true
     *     Uxid::isValid('usr_01hf7yat0004h1', requiredPrefix: 'cus');  // false
     *
     * @param string      $id             the string to check
     * @param string      $delimiter      the delimiter the id was generated with, `_` unless given; never empty
     * @param string|null $requiredPrefix the prefix the id must carry, compared byte for byte; null or the empty string, the default, accepts any prefix or none; a string with no delimiter has no prefix, so it fails any other required prefix
     *
     * @return bool true when the string is well formed and carries the required prefix
     *
     * @throws \InvalidArgumentException the delimiter is empty
     */
    public static function isValid(string $id, string $delimiter = '_', ?string $requiredPrefix = null): bool
    {
        self::checkDelimiter($delimiter);

        [$prefix, $body] = self::split($id, $delimiter);

        return !self::form($body) instanceof DecodeError
            && ($requiredPrefix === null || $requiredPrefix === '' || $prefix === $requiredPrefix);
    }

    /**
     * Answers whether a string is a well-formed deterministic id under a
     * delimiter: one {@see decode()} would decode whose body starts with the
     * marker `z` or `Z`. A malformed string is not one, whatever its first
     * body character. It reads no clock and never throws on the string.
     *
     *     Uxid::isDeterministic('usr_zcvt7epac0t1ebcsjfyf7cwz25'); // true
     *     Uxid::isDeterministic('usr_01hf7yat0004h1');             // false
     *
     * @param string $id        the string to check
     * @param string $delimiter the delimiter the id was generated with, `_` unless given; never empty
     *
     * @return bool true when the string is well formed and deterministic
     *
     * @throws \InvalidArgumentException the delimiter is empty
     */
    public static function isDeterministic(string $id, string $delimiter = '_'): bool
    {
        self::checkDelimiter($delimiter);

        [, $body] = self::split($id, $delimiter);

        return !self::form($body) instanceof DecodeError && ($body[0] === 'z' || $body[0] === 'Z');
    }

    /**
     * Refuses an empty delimiter.
     *
     * @throws \InvalidArgumentException when the delimiter is empty
     *
     * @phpstan-assert non-empty-string $delimiter
     */
    private static function checkDelimiter(string $delimiter): void
    {
        if ($delimiter === '') {
            throw new \InvalidArgumentException('The delimiter must be a non-empty string.');
        }
    }

    /**
     * Splits a string into its prefix and body at the last delimiter, the
     * string read left to right as non-overlapping delimiters; the prefix is
     * null when there is no delimiter.
     *
     * @param non-empty-string $delimiter
     *
     * @return array{?string, string} the prefix and the body
     */
    private static function split(string $id, string $delimiter): array
    {
        $parts = explode($delimiter, $id);
        $body = array_pop($parts);
        if ($parts === []) {
            return [null, $body];
        }

        return [implode($delimiter, $parts), $body];
    }

    /**
     * The size and time form a well-formed body's length gives, or the first
     * rule the body breaks, in the order empty, length in bytes, alphabet.
     *
     * @return array{Size, bool}|DecodeError
     */
    private static function form(string $body): array|DecodeError
    {
        if ($body === '') {
            return DecodeError::Empty;
        }

        $length = \strlen($body);
        $form = match ($length) {
            10 => [Size::XSmall, false],
            13 => [Size::Small, true],
            14 => [Size::Small, false],
            18 => [Size::Medium, false],
            21 => [Size::Large, true],
            22 => [Size::Large, false],
            24 => [Size::XLarge, true],
            26 => [Size::XLarge, false],
            default => DecodeError::InvalidLength,
        };
        if ($form instanceof DecodeError) {
            return $form;
        }

        if (strspn($body, self::LOWER_ALPHABET . self::UPPER_ALPHABET) !== $length) {
            return DecodeError::InvalidCharacter;
        }

        return $form;
    }

    /**
     * Reads `$chars` lower-case symbols from `$offset` as one integer, 5 bits
     * per symbol, most significant first. The caller has checked that every
     * symbol is in the alphabet.
     */
    private static function readInteger(string $lower, int $offset, int $chars): int
    {
        $value = 0;
        for ($index = $offset; $index < $offset + $chars; $index++) {
            $value = ($value << 5) | (int) strpos(self::LOWER_ALPHABET, $lower[$index]);
        }

        return $value;
    }

    /**
     * The full time of a compact body: its low 40 bits under the clock's
     * high bits, less one 2^40 ms window when that lands more than one day
     * ahead of the clock, with no floor at zero.
     *
     * @param (callable(): mixed)|null $clock
     *
     * @throws \UnexpectedValueException when the clock returns a value that is not an integer from 0 to 2^48 - 1
     */
    private static function compactTime(int $low, ?callable $clock): int
    {
        $now = $clock === null ? self::now() : $clock();
        if (!\is_int($now) || $now < 0 || $now > self::MAX_TIME) {
            throw new \UnexpectedValueException(
                'The clock must return an integer from 0 to 2^48 - 1 milliseconds, got '
                    . (\is_int($now) ? (string) $now : get_debug_type($now)) . '.',
            );
        }

        $time = ($now & ~self::COMPACT_MASK) | $low;

        return $time - $now > self::ONE_DAY ? $time - (self::COMPACT_MASK + 1) : $time;
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
     * The deterministic id: the marker, then the leading 5-bit groups of the
     * SHA-256 hash of the prefix, a zero byte, the input, a zero byte and the
     * hash character count as one byte.
     */
    private static function deterministic(string $prefix, Size $size, LetterCase $case, string $delimiter, string $from): string
    {
        $count = self::hashChars($size);
        $digest = hash('sha256', $prefix . "\x00" . $from . "\x00" . \chr($count), true);
        $alphabet = $case === LetterCase::Upper ? self::UPPER_ALPHABET : self::LOWER_ALPHABET;

        $body = $alphabet[self::MARKER];
        for ($group = 0; $group < $count; $group++) {
            $bit = 5 * $group;
            $window = (\ord($digest[$bit >> 3]) << 8) | \ord($digest[($bit >> 3) + 1]);
            $body .= $alphabet[($window >> (11 - ($bit & 7))) & 31];
        }

        return $prefix === '' ? $body : $prefix . $delimiter . $body;
    }

    /**
     * The number of hash characters in a deterministic body for a size.
     */
    private static function hashChars(Size $size): int
    {
        return match ($size) {
            Size::XSmall => 9,
            Size::Small => 13,
            Size::Medium => 17,
            Size::Large => 21,
            Size::XLarge => 25,
        };
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
