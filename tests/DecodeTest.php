<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Riddler\Uxid\Decoded;
use Riddler\Uxid\DecodeError;
use Riddler\Uxid\Invalid;
use Riddler\Uxid\LetterCase;
use Riddler\Uxid\Size;
use Riddler\Uxid\Uxid;

/**
 * Uxid::decode: the conformance corpus's decode cases and its decode
 * refusals (uxid-ex, tag v2.10.0), copied as inputs and expected answers,
 * the decoder clock window the corpus README states, and the behavior the
 * corpus does not pin.
 */
#[CoversClass(Uxid::class)]
#[CoversClass(Decoded::class)]
#[CoversClass(Invalid::class)]
final class DecodeTest extends TestCase
{
    /** 2^40 ms, one compact time window. */
    private const int WINDOW = 1099511627776;

    /** One day in milliseconds, how far ahead of the clock a compact time may be. */
    private const int ONE_DAY = 86400000;

    /**
     * The corpus window's first clock (inclusive): a clock no more than one
     * day behind the cases at 1700000000000 ms recovers them.
     */
    private const int CLOCK_FIRST = 1700000000000 - self::ONE_DAY;

    /**
     * The corpus window's end (exclusive): the compact guard's last valid
     * millisecond from the window before, 1065151889407, is recovered by the
     * one-window subtraction only while 2^40 + 1065151889407 is more than
     * one day ahead of the clock.
     */
    private const int CLOCK_END = self::WINDOW + 1065151889407 - self::ONE_DAY;

    /**
     * Decode cases from the corpus, by case id: the input, then the expected
     * prefix, size, compact flag, deterministic flag and time.
     *
     * @return array<string, array{string, ?string, string, bool, bool, ?int}>
     */
    public static function corpusDecodeCases(): array
    {
        return [
            'decode/xl-standard' => ['usr_01hf7yat0004hmasw9nf6yy093', 'usr', 'xl', false, false, 1700000000000],
            'decode/xl-compact' => ['usr_hf7yat0004hmasw9nf6yy093', 'usr', 'xl', true, false, 1700000000000],
            'decode/l-standard' => ['usr_01hf7yat0004hmasw9nf61', 'usr', 'l', false, false, 1700000000000],
            'decode/l-compact' => ['usr_hf7yat0004hmasw9nf6yf', 'usr', 'l', true, false, 1700000000000],
            'decode/m-standard' => ['usr_01hf7yat0004hmasw9', 'usr', 'm', false, false, 1700000000000],
            'decode/m-compact-reads-as-standard' => ['usr_hf7yat0004hmasw9n3', 'usr', 'm', false, false, 51950139736068],
            'decode/s-standard' => ['usr_01hf7yat0004h1', 'usr', 's', false, false, 1700000000000],
            'decode/s-compact' => ['usr_hf7yat0004hm5', 'usr', 's', true, false, 1700000000000],
            'decode/xs-standard' => ['usr_01hf7yat00', 'usr', 'xs', false, false, 1700000000000],
            'decode/xs-compact-reads-as-standard' => ['usr_hf7yat0001', 'usr', 'xs', false, false, 51950139736065],
            'decode/xl-standard-upper' => ['usr_01HF7YAT0004HMASW9NF6YY093', 'usr', 'xl', false, false, 1700000000000],
            'decode/xl-standard-no-prefix' => ['01hf7yat0004hmasw9nf6yy093', null, 'xl', false, false, 1700000000000],
            'decode/s-standard-prefix-with-delimiter' => ['a_b_01hf7yat000001', 'a_b', 's', false, false, 1700000000000],
            'decode/s-compact-guard-last-valid' => ['usr_yzzzzzzz04hm5', 'usr', 's', true, false, 1065151889407],
            'decode/deterministic-usr-xl' => ['usr_zcvt7epac0t1ebcsjfyf7cwz25', 'usr', 'xl', false, true, null],
            'decode/deterministic-cus-s' => ['cus_zt9vnyfd85bff0', 'cus', 's', false, true, null],
            'decode/deterministic-no-prefix-m' => ['zqz4cwzgyw8ks97869', null, 'm', false, true, null],
            'decode/deterministic-upper-marker' => ['usr_ZCVT7EPAC0T1EBCSJFYF7CWZ25', 'usr', 'xl', false, true, null],
        ];
    }

