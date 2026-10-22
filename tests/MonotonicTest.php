<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Riddler\Uxid\Decoded;
use Riddler\Uxid\DrawSource;
use Riddler\Uxid\ErrorCode;
use Riddler\Uxid\LetterCase;
use Riddler\Uxid\Monotonic;
use Riddler\Uxid\Size;
use Riddler\Uxid\Uxid;
use Riddler\Uxid\UxidException;

/**
 * Monotonic: the conformance corpus's monotonic cases and its two monotonic
 * generation refusals (uxid-ex, tag v2.10.0), copied as inputs and expected
 * strings, beside the behavior the corpus does not pin: the state per prefix
 * and width, the default draws, the state after a refused call and the
 * refusals of the arguments.
 */
#[CoversClass(Monotonic::class)]
#[CoversClass(Uxid::class)]
#[CoversClass(Size::class)]
#[CoversClass(UxidException::class)]
#[UsesClass(Decoded::class)]
final class MonotonicTest extends TestCase
{
    /** The last valid compact time: the next millisecond's first character is the marker. */
    private const int LAST_COMPACT = 1065151889407;

    /**
     * The corpus's monotonic cases, by case id: prefix, size, compact, the
     * time of each call, the initial seed, the steps, the overflow seed and
     * the expected ids.
     *
     * @return array<string, array{?string, string, bool, list<int>, int, list<int>, ?int, list<string>}>
     */
    public static function corpusMonotonicCases(): array
    {
        return [
            'monotonic/xl-same-millisecond' => [
                'usr', 'xl', false, [1700000000000, 1700000000000, 1700000000000, 1700000000000], 123456789, [1, 1099511627776, 42], null,
                ['usr_01hf7yat0000000000003nqk8n', 'usr_01hf7yat0000000000003nqk8p', 'usr_01hf7yat0000000001003nqk8p', 'usr_01hf7yat0000000001003nqka0'],
            ],
            'monotonic/m-clock-moves-back' => [
                'usr', 'm', false, [1700000000000, 1699999999999, 1699999999990, 1700000000000], 1000, [5, 1048576, 7], null,
                ['usr_01hf7yat00000000z8', 'usr_01hf7yat00000000zd', 'usr_01hf7yat00000100zd', 'usr_01hf7yat00000100zm'],
            ],
            'monotonic/s-overflow-bumps-time' => [
                'usr', 's', false, [1700000000000, 1700000000000, 1700000000000, 1700000000000, 1700000000000, 1700000000001], 65000, [200, 250, 100, 7, 3], 1234,
                ['usr_01hf7yat00zqm0', 'usr_01hf7yat00ztr0', 'usr_01hf7yat00zyn0', 'usr_01hf7yat010k90', 'usr_01hf7yat010kc1', 'usr_01hf7yat010ke0'],
            ],
            'monotonic/xs-compact-overflow-clock-back' => [
                'usr', 'xs', true, [1700000000000, 1700000000000, 1699999999999], 250, [5, 16], 9,
                ['usr_hf7yat00z2', 'usr_hf7yat00z7', 'usr_hf7yat0111'],
            ],
            'monotonic/l-compact-single' => [
                'usr', 'l', true, [1700000000000], 0, [], null,
                ['usr_hf7yat000000000000000'],
            ],
            'monotonic/s-compact-no-prefix-largest-step' => [
                null, 's', true, [1700000000000, 1700000000000], 0, [4096], null,
                ['hf7yat0000000', 'hf7yat0000800'],
            ],
        ];
    }

    /**
     * @param list<int>    $times
     * @param list<int>    $steps
     * @param list<string> $expected
     */
    # sabotage: drop the byte's own carry in addStep ($carry = ($carry >> 8)) -> assertSame failed: two arrays are not identical (monotonic/s-overflow-bumps-time and monotonic/xs-compact-overflow-clock-back)
    #[DataProvider('corpusMonotonicCases')]
    public function testCorpusMonotonicCase(
        ?string $prefix,
        string $size,
        bool $compact,
        array $times,
        int $initialSeed,
        array $steps,
        ?int $overflowSeed,
        array $expected,
    ): void {
        $seeds = $overflowSeed === null ? [$initialSeed] : [$initialSeed, $overflowSeed];
        $ids = new Monotonic(new ReplayDraws($seeds, $steps));

        $got = [];
        foreach ($times as $time) {
            $got[] = $ids->generate(prefix: $prefix ?? '', size: $size, compactTime: $compact, time: $time);
        }

        self::assertSame($expected, $got);
    }

