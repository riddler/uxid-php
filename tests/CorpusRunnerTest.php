<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Riddler\Uxid\Decoded;
use Riddler\Uxid\DecodeError;
use Riddler\Uxid\ErrorCode;
use Riddler\Uxid\Invalid;
use Riddler\Uxid\LetterCase;
use Riddler\Uxid\Monotonic;
use Riddler\Uxid\Size;
use Riddler\Uxid\Uxid;
use Riddler\Uxid\UxidException;

/**
 * Runs every case of the vendored conformance corpus, one test per case
 * kind, each fed every line of its file by a data provider keyed by case id.
 * There is no skip list and no filter.
 */
#[Group('corpus')]
#[CoversClass(Uxid::class)]
#[CoversClass(Monotonic::class)]
#[CoversClass(Decoded::class)]
#[CoversClass(Invalid::class)]
#[CoversClass(UxidException::class)]
#[CoversClass(Size::class)]
#[CoversClass(LetterCase::class)]
#[CoversClass(ErrorCode::class)]
#[CoversClass(DecodeError::class)]
final class CorpusRunnerTest extends TestCase
{
    /**
     * Every line of one case file, keyed by case id.
     *
     * @return iterable<string, array{string}>
     */
    private static function cases(string $file): iterable
    {
        foreach (Corpus::lines($file) as $line) {
            yield CorpusCases::id($line) => [$line];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function encodeCases(): iterable
    {
        return self::cases(Corpus::FILES['encode']);
    }

    /** @return iterable<string, array{string}> */
    public static function deterministicCases(): iterable
    {
        return self::cases(Corpus::FILES['deterministic']);
    }

    /** @return iterable<string, array{string}> */
    public static function decodeCases(): iterable
    {
        return self::cases(Corpus::FILES['decode']);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCases(): iterable
    {
        return self::cases(Corpus::FILES['invalid']);
    }

    /** @return iterable<string, array{string}> */
    public static function monotonicCases(): iterable
    {
        return self::cases(Corpus::FILES['monotonic']);
    }

    /** An encode case builds the expected id. */
    # sabotage: Uxid::generate always writes the lower-case alphabet -> the assertSame failed for every upper-case encode case
    #[DataProvider('encodeCases')]
    public function testEncodeCase(string $line): void
    {
        CorpusCases::encode($line);
    }

    /** A deterministic case builds the expected id. */
    # sabotage: Uxid::generate always writes the lower-case alphabet -> the assertSame failed for deterministic/usr-xl-upper
    #[DataProvider('deterministicCases')]
    public function testDeterministicCase(string $line): void
    {
        CorpusCases::deterministic($line);
    }

    /** A decode case reads the expected parts. */
    # sabotage: Uxid::decode returns the compact flag negated -> the compact assertSame failed for every time-based decode case
    #[DataProvider('decodeCases')]
    public function testDecodeCase(string $line): void
    {
        CorpusCases::decode($line);
    }

    /** An invalid case is refused with the expected error. */
    # sabotage: Uxid::decode refuses an empty body as invalid_length -> the assertSame of the reason failed for the four empty cases
    #[DataProvider('invalidCases')]
    public function testInvalidCase(string $line): void
    {
        CorpusCases::invalid($line);
    }

    /** A monotonic case emits the expected ids from the replayed draws. */
    # sabotage: Monotonic adds the step plus one -> the assertSame of the ids failed for every case with a step
    #[DataProvider('monotonicCases')]
    public function testMonotonicCase(string $line): void
    {
        CorpusCases::monotonic($line);
    }
}