    /**
     * Decode refusals from the corpus, by case id: the input and the
     * expected reason.
     *
     * @return array<string, array{string, string}>
     */
    public static function corpusInvalidDecodeCases(): array
    {
        return [
            'invalid/decode-empty-string' => ['', 'empty'],
            'invalid/decode-ends-in-delimiter' => ['usr_', 'empty'],
            'invalid/decode-prefix-with-delimiter-ends-in-delimiter' => ['a_b_', 'empty'],
            'invalid/decode-delimiter-only' => ['_', 'empty'],
            'invalid/decode-length-11' => ['usr_01hf7yat000', 'invalid_length'],
            'invalid/decode-length-25' => ['usr_01hf7yat00000g40r40m30e20', 'invalid_length'],
            'invalid/decode-length-27' => ['usr_01hf7yat00000g40r40m30e2090', 'invalid_length'],
            'invalid/decode-no-delimiter-short' => ['usr', 'invalid_length'],
            'invalid/decode-character-in-time' => ['usr_01hu7yat00000g40r40m30e209', 'invalid_character'],
            'invalid/decode-character-in-random' => ['usr_01hf7yat00000g4lr40m30e209', 'invalid_character'],
            'invalid/decode-upper-o-in-random' => ['usr_01hf7yat00000g40r40mO0e209', 'invalid_character'],
            'invalid/decode-hyphen-in-body' => ['usr_01hf7yat00-00g40r40m30e209', 'invalid_character'],
            'invalid/decode-character-in-deterministic' => ['usr_zcvt7epac0t1ebcsjfyf7cwzi5', 'invalid_character'],
            'invalid/decode-non-ascii-table-length' => ["01hf7yat00000g40r40m30e2\u{e9}", 'invalid_character'],
            'invalid/decode-non-ascii-off-table-length' => ["01hf7yat00000g40r40m30e20\u{e9}", 'invalid_length'],
            'invalid/decode-length-and-character' => ['usr_01hf7yat00u', 'invalid_length'],
        ];
    }

    /**
     * The corpus's compact time-based decode cases, by case id: the input
     * and the expected time.
     *
     * @return array<string, array{string, int}>
     */
    public static function corpusCompactCases(): array
    {
        return [
            'decode/xl-compact' => ['usr_hf7yat0004hmasw9nf6yy093', 1700000000000],
            'decode/l-compact' => ['usr_hf7yat0004hmasw9nf6yf', 1700000000000],
            'decode/s-compact' => ['usr_hf7yat0004hm5', 1700000000000],
            'decode/s-compact-guard-last-valid' => ['usr_yzzzzzzz04hm5', 1065151889407],
        ];
    }

    /**
     * Strings no producer emits, each with the reason decode refuses it.
     *
     * @return array<string, array{string, string}>
     */
    public static function garbageCases(): array
    {
        return [
            'a lone delimiter' => ['_', 'empty'],
            'only delimiters' => ['____', 'empty'],
            'a very long string' => [str_repeat('0', 1 << 20), 'invalid_length'],
            'a very long string after a delimiter' => ['usr_' . str_repeat('z', 100000), 'invalid_length'],
            'invalid UTF-8 at a table length' => [str_repeat("\xff\xfe", 13), 'invalid_character'],
            'a trailing NUL byte' => ["01hf7yat00\x00", 'invalid_length'],
            'NUL bytes at a table length' => [str_repeat("\x00", 10), 'invalid_character'],
            'a lone continuation byte' => ["\x80", 'invalid_length'],
            'a leading space at a table length' => [' 01hf7yat0004h', 'invalid_character'],
            'a body of only the marker' => ['z', 'invalid_length'],
        ];
    }

    # sabotage: drop the & 7 on the first standard time symbol in decode -> assertSame failed: two arrays are not identical, time 614900093157377 for 51950139736065 (decode/xs-compact-reads-as-standard; decode/m-compact-reads-as-standard too)
    #[DataProvider('corpusDecodeCases')]
    public function testCorpusDecodeCase(
        string $input,
        ?string $prefix,
        string $size,
        bool $compact,
        bool $deterministic,
        ?int $time,
    ): void {
        $decoded = Uxid::decode($input, clock: static fn(): int => 1700000000000);

        self::assertInstanceOf(Decoded::class, $decoded);
        self::assertSame(
            [$prefix, $size, $compact, $deterministic, $time],
            [$decoded->prefix, $decoded->size->value, $decoded->compactTime, $decoded->deterministic, $decoded->time],
        );
    }

    # sabotage: check the alphabet before the length in form -> assertSame failed: 'invalid_character' is not identical to 'invalid_length' (invalid/decode-non-ascii-off-table-length, invalid/decode-length-and-character and one more set)
    #[DataProvider('corpusInvalidDecodeCases')]
    public function testCorpusInvalidDecodeCase(string $input, string $reason): void
    {
        $invalid = Uxid::decode($input, clock: static fn(): int => 1700000000000);

        self::assertInstanceOf(Invalid::class, $invalid);
        self::assertSame($reason, $invalid->reason->value);
    }

