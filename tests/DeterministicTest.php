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
 * Uxid::generate with `from`: a hand-picked subset of the conformance
 * corpus's deterministic cases (uxid-ex, tag v2.10.0), copied as inputs and
 * expected strings, beside the behavior the corpus does not pin.
 */
#[CoversClass(Uxid::class)]
#[CoversClass(Size::class)]
#[CoversClass(UxidException::class)]
final class DeterministicTest extends TestCase
{
    /**
     * Deterministic cases from the corpus, by case id: prefix, from, size,
     * case and the expected id.
     *
     * @return array<string, array{?string, string, string, string, string}>
     */
    public static function corpusDeterministicCases(): array
    {
        return [
            'deterministic/golden-usr-xl' => ['usr', 'alice@example.com', 'xl', 'lower', 'usr_zcvt7epac0t1ebcsjfyf7cwz25'],
            'deterministic/golden-cus-s' => ['cus', 'acme-corp', 's', 'lower', 'cus_zt9vnyfd85bff0'],
            'deterministic/golden-no-prefix-m' => [null, 'alice@example.com', 'm', 'lower', 'zqz4cwzgyw8ks97869'],
            'deterministic/usr-xs' => ['usr', 'alice@example.com', 'xs', 'lower', 'usr_z1yhw20vag'],
            'deterministic/usr-s' => ['usr', 'alice@example.com', 's', 'lower', 'usr_z5rtsrkhx2h4dr'],
            'deterministic/usr-m' => ['usr', 'alice@example.com', 'm', 'lower', 'usr_zt8naccefxhwnrmscd'],
            'deterministic/usr-l' => ['usr', 'alice@example.com', 'l', 'lower', 'usr_zwbz6myvgz7ehe3nceap5n'],
            'deterministic/empty-prefix-xl' => ['', 'alice@example.com', 'xl', 'lower', 'z4krgegf6drn563pvqhya4tsx9'],
            'deterministic/usr-xl-upper' => ['usr', 'alice@example.com', 'xl', 'upper', 'usr_ZCVT7EPAC0T1EBCSJFYF7CWZ25'],
            'deterministic/non-ascii-from-m' => ['cus', "caf\u{e9}", 'm', 'lower', 'cus_zfsx9se75671f9kdjv'],
        ];
    }

    # sabotage: drop the count byte from the hash input in deterministic -> assertSame failed: 'usr_zaemdwqphe' is not identical to 'usr_z1yhw20vag' (deterministic/usr-xs, 10 data sets); also hash('sha1') in place of sha256 failed all 10 sets (usr-xs: 'usr_ze0qvhgf8k'), Small hashChars 12 in place of 13 failed usr-s and golden-cus-s ('usr_zbk41sev6wpnv' is not identical to 'usr_z5rtsrkhx2h4dr'), and the lower-case marker in upper failed deterministic/usr-xl-upper ('usr_zCVT7EPAC0T1EBCSJFYF7CWZ25' is not identical to 'usr_ZCVT7EPAC0T1EBCSJFYF7CWZ25')
    #[DataProvider('corpusDeterministicCases')]
    public function testCorpusDeterministicCase(?string $prefix, string $from, string $size, string $case, string $expected): void
    {
        $id = Uxid::generate(
            prefix: $prefix ?? '',
            size: Size::from($size),
            case: LetterCase::from($case),
            from: $from,
        );

        self::assertSame($expected, $id);
    }

    # sabotage: replace the rand-with-from check in generate with if (false) -> self::fail: generate did not refuse fixed random bytes with from
    public function testFixedRandWithFromIsRefused(): void
    {
        $rand = hex2bin('00010203040506070809');
        self::assertIsString($rand);

        try {
            Uxid::generate(prefix: 'usr', from: 'alice@example.com', rand: $rand);
        } catch (UxidException $refusal) {
            self::assertSame(ErrorCode::RandWithFrom, $refusal->errorCode);

            return;
        }

        self::fail('generate did not refuse fixed random bytes with from');
    }

    # sabotage: skip the rand-with-from refusal for the empty string in generate ($rand !== null && $rand !== '') -> self::fail: generate did not refuse an empty rand with from
    public function testAnEmptyRandWithFromIsRefusedToo(): void
    {
        try {
            Uxid::generate(prefix: 'usr', from: 'alice@example.com', rand: '');
        } catch (UxidException $refusal) {
            self::assertSame(ErrorCode::RandWithFrom, $refusal->errorCode);

            return;
        }

        self::fail('generate did not refuse an empty rand with from');
    }

    # sabotage: draw one byte from the byte source in the from path of generate -> assertSame failed: 1 is not identical to 0
    # sabotage: check the time range in generate above the from branch -> assertNull failed: generate refused the time with from ('The time must be from 0 to 2^48 - 1 milliseconds since the Unix epoch, got -1.' is not null)
    public function testTheTimeArgumentsAndTheByteSourceAreNotReadWithFrom(): void
    {
        $plain = Uxid::generate(prefix: 'usr', size: Size::Medium, from: 'alice@example.com');
        $calls = 0;
        $id = null;
        $refusal = null;

        try {
            $id = Uxid::generate(
                prefix: 'usr',
                size: Size::Medium,
                randSize: 11,
                compactTime: true,
                time: -1,
                byteSource: static function (int $count) use (&$calls): string {
                    $calls++;

                    return str_repeat("\x00", $count);
                },
                from: 'alice@example.com',
            );
        } catch (\InvalidArgumentException $thrown) {
            $refusal = $thrown->getMessage();
        }

        self::assertNull($refusal, 'generate refused the time with from');
        self::assertSame($plain, $id);
        self::assertSame(0, $calls);
    }

    # sabotage: join with a literal '_' instead of $delimiter in deterministic -> assertSame failed: 'usr_z1yhw20vag' is not identical to 'usr-z1yhw20vag'
    public function testACustomDelimiterJoinsPrefixAndBody(): void
    {
        $id = Uxid::generate(prefix: 'usr', size: Size::XSmall, delimiter: '-', from: 'alice@example.com');

        self::assertSame('usr-z1yhw20vag', $id);
    }

    # sabotage: treat an empty from as none in generate ($from !== null && $from !== '') -> assertSame failed: '0' is not identical to 'z'
    public function testAnEmptyFromIsAnInputLikeAnyOther(): void
    {
        $empty = Uxid::generate(size: Size::Small, from: '');
        $other = Uxid::generate(size: Size::Small, from: 'alice@example.com');

        self::assertSame(14, \strlen($empty));
        self::assertSame('z', $empty[0]);
        self::assertNotSame($other, $empty);
    }

    # sabotage: skip Size::parse when from is given in generate -> expectException failed: Failed asserting that exception of type "TypeError" matches expected exception "InvalidArgumentException"
    public function testAnUnknownSizeWithFromIsStillRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Uxid::generate(size: 'XL', from: 'alice@example.com');
    }

    # sabotage: skip the empty delimiter check when from is given in generate -> expectException failed: Failed asserting that exception of type "InvalidArgumentException" is thrown
    public function testAnEmptyDelimiterWithFromIsStillRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('delimiter');

        Uxid::generate(prefix: 'usr', delimiter: '', from: 'alice@example.com');
    }
}
