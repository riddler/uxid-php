<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Riddler\Uxid\ErrorCode;
use Riddler\Uxid\LetterCase;
use Riddler\Uxid\Size;
use Riddler\Uxid\Uxid;
use Riddler\Uxid\UxidException;

/**
 * Uxid::generate: a hand-picked subset of the conformance corpus's encode
 * and generation-refusal cases (uxid-ex, tag v2.10.0), copied as inputs and
 * expected strings, beside the behavior the corpus does not pin.
 */
#[CoversClass(Uxid::class)]
#[CoversClass(Size::class)]
#[CoversClass(UxidException::class)]
final class GenerateTest extends TestCase
{
    /**
     * Encode cases from the corpus, by case id: prefix, size, compact,
     * case, time, rand (hex) and the expected id.
     *
     * @return array<string, array{?string, string, bool, string, int, string, string}>
     */
    public static function corpusEncodeCases(): array
    {
        return [
            'encode/xs-standard-lower' => ['usr', 'xs', false, 'lower', 1700000000000, '', 'usr_01hf7yat00'],
            'encode/xs-compact-upper' => ['usr', 'xs', true, 'upper', 1700000000000, '01', 'usr_HF7YAT0001'],
            'encode/s-standard-lower' => ['usr', 's', false, 'lower', 1700000000000, '0123', 'usr_01hf7yat0004h1'],
            'encode/s-compact-lower' => ['usr', 's', true, 'lower', 1700000000000, '012345', 'usr_hf7yat0004hm5'],
            'encode/m-standard-upper' => ['usr', 'm', false, 'upper', 1700000000000, '0123456789', 'usr_01HF7YAT0004HMASW9'],
            'encode/m-compact-lower' => ['usr', 'm', true, 'lower', 1700000000000, '0123456789ab', 'usr_hf7yat0004hmasw9n3'],
            'encode/l-standard-lower' => ['usr', 'l', false, 'lower', 1700000000000, '0123456789abcd', 'usr_01hf7yat0004hmasw9nf61'],
            'encode/l-compact-upper' => ['usr', 'l', true, 'upper', 1700000000000, '0123456789abcdef', 'usr_HF7YAT0004HMASW9NF6YF'],
            'encode/xl-standard-lower' => ['usr', 'xl', false, 'lower', 1700000000000, '0123456789abcdef0123', 'usr_01hf7yat0004hmasw9nf6yy093'],
            'encode/xl-compact-lower' => ['usr', 'xl', true, 'lower', 1700000000000, '0123456789abcdef0123', 'usr_hf7yat0004hmasw9nf6yy093'],
            'encode/xs-compact-rand-all-ones' => ['cus', 'xs', true, 'lower', 1700000000000, 'ff', 'cus_hf7yat00z7'],
            'encode/s-standard-rand-all-ones' => ['cus', 's', false, 'lower', 1700000000000, 'ffff', 'cus_01hf7yat00zzz1'],
            'encode/l-compact-rand-all-ones' => ['cus', 'l', true, 'lower', 1700000000000, 'ffffffffffffffff', 'cus_hf7yat00zzzzzzzzzzzzf'],
            'encode/xs-standard-time-zero' => [null, 'xs', false, 'lower', 0, '', '0000000000'],
            'encode/xs-standard-time-max' => [null, 'xs', false, 'lower', 281474976710655, '', '7zzzzzzzzz'],
            'encode/s-compact-guard-last-valid' => ['usr', 's', true, 'lower', 1065151889407, '012345', 'usr_yzzzzzzz04hm5'],
            'encode/xl-standard-no-prefix' => [null, 'xl', false, 'lower', 1700000000000, '0123456789abcdef0123', '01hf7yat0004hmasw9nf6yy093'],
            'encode/xl-standard-empty-prefix' => ['', 'xl', false, 'lower', 1700000000000, '0123456789abcdef0123', '01hf7yat0004hmasw9nf6yy093'],
            'encode/s-standard-prefix-with-delimiter' => ['a_b', 's', false, 'lower', 1700000000000, '0123', 'a_b_01hf7yat0004h1'],
        ];
    }