    # sabotage: subtract the window at one day ahead or more (>= in place of >) in compactTime -> assertSame failed: 600488372224 is not identical to 1700000000000 (decode/xl-compact, l-compact and s-compact at the first clock)
    #[DataProvider('corpusCompactCases')]
    public function testTheCompactCasesHoldAtTheFirstClockOfTheWindow(string $input, int $time): void
    {
        self::assertSame(1699913600000, self::CLOCK_FIRST);
        self::assertSame($time, self::decodedTime($input, self::CLOCK_FIRST));
    }

    # sabotage: subtract the window only past one day and a millisecond (self::ONE_DAY + 1) in compactTime -> assertSame failed: 2164663517183 is not identical to 1065151889407 (decode/s-compact-guard-last-valid at the last clock)
    #[DataProvider('corpusCompactCases')]
    public function testTheCompactCasesHoldAtTheLastClockOfTheWindow(string $input, int $time): void
    {
        self::assertSame(2164577117183, self::CLOCK_END);
        self::assertSame($time, self::decodedTime($input, self::CLOCK_END - 1));
    }

    # sabotage: subtract the window only past two days (2 * self::ONE_DAY) in compactTime -> assertSame failed: 1700000000000 is not identical to 600488372224
    public function testAClockJustBeforeTheWindowStepsTheCasesBack(): void
    {
        self::assertSame(
            1700000000000 - self::WINDOW,
            self::decodedTime('usr_hf7yat0004hmasw9nf6yy093', self::CLOCK_FIRST - 1),
        );
    }

    # sabotage: subtract the window at one day ahead or more (>= in place of >) in compactTime -> assertSame failed: 1065151889407 is not identical to 2164663517183
    public function testAClockAtTheWindowEndNoLongerStepsTheGuardCaseBack(): void
    {
        self::assertSame(
            self::WINDOW + 1065151889407,
            self::decodedTime('usr_yzzzzzzz04hm5', self::CLOCK_END),
        );
    }

    # sabotage: clamp the stepped-back time at zero in compactTime (max(0, ...)) -> assertSame failed: 0 is not identical to -499023255552
    public function testAClockInTheFirstWindowStepsBackToANegativeTime(): void
    {
        self::assertSame(1700000000000 - self::WINDOW - self::WINDOW, -499023255552);
        self::assertSame(-499023255552, self::decodedTime('usr_hf7yat0004hm5', 0));
        self::assertSame(-499023255552, self::decodedTime('usr_hf7yat0004hm5', 1000));
    }

    # sabotage: keep the clock's low bits ($now | $low) in compactTime -> assertSame failed: 600756809727 is not identical to 1700000000000
    public function testACompactTimeTakesOnlyTheHighBitsOfTheClock(): void
    {
        self::assertSame(1700000000000, self::decodedTime('usr_hf7yat0004hm5', 1700000000000 + 2097151));
        self::assertSame(1700000000000 + self::WINDOW, self::decodedTime('usr_hf7yat0004hm5', 1700000000000 + self::WINDOW));
    }

    # sabotage: take the compact branch for every time-based body in decode (($compact || true)) -> assertSame failed: 1 is not identical to 0
    public function testTheClockIsCalledOnceForACompactBodyAndNeverOtherwise(): void
    {
        $calls = 0;
        $clock = static function () use (&$calls): int {
            $calls++;

            return 1700000000000;
        };

        Uxid::decode('usr_01hf7yat0004h1', clock: $clock);
        Uxid::decode('usr_zcvt7epac0t1ebcsjfyf7cwz25', clock: $clock);
        Uxid::decode('usr_zt9vnyfd85bf0', clock: $clock);
        Uxid::decode('usr_hf7yat0004hm', clock: $clock);
        self::assertSame(0, $calls);

        Uxid::decode('usr_hf7yat0004hm5', clock: $clock);
        self::assertSame(1, $calls);
    }

    # sabotage: return 0 from now() -> assertSame failed: a negative time (-406388144930 in the run) is not identical to the clock read before the call
    public function testTheDefaultClockIsTheSystemClock(): void
    {
        $now = (int) floor(microtime(true) * 1000);
        $id = Uxid::generate(prefix: 'usr', size: Size::Small, compactTime: true, time: $now, rand: "\x00\x00\x00");

        $decoded = Uxid::decode($id);

        self::assertInstanceOf(Decoded::class, $decoded);
        self::assertSame($now, $decoded->time);
    }

    # sabotage: drop the is_int check on the clock's value in compactTime -> expectException failed: no UnexpectedValueException was thrown
    public function testAClockThatReturnsNoIntegerIsRefused(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('string');

        // Through reflection, as a caller without static analysis would call
        // it: the untyped closure the check exists for.
        (new \ReflectionMethod(Uxid::class, 'decode'))->invokeArgs(null, [
            'id' => 'usr_hf7yat0004hm5',
            'clock' => static fn(): string => '1700000000000',
        ]);
    }