    # sabotage: throw ErrorCode::RandWithFrom for a $from in generate -> assertSame failed: ErrorCode RandWithFrom is not the same as FromWithMonotonic
    public function testCorpusRefusesFromWithMonotonic(): void
    {
        $this->assertRefused(
            ErrorCode::FromWithMonotonic,
            static fn(Monotonic $ids): string => $ids->generate(prefix: 'usr', from: 'alice@example.com'),
        );
    }

    # sabotage: throw ErrorCode::RandWidthMismatch for a $rand in generate -> assertSame failed: ErrorCode RandWidthMismatch is not the same as RandWithMonotonic
    public function testCorpusRefusesRandWithMonotonic(): void
    {
        $this->assertRefused(
            ErrorCode::RandWithMonotonic,
            static fn(Monotonic $ids): string => $ids->generate(prefix: 'usr', size: 's', time: 1700000000000, rand: "\x01\x02"),
        );
    }

    # sabotage: refuse $from only when $rand is null (if ($from !== null && $rand === null)) -> assertSame failed: ErrorCode RandWithMonotonic is not the same as FromWithMonotonic
    public function testFromIsRefusedBeforeRandAndBeforeTheOtherArguments(): void
    {
        $this->assertRefused(
            ErrorCode::FromWithMonotonic,
            static fn(Monotonic $ids): string => $ids->generate(delimiter: '', rand: "\x01", from: 'alice@example.com'),
        );
        $this->assertRefused(
            ErrorCode::RandWithMonotonic,
            static fn(Monotonic $ids): string => $ids->generate(size: 'XL', delimiter: '', rand: "\x01"),
        );
    }