    # sabotage: write a short last group shifted up to 5 bits in encodeBytes ($alphabet[$buffer << (5 - $bits)]) -> assertSame failed: 'usr_hf7yat0004hma' is not identical to 'usr_hf7yat0004hm5' (encode/s-compact-lower)
    #[DataProvider('corpusEncodeCases')]
    public function testCorpusEncodeCase(
        ?string $prefix,
        string $size,
        bool $compact,
        string $case,
        int $time,
        string $rand,
        string $expected,
    ): void {
        $bytes = hex2bin($rand);
        self::assertIsString($bytes);

        $id = Uxid::generate(
            prefix: $prefix ?? '',
            size: Size::from($size),
            compactTime: $compact,
            case: LetterCase::from($case),
            time: $time,
            rand: $bytes,
        );

        self::assertSame($expected, $id);
    }

    /**
     * Generation refusals from the corpus within this method's options, by
     * case id: the options and the expected error name.
     *
     * @return array<string, array{array{size: string, compact?: bool, time: int, rand?: string, rand_size?: int}, string}>
     */
    public static function corpusRefusalCases(): array
    {
        return [
            'invalid/generate-compact-time-at-guard' => [['size' => 's', 'compact' => true, 'time' => 2164663517184, 'rand' => '010203'], 'compact_time_out_of_range'],
            'invalid/generate-compact-time-at-guard-previous-window' => [['size' => 's', 'compact' => true, 'time' => 1065151889408, 'rand' => '010203'], 'compact_time_out_of_range'],
            'invalid/generate-rand-too-wide' => [['size' => 's', 'time' => 1700000000000, 'rand' => '010203'], 'rand_width_mismatch'],
            'invalid/generate-rand-too-narrow' => [['size' => 'xl', 'time' => 1700000000000, 'rand' => '010203040506070809'], 'rand_width_mismatch'],
            'invalid/generate-rand-on-xs-standard' => [['size' => 'xs', 'time' => 1700000000000, 'rand' => '01'], 'rand_width_mismatch'],
            'invalid/generate-rand-size-11' => [['size' => 'xl', 'time' => 1700000000000, 'rand_size' => 11], 'rand_size_too_large'],
        ];
    }

    /**
     * @param array{size: string, compact?: bool, time: int, rand?: string, rand_size?: int} $options
     */
    # sabotage: compare the compact time's top group to self::MARKER - 1 in generate -> self::fail: generate did not refuse the request with compact_time_out_of_range (both compact-time-at-guard cases)
    #[DataProvider('corpusRefusalCases')]
    public function testCorpusRefusalCase(array $options, string $expectedError): void
    {
        $rand = isset($options['rand']) ? hex2bin($options['rand']) : null;
        self::assertNotFalse($rand);

        try {
            Uxid::generate(
                prefix: 'usr',
                size: $options['size'],
                randSize: $options['rand_size'] ?? null,
                compactTime: $options['compact'] ?? false,
                time: $options['time'],
                rand: $rand,
                byteSource: static fn(int $count): string => str_repeat("\x00", $count),
            );
        } catch (UxidException $refusal) {
            self::assertSame($expectedError, $refusal->errorCode->value);

            return;
        }

        self::fail('generate did not refuse the request with ' . $expectedError);
    }

    # sabotage: change the default $size of generate to Size::Large -> assertSame failed: 22 is not identical to 26
    public function testDefaultsAreXLargeStandardLowerCaseWithNoPrefix(): void
    {
        $id = Uxid::generate(time: 1700000000000, byteSource: static fn(int $count): string => str_repeat("\xff", $count));

        self::assertSame(26, \strlen($id));
        self::assertSame('01hf7yat00zzzzzzzzzzzzzzzz', $id);
    }