    # sabotage: drop the lower bound ($now < 0) of the clock check in compactTime -> expectException failed: no UnexpectedValueException was thrown
    public function testAClockOutsideTheTimeRangeIsRefused(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('-1');

        Uxid::decode('usr_hf7yat0004hm5', clock: static fn(): int => -1);
    }

    # sabotage: drop the upper bound ($now > self::MAX_TIME) of the clock check in compactTime -> expectException failed: no UnexpectedValueException was thrown
    public function testAClockPastTheLatestTimeIsRefused(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        Uxid::decode('usr_hf7yat0004hm5', clock: static fn(): int => 281474976710656);
    }

    # sabotage: drop the empty-body check in form -> assertSame failed: 'invalid_length' is not identical to 'empty' (a lone delimiter, only delimiters)
    #[DataProvider('garbageCases')]
    public function testMalformedInputIsRefusedNotThrown(string $input, string $reason): void
    {
        $invalid = Uxid::decode($input);

        self::assertInstanceOf(Invalid::class, $invalid);
        self::assertSame($reason, $invalid->reason->value);
    }

    # sabotage: split at the first delimiter in split (explode with limit 2) -> assertInstanceOf failed: an Invalid is not a Decoded
    public function testACustomDelimiterSplitsAtItsLastOccurrence(): void
    {
        $decoded = Uxid::decode('a--b--01hf7yat0004h1', delimiter: '--');

        self::assertInstanceOf(Decoded::class, $decoded);
        self::assertSame('a--b', $decoded->prefix);
        self::assertSame(Size::Small, $decoded->size);
        self::assertSame(1700000000000, $decoded->time);
    }

    # sabotage: split at strrpos (the last start of the delimiter, overlapping) in split -> assertInstanceOf failed: a Decoded is not an Invalid
    public function testDelimitersAreReadLeftToRightWithoutOverlap(): void
    {
        $decoded = Uxid::decode('a___01hf7yat00', delimiter: '__');

        self::assertInstanceOf(Invalid::class, $decoded);
        self::assertSame(DecodeError::InvalidLength, $decoded->reason);
    }

    # sabotage: report an empty prefix as null in split ($parts === [''] treated as none) -> assertSame failed: null is not identical to ''
    public function testAStringStartingWithTheDelimiterHasTheEmptyPrefix(): void
    {
        $decoded = Uxid::decode('_01hf7yat00');

        self::assertInstanceOf(Decoded::class, $decoded);
        self::assertSame('', $decoded->prefix);
    }

    # sabotage: read length 13 as standard Small in form -> assertTrue failed: false is not true (compactTime)
    public function testACompactDeterministicLengthDecodesAsDeterministicWithItsLengthsForm(): void
    {
        $decoded = Uxid::decode('usr_zt9vnyfd85bff', clock: static fn(): int => throw new \LogicException('clock read'));

        self::assertInstanceOf(Decoded::class, $decoded);
        self::assertTrue($decoded->deterministic);
        self::assertTrue($decoded->compactTime);
        self::assertSame(Size::Small, $decoded->size);
        self::assertNull($decoded->time);
    }

    # sabotage: drop strtolower in decode ($lower = $body) -> assertSame failed: 1099746508800 is not identical to 1700000000000
    public function testAnUpperCaseCompactBodyDecodesToTheSameTime(): void
    {
        $upper = Uxid::generate(prefix: 'usr', size: Size::Large, compactTime: true, case: LetterCase::Upper, time: 1700000000000);

        self::assertSame(1700000000000, self::decodedTime($upper, 1700000000000));
    }

    # sabotage: drop checkDelimiter in decode -> assertInstanceOf failed: a ValueError is not an InvalidArgumentException
    public function testAnEmptyDelimiterIsRefused(): void
    {
        self::assertRefusesTheDelimiter(static fn(): Decoded|Invalid => Uxid::decode('usr_01hf7yat00', delimiter: ''));
    }

    /**
     * The time decode reads from a string under a fixed clock; the string
     * must decode.
     */
    private static function decodedTime(string $input, int $clock): ?int
    {
        $decoded = Uxid::decode($input, clock: static fn(): int => $clock);
        self::assertInstanceOf(Decoded::class, $decoded);

        return $decoded->time;
    }

    /**
     * Asserts that a call throws an InvalidArgumentException naming the
     * delimiter; any other throwable fails the assertion rather than
     * erroring the test.
     */
    private static function assertRefusesTheDelimiter(callable $call): void
    {
        try {
            $call();
        } catch (\Throwable $thrown) {
            self::assertInstanceOf(\InvalidArgumentException::class, $thrown);
            self::assertStringContainsString('delimiter', $thrown->getMessage());

            return;
        }

        self::fail('the empty delimiter was not refused');
    }
}