    # sabotage: key the state by the width alone ($key = (string) $width) -> assertSame failed: the draws were seed:2, step:2, step:2, not seed:2, seed:2, step:2
    public function testStateIsKeptPerPrefix(): void
    {
        $draws = new ReplayDraws([10, 20], [3]);
        $ids = new Monotonic($draws);

        $first = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::Small, time: 1700000000000));
        $other = self::attempt(static fn(): string => $ids->generate(prefix: 'cus', size: Size::Small, time: 1700000000000));
        $second = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::Small, time: 1700000000000));

        self::assertSame(['seed:2', 'seed:2', 'step:2'], $draws->calls);
        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x0a"), $first);
        self::assertSame(self::id('cus', Size::Small, false, 1700000000000, "\x00\x14"), $other);
        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x0d"), $second);
    }

    # sabotage: key the state by the prefix alone ($key = $prefix) -> assertSame failed: the draws were seed:2, step:4, step:4, not seed:2, seed:4, step:4
    public function testStateIsKeptPerRandomFieldWidth(): void
    {
        $draws = new ReplayDraws([10, 20], [3]);
        $ids = new Monotonic($draws);

        $small = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::Small, time: 1700000000000));
        $wider = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::Small, randSize: 4, time: 1700000000000));
        $next = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::Small, randSize: 4, time: 1700000000000));

        self::assertSame(['seed:2', 'seed:4', 'step:4'], $draws->calls);
        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x0a"), $small);
        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x00\x00\x14", 4), $wider);
        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x00\x00\x17", 4), $next);
    }

    # sabotage: make $state a static property (private static array $state, self::$state) -> assertSame failed: the second generator's id stepped the first one's seed
    public function testTwoGeneratorsKeepSeparateState(): void
    {
        $one = new Monotonic(new ReplayDraws([10], []));
        $two = new Monotonic(new ReplayDraws([20], [1]));

        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x0a"), $one->generate(prefix: 'usr', size: 's', time: 1700000000000));
        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x14"), $two->generate(prefix: 'usr', size: 's', time: 1700000000000));
    }

    # sabotage: mask the step to 4w + 4 bits (1 << ($bits + 4)) in step -> assertSame failed: the second id is not the one with the field 0x001010
    public function testDefaultDrawsMaskTheStepFromTheByteSource(): void
    {
        $counts = [];
        $bytes = ["\x00\x00\x10", "\xff\xff", "\xf0\x02"];
        $source = static function (int $count) use (&$counts, &$bytes): string {
            $counts[] = $count;

            return (string) array_shift($bytes);
        };
        $ids = new Monotonic();

        $first = $ids->generate(prefix: 'usr', size: Size::Small, compactTime: true, time: 1700000000000, byteSource: $source);
        $second = $ids->generate(prefix: 'usr', size: Size::Small, compactTime: true, time: 1700000000000, byteSource: $source);
        $third = $ids->generate(prefix: 'usr', size: Size::Small, compactTime: true, time: 1700000000000, byteSource: $source);

        // A 3-byte field steps by the low 12 bits of 2 bytes, plus one: 0xfff + 1, then 0x002 + 1.
        self::assertSame([3, 2, 2], $counts);
        self::assertSame(self::id('usr', Size::Small, true, 1700000000000, "\x00\x00\x10"), $first);
        self::assertSame(self::id('usr', Size::Small, true, 1700000000000, "\x00\x10\x10"), $second);
        self::assertSame(self::id('usr', Size::Small, true, 1700000000000, "\x00\x10\x13"), $third);
    }

    # sabotage: drop the + 1 from the masked step in step -> assertSame failed: the second id is not the one with the field 0x14
    public function testDefaultDrawsMaskAOneByteFieldToFourBits(): void
    {
        $bytes = ["\x10", "\xf3"];
        $ids = new Monotonic();
        $source = static function (int $count) use (&$bytes): string {
            return (string) array_shift($bytes);
        };

        $ids->generate(prefix: 'usr', size: Size::XSmall, time: 1700000000000, byteSource: $source);
        $next = $ids->generate(prefix: 'usr', size: Size::XSmall, time: 1700000000000, byteSource: $source);

        self::assertSame(self::id('usr', Size::XSmall, true, 1700000000000, "\x14"), $next);
    }

    # sabotage: always draw the seed from the byte source in seed (if (true)) -> assertSame failed: the second id is not the one with the field 0x0002
    public function testADrawSourceReplacesTheByteSource(): void
    {
        $ids = new Monotonic(new ReplayDraws([1], [1]));
        $calls = 0;
        $source = static function (int $count) use (&$calls): string {
            $calls++;

            return str_repeat("\x09", $count);
        };

        $ids->generate(prefix: 'usr', size: Size::Small, time: 1700000000000, byteSource: $source);
        $next = $ids->generate(prefix: 'usr', size: Size::Small, time: 1700000000000, byteSource: $source);

        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x02"), $next);
        self::assertSame(0, $calls);
    }

    # sabotage: store the state before Uxid::generate in generate -> assertSame failed: the third call threw UxidException rather than returning the id with the field 0xff
    public function testARefusedOverflowLeavesTheStateAsItWas(): void
    {
        $draws = new ReplayDraws([250, 9], [16, 5]);
        $ids = new Monotonic($draws);

        $first = $ids->generate(prefix: 'usr', size: Size::XSmall, time: self::LAST_COMPACT);

        try {
            $ids->generate(prefix: 'usr', size: Size::XSmall, time: self::LAST_COMPACT);
            self::fail('the overflow onto the marker was not refused');
        } catch (UxidException $refusal) {
            self::assertSame(ErrorCode::CompactTimeOutOfRange, $refusal->errorCode);
        }

        $next = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::XSmall, time: self::LAST_COMPACT));

        self::assertSame(['seed:1', 'step:1', 'seed:1', 'step:1'], $draws->calls);
        self::assertSame(self::id('usr', Size::XSmall, true, self::LAST_COMPACT, "\xfa"), $first);
        self::assertSame(self::id('usr', Size::XSmall, true, self::LAST_COMPACT, "\xff"), $next);
    }

    # sabotage: store the state before Uxid::generate in generate -> assertSame failed: the draws were seed:3, step:3, not seed:3, seed:3
    public function testARefusedFirstCallRecordsNoState(): void
    {
        $draws = new ReplayDraws([7, 8], []);
        $ids = new Monotonic($draws);

        try {
            $ids->generate(prefix: 'usr', size: Size::Small, compactTime: true, time: self::LAST_COMPACT + 1);
            self::fail('a compact time starting with the marker was not refused');
        } catch (UxidException $refusal) {
            self::assertSame(ErrorCode::CompactTimeOutOfRange, $refusal->errorCode);
        }

        $id = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::Small, compactTime: true, time: self::LAST_COMPACT - 7));

        self::assertSame(['seed:3', 'seed:3'], $draws->calls);
        self::assertSame(self::id('usr', Size::Small, true, self::LAST_COMPACT - 7, "\x00\x00\x08"), $id);
    }

    # sabotage: clear $this->state before the from_with_monotonic refusal in generate -> assertSame failed: the draws were seed:2, seed:2, not seed:2, step:2
    public function testARefusedArgumentLeavesTheStateAsItWas(): void
    {
        $draws = new ReplayDraws([10], [4]);
        $ids = new Monotonic($draws);
        $ids->generate(prefix: 'usr', size: Size::Small, time: 1700000000000);

        try {
            $ids->generate(prefix: 'usr', size: Size::Small, from: 'alice@example.com');
            self::fail('from was not refused');
        } catch (UxidException) {
        }

        try {
            $ids->generate(prefix: 'usr', size: Size::Small, time: -1);
            self::fail('a negative time was not refused');
        } catch (\InvalidArgumentException) {
        }

        $next = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::Small, time: 1700000000000));

        self::assertSame(['seed:2', 'step:2'], $draws->calls);
        self::assertSame(self::id('usr', Size::Small, false, 1700000000000, "\x00\x0e"), $next);
    }

    # sabotage: default $compactTime to false ($compactTime ?? false) in generate -> assertSame failed: the call threw InvalidArgumentException rather than returning usr_hf7yat00n3
    public function testXSmallDefaultsToCompactTime(): void
    {
        $ids = new Monotonic(new ReplayDraws([0xab], []));

        self::assertSame('usr_hf7yat00n3', self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: 'xs', time: 1700000000000)));
    }

    /**
     * @return array<string, array{Size, ?bool, ?int}>
     */
    public static function zeroWidths(): array
    {
        return [
            'xs with standard time' => [Size::XSmall, false, null],
            'randSize 0' => [Size::Large, null, 0],
        ];
    }

    # sabotage: skip the zero-width check in generate (if (false)) -> expectException failed: LogicException (no seed left to replay) does not match InvalidArgumentException (both data sets)
    #[DataProvider('zeroWidths')]
    public function testAZeroWidthFieldIsRefused(Size $size, ?bool $compactTime, ?int $randSize): void
    {
        $ids = new Monotonic(new ReplayDraws([], []));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a random field to step');

        $ids->generate(size: $size, randSize: $randSize, compactTime: $compactTime, time: 1700000000000);
    }

    # sabotage: ignore $randSize ($width = self::randBytes($size, $compact)) in generate -> assertSame failed: the call threw InvalidArgumentException rather than returning the id
    public function testAnExplicitWidthAtXSmallStandardTimeIsAllowed(): void
    {
        $ids = new Monotonic(new ReplayDraws([5], []));

        $id = self::attempt(static fn(): string => $ids->generate(prefix: 'usr', size: Size::XSmall, randSize: 1, compactTime: false, time: 1700000000000));

        self::assertSame(self::id('usr', Size::XSmall, false, 1700000000000, "\x05", 1), $id);
    }

    # sabotage: compare the width to 11 rather than self::MAX_RAND_BYTES in generate -> self::fail: the call threw LogicException rather than refusing with rand_size_too_large
    public function testARandSizeAboveTenIsRefused(): void
    {
        $this->assertRefused(
            ErrorCode::RandSizeTooLarge,
            static fn(Monotonic $ids): string => $ids->generate(randSize: 11, time: 1700000000000),
        );
    }

    # sabotage: skip the negative-width check in generate (if (false)) -> expectException failed: LogicException (no seed left to replay) does not match InvalidArgumentException
    public function testANegativeRandSizeIsRefused(): void
    {
        $ids = new Monotonic(new ReplayDraws([], []));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be negative');

        $ids->generate(randSize: -1, time: 1700000000000);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function outOfRangeTimes(): array
    {
        return [
            'negative' => [-1],
            'past 2^48 - 1' => [281474976710656],
        ];
    }

    # sabotage: drop the lower bound ($now < 0) of the time check in generate -> expectException failed: LogicException (no seed left to replay) does not match InvalidArgumentException (data set negative)
    #[DataProvider('outOfRangeTimes')]
    public function testATimeOutsideTheFieldIsRefused(int $time): void
    {
        $ids = new Monotonic(new ReplayDraws([], []));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The time must be from 0 to 2^48 - 1');

        $ids->generate(time: $time);
    }

    # sabotage: skip the last-millisecond check in generate (if (false)) -> expectException failed: LogicException (no seed left to replay) does not match OverflowException
    public function testAnOverflowInTheLastMillisecondIsRefused(): void
    {
        $ids = new Monotonic(new ReplayDraws([0xFFFF], [1]));
        $ids->generate(size: Size::Small, time: 281474976710655);

        $this->expectException(\OverflowException::class);

        $ids->generate(size: Size::Small, time: 281474976710655);
    }

    # sabotage: pass delimiter: '_' to Uxid::generate in generate -> assertSame failed: 'usr_01HF7YAT0004H1' is not identical to 'usr-01HF7YAT0004H1'
    public function testADelimiterAndCaseAreWrittenAsGenerateWritesThem(): void
    {
        $ids = new Monotonic(new ReplayDraws([0x0123], []));

        $id = $ids->generate(prefix: 'usr', size: 'small', case: LetterCase::Upper, delimiter: '-', time: 1700000000000);

        self::assertSame('usr-01HF7YAT0004H1', $id);
    }

    # sabotage: skip the empty-delimiter check in generate (if (false)) -> expectException failed: LogicException (no seed left to replay) does not match InvalidArgumentException
    public function testAnEmptyDelimiterIsRefused(): void
    {
        $ids = new Monotonic(new ReplayDraws([], []));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The delimiter must be a non-empty string.');

        $ids->generate(delimiter: '', time: 1700000000000);
    }

    /**
     * @return array<string, array{list<int>, list<int>, string}>
     */
    public static function badDraws(): array
    {
        return [
            'step 0' => [[1], [0], 'the step 0, outside 1 to 2^8'],
            'step past 2^(4w)' => [[1], [257], 'the step 257, outside 1 to 2^8'],
        ];
    }

    /**
     * @param list<int> $seeds
     * @param list<int> $steps
     */
    # sabotage: drop the lower bound ($step < 1) of the draw source's step check in step -> expectException failed: no UnexpectedValueException was thrown (data set step 0)
    #[DataProvider('badDraws')]
    public function testADrawSourceStepOutOfRangeIsRefused(array $seeds, array $steps, string $message): void
    {
        $ids = new Monotonic(new ReplayDraws($seeds, $steps));
        $ids->generate(size: Size::Small, time: 1700000000000);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $ids->generate(size: Size::Small, time: 1700000000000);
    }

    # sabotage: skip the seed length check in seed (if (false)) -> expectException failed: UxidException (rand_width_mismatch) does not match UnexpectedValueException
    public function testADrawSourceSeedOfTheWrongLengthIsRefused(): void
    {
        $draws = new class implements DrawSource {
            public function seed(int $width): string
            {
                return "\x00";
            }

            public function step(int $width): int
            {
                return 1;
            }
        };
        $ids = new Monotonic($draws);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('a seed of 1 bytes for a random field of 2');

        $ids->generate(size: Size::Small, time: 1700000000000);
    }

    # sabotage: skip the length check in draw (if (false)) -> expectException failed: UxidException (rand_width_mismatch) does not match UnexpectedValueException
    public function testAByteSourceOfTheWrongLengthIsRefused(): void
    {
        $ids = new Monotonic();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('returned 1 bytes when asked for 2');

        $ids->generate(size: Size::Small, time: 1700000000000, byteSource: static fn(int $count): string => "\x00");
    }

    /**
     * The closure is passed through reflection because its return type, an
     * int, is not the string the parameter documents: this is the caller's
     * untyped closure the check exists for.
     */
    # sabotage: skip the is_string check in draw (if (false)) -> expectException failed: TypeError from strlen does not match UnexpectedValueException
    public function testAByteSourceReturningANonStringIsRefused(): void
    {
        $generate = new \ReflectionMethod(Monotonic::class, 'generate');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('returned int when asked for 2 bytes');

        $generate->invokeArgs(new Monotonic(), ['size' => Size::Small, 'time' => 1700000000000, 'byteSource' => static fn(int $count): int => $count]);
    }

    # sabotage: default the time to 1700000000000 rather than the system clock in generate -> assertGreaterThanOrEqual failed: 1700000000000 is not at or after the clock read before the call
    public function testTheSystemClockAndRandomBytesAreTheDefaults(): void
    {
        $ids = new Monotonic();
        $before = (int) (new \DateTimeImmutable())->format('Uv');

        $first = $ids->generate(prefix: 'usr');
        $second = $ids->generate(prefix: 'usr');

        $after = (int) (new \DateTimeImmutable())->format('Uv');
        self::assertLessThan(0, strcmp($first, $second));
        foreach ([$first, $second] as $id) {
            $decoded = Uxid::decode($id);
            self::assertInstanceOf(Decoded::class, $decoded);
            self::assertSame(Size::XLarge, $decoded->size);
            self::assertGreaterThanOrEqual($before, $decoded->time);
            self::assertLessThanOrEqual($after + 1, $decoded->time);
        }
    }

    # sabotage: add a private static int property to Monotonic -> assertSame failed: the static properties are not the empty array
    public function testTheGeneratorKeepsNoStaticState(): void
    {
        $class = new \ReflectionClass(Monotonic::class);

        self::assertSame([], $class->getProperties(\ReflectionProperty::IS_STATIC));
        self::assertSame([], (new \ReflectionClass(Uxid::class))->getProperties(\ReflectionProperty::IS_STATIC));
    }

    /**
     * The id {@see Uxid::generate()} writes for fixed random bytes, the
     * reference the monotonic ids are compared to.
     */
    private static function id(string $prefix, Size $size, bool $compact, int $time, string $rand, ?int $randSize = null): string
    {
        return Uxid::generate(prefix: $prefix, size: $size, randSize: $randSize, compactTime: $compact, time: $time, rand: $rand);
    }

    /**
     * Asserts that a call on a fresh generator throws a UxidException with
     * the given error code.
     *
     * @param callable(Monotonic): string $call
     */
    private function assertRefused(ErrorCode $expected, callable $call): void
    {
        try {
            $call(new Monotonic(new ReplayDraws([], [])));
        } catch (UxidException $refusal) {
            self::assertSame($expected, $refusal->errorCode);

            return;
        } catch (\Throwable $thrown) {
            self::fail('the call threw ' . $thrown::class . ' rather than refusing with ' . $expected->value);
        }

        self::fail('the call was not refused with ' . $expected->value);
    }

    /**
     * Runs one call and answers the id it returned, or what it threw, so a
     * call that should succeed is compared as a value.
     *
     * @param callable(): string $call
     */
    private static function attempt(callable $call): string
    {
        try {
            return $call();
        } catch (\Throwable $thrown) {
            return 'threw ' . $thrown::class . ': ' . $thrown->getMessage();
        }
    }
}