    # sabotage: join the prefix and the body without the delimiter in generate -> assertSame failed: 'usr01hf7yat0004h1' is not identical to 'usrzz01hf7yat0004h1'
    public function testDelimiterJoinsPrefixAndBody(): void
    {
        $id = Uxid::generate(prefix: 'usr', size: Size::Small, delimiter: 'zz', time: 1700000000000, rand: "\x01\x23");

        self::assertSame('usrzz01hf7yat0004h1', $id);
    }

    /**
     * @return array<string, array{string, Size}>
     */
    public static function spellings(): array
    {
        return [
            'xs' => ['xs', Size::XSmall],
            'xsmall' => ['xsmall', Size::XSmall],
            's' => ['s', Size::Small],
            'small' => ['small', Size::Small],
            'm' => ['m', Size::Medium],
            'medium' => ['medium', Size::Medium],
            'l' => ['l', Size::Large],
            'large' => ['large', Size::Large],
            'xl' => ['xl', Size::XLarge],
            'xlarge' => ['xlarge', Size::XLarge],
        ];
    }

    # sabotage: map 'large' to self::XLarge in Size::parse -> assertSame failed: Size XLarge is not the same as Size Large (data set large)
    #[DataProvider('spellings')]
    public function testSizeParsesShortAndLongSpellings(string $spelling, Size $expected): void
    {
        self::assertSame($expected, Size::parse($spelling));
    }

    # sabotage: replace Size::parse($size) with Size::XLarge in generate -> assertSame failed: 26 is not identical to 22
    public function testGenerateTakesALongSpelling(): void
    {
        $id = Uxid::generate(size: 'large', time: 1700000000000, byteSource: static fn(int $count): string => str_repeat("\x00", $count));

        self::assertSame(22, \strlen($id));
    }

    # sabotage: add 'XL' as a spelling of self::XLarge in Size::parse -> expectException failed: no InvalidArgumentException was thrown
    public function testSizeRefusesAnUnknownSpelling(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Size::parse('XL');
    }

    # sabotage: keep the size's width when $randSize is given ($width = $width) in generate -> assertSame failed: 'cus_01hf7yat00zzzzzzzzzzzzzzzz' is not identical to 'cus_01hf7yat00zzzzzz3'
    public function testRandSizeSetsTheRandomFieldWidth(): void
    {
        $counts = [];
        $id = Uxid::generate(
            prefix: 'cus',
            randSize: 4,
            time: 1700000000000,
            byteSource: static function (int $count) use (&$counts): string {
                $counts[] = $count;

                return str_repeat("\xff", $count);
            },
        );

        self::assertSame('cus_01hf7yat00zzzzzz3', $id);
        self::assertSame([4], $counts);
    }

    # sabotage: delete the empty-field return at the top of draw -> assertSame failed: [0] is not identical to []
    public function testAnEmptyRandomFieldDrawsNothing(): void
    {
        $counts = [];
        $id = Uxid::generate(
            size: Size::XSmall,
            time: 1700000000000,
            byteSource: static function (int $count) use (&$counts): string {
                $counts[] = $count;

                return '';
            },
        );

        self::assertSame('01hf7yat00', $id);
        self::assertSame([], $counts);
    }

    # sabotage: replace the length check in draw with if (false) -> expectException failed: no UnexpectedValueException was thrown
    public function testAByteSourceOfTheWrongLengthIsRefused(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        Uxid::generate(time: 1700000000000, byteSource: static fn(int $count): string => 'short');
    }

    /**
     * The closure is passed through reflection because its return type, an
     * int, is not the string the parameter documents: this is the caller's
     * untyped closure the check exists for.
     */
    # sabotage: replace the is_string check in draw with if (false) -> expectException failed: TypeError from strlen does not match the expected UnexpectedValueException
    public function testAByteSourceReturningANonStringIsRefused(): void
    {
        $generate = new \ReflectionMethod(Uxid::class, 'generate');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('must return a string');

        $generate->invokeArgs(null, ['time' => 1700000000000, 'byteSource' => static fn(int $count): int => $count]);
    }

    # sabotage: replace the $randSize < 0 check in generate with if (false) -> expectException failed: no InvalidArgumentException was thrown
    public function testANegativeRandSizeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be negative');

        Uxid::generate(randSize: -1, time: 1700000000000, byteSource: static fn(int $count): string => str_repeat("\x00", $count));
    }

    # sabotage: replace the empty delimiter check in generate with if (false) -> expectException failed: no InvalidArgumentException was thrown
    public function testAnEmptyDelimiterIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('delimiter');

        Uxid::generate(prefix: 'usr', delimiter: '', time: 1700000000000);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function timesOutOfRange(): array
    {
        return [
            'negative' => [-1],
            'past 2^48 - 1' => [281474976710656],
        ];
    }

    # sabotage: drop the upper bound ($time > self::MAX_TIME) of the time check in generate -> expectException failed: no InvalidArgumentException was thrown (data set past 2^48 - 1)
    #[DataProvider('timesOutOfRange')]
    public function testATimeOutsideFortyEightBitsIsRefused(int $time): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2^48 - 1');

        Uxid::generate(size: Size::XSmall, time: $time);
    }

    # sabotage: return 0 from now() -> assertGreaterThanOrEqual failed: 0 is not greater than or equal to the clock read before the call
    public function testTheDefaultTimeIsTheSystemClock(): void
    {
        $before = (int) floor(microtime(true) * 1000);
        $id = Uxid::generate(size: Size::XSmall);
        $after = (int) ceil(microtime(true) * 1000);

        $time = 0;
        foreach (str_split($id) as $symbol) {
            $value = strpos('0123456789abcdefghjkmnpqrstvwxyz', $symbol);
            self::assertIsInt($value);
            $time = ($time << 5) | $value;
        }

        self::assertGreaterThanOrEqual($before, $time);
        self::assertLessThanOrEqual($after, $time);
    }

    # sabotage: replace random_bytes($count) with str_repeat("\x00", $count) in draw -> assertNotSame failed: the two ids were identical
    public function testTheDefaultByteSourceIsRandom(): void
    {
        $first = Uxid::generate(time: 1700000000000);
        $second = Uxid::generate(time: 1700000000000);

        self::assertSame(26, \strlen($first));
        self::assertNotSame($first, $second);
    }

    # sabotage: throw the rand_size_too_large refusal with ErrorCode::RandWidthMismatch in generate -> assertSame failed: ErrorCode RandWidthMismatch is not the same as RandSizeTooLarge
    public function testARefusalIsAnInvalidArgumentExceptionCarryingItsCode(): void
    {
        try {
            Uxid::generate(randSize: 11, time: 1700000000000);
        } catch (\InvalidArgumentException $refusal) {
            self::assertInstanceOf(UxidException::class, $refusal);
            self::assertSame(ErrorCode::RandSizeTooLarge, $refusal->errorCode);
            self::assertSame(0, $refusal->getCode());

            return;
        }

        self::fail('generate did not refuse a random field width of 11 bytes');
    }

    # sabotage: compare $intSize to 4 instead of 8 in requireSixtyFourBit -> expectException failed: no LogicException was thrown
    public function testANarrowerThanSixtyFourBitBuildIsRefused(): void
    {
        $check = new \ReflectionMethod(Uxid::class, 'requireSixtyFourBit');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('64-bit');

        $check->invoke(null, 4);
    }

    # sabotage: make the constructor of Uxid public -> assertTrue failed: false is not true (isPrivate)
    public function testUxidCannotBeInstantiated(): void
    {
        $constructor = new \ReflectionMethod(Uxid::class, '__construct');

        self::assertTrue($constructor->isPrivate());
        self::assertTrue((new \ReflectionClass(Uxid::class))->isFinal());
    }
}
