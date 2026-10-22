<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
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
 * Proves the corpus runner is strict: a case carrying a key it does not
 * know, lacking one it needs, or giving a wrong answer fails rather than
 * passing, and a null or empty prefix both read as no prefix.
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
final class CorpusStrictnessTest extends TestCase
{
    /**
     * A passing encode case, with the given changes to it and to its input.
     *
     * @param array<string, mixed> $top   keys set on the case (a null value removes the key)
     * @param array<string, mixed> $input keys set on its input (a null value removes the key)
     */
    private static function encodeLine(array $top = [], array $input = []): string
    {
        $case = [
            'id' => 'encode/strict',
            'expected' => 'usr_01hf7yat00',
            'input' => ['prefix' => 'usr', 'size' => 'xs', 'compact' => false, 'case' => 'lower', 'time' => 1700000000000, 'rand' => ''],
        ];

        return self::line(array_merge($case, $top), $input);
    }

    /**
     * Encodes a case with keys merged into its input.
     *
     * @param array<string, mixed> $case
     * @param array<string, mixed> $input
     */
    private static function line(array $case, array $input): string
    {
        $merged = \is_array($case['input'] ?? null) ? array_merge($case['input'], $input) : [];
        $case['input'] = array_filter($merged, static fn(mixed $value): bool => $value !== '__remove__');
        foreach ($case as $key => $value) {
            if ($value === '__remove__') {
                unset($case[$key]);
            }
        }

        return json_encode($case, JSON_THROW_ON_ERROR);
    }

    /** The failure message of a runner call, which has to fail. */
    private function failure(\Closure $run): string
    {
        try {
            $run();
        } catch (AssertionFailedError $failure) {
            return $failure->getMessage();
        }

        self::fail('The runner accepted the case');
    }

    /** The baseline case passes, so each failure below comes from its one change. */
    # sabotage: CorpusCases::id returns a fixed string -> the assertSame of the case id failed
    public function testTheBaselineCasePasses(): void
    {
        CorpusCases::encode(self::encodeLine());

        self::assertSame('encode/strict', CorpusCases::id(self::encodeLine()));
    }

    /** An unknown key at the top of a case fails and is named. */
    # sabotage: CaseFields::done never fails -> the runner accepted the case and self::fail failed
    public function testAnUnknownKeyAtTheTopOfACaseFails(): void
    {
        $message = $this->failure(static fn() => CorpusCases::encode(self::encodeLine(['surprise_top' => 1])));

        self::assertStringContainsString('surprise_top', $message);
        self::assertStringContainsString('encode/strict', $message);
    }

    /** An unknown key inside an input fails and is named. */
    # sabotage: CaseFields::done never fails -> the runner accepted the case and self::fail failed
    public function testAnUnknownKeyInsideAnInputFails(): void
    {
        $message = $this->failure(static fn() => CorpusCases::encode(self::encodeLine([], ['surprise_input' => true])));

        self::assertStringContainsString('surprise_input', $message);
    }

    /** An unknown key inside a generate request of an invalid case fails and is named. */
    # sabotage: CaseFields::done never fails -> the runner accepted the case and self::fail failed
    public function testAnUnknownKeyInsideAGenerateRequestFails(): void
    {
        $line = json_encode([
            'id' => 'invalid/strict',
            'expected_error' => 'rand_size_too_large',
            'input' => ['generate' => ['prefix' => 'usr', 'rand_size' => 11, 'surprise_option' => 1]],
        ], JSON_THROW_ON_ERROR);

        $message = $this->failure(static fn() => CorpusCases::invalid($line));

        self::assertStringContainsString('surprise_option', $message);
    }

    /** An unknown key inside an expected decode object fails and is named. */
    # sabotage: CaseFields::done never fails -> the runner accepted the case and self::fail failed
    public function testAnUnknownKeyInsideAnExpectedObjectFails(): void
    {
        $line = json_encode([
            'id' => 'decode/strict',
            'input' => 'usr_01hf7yat0004hmasw9nf6yy093',
            'expected' => [
                'prefix' => 'usr', 'size' => 'xl', 'compact' => false, 'deterministic' => false,
                'time' => 1700000000000, 'surprise_expected' => 1,
            ],
        ], JSON_THROW_ON_ERROR);

        $message = $this->failure(static fn() => CorpusCases::decode($line));

        self::assertStringContainsString('surprise_expected', $message);
    }

    /** A missing key fails and is named. */
    # sabotage: CaseFields reads a missing key as null -> the assertStringContainsString of has no time failed
    public function testAMissingKeyFails(): void
    {
        $message = $this->failure(static fn() => CorpusCases::encode(self::encodeLine([], ['time' => '__remove__'])));
        self::assertStringContainsString('has no time', $message);

        $message = $this->failure(static fn() => CorpusCases::encode(self::encodeLine(['expected' => '__remove__'])));
        self::assertStringContainsString('has no expected', $message);
    }

    /** A key of the wrong type fails. */
    # sabotage: CaseFields::int casts the value to an integer before the type check -> the runner accepted the case and self::fail failed
    public function testAKeyOfTheWrongTypeFails(): void
    {
        $message = $this->failure(static fn() => CorpusCases::encode(self::encodeLine([], ['time' => '1700000000000'])));

        self::assertStringContainsString('time that is not an integer', $message);
    }

    /** A null prefix and an empty prefix both read as no prefix. */
    # sabotage: a null encode prefix read as the string null -> the encode assertSame failed for the null prefix
    public function testNullAndEmptyPrefixesAreNoPrefix(): void
    {
        CorpusCases::encode(self::encodeLine(['expected' => '01hf7yat00'], ['prefix' => null]));
        CorpusCases::encode(self::encodeLine(['expected' => '01hf7yat00'], ['prefix' => '']));

        $this->addToAssertionCount(1);
    }

    /** A wrong prefix fails rather than being read as no prefix. */
    # sabotage: the encode comparison made against the expected id itself -> the runner accepted the case and self::fail failed
    public function testAWrongPrefixFails(): void
    {
        $message = $this->failure(static fn() => CorpusCases::encode(self::encodeLine([], ['prefix' => 'cus'])));

        self::assertStringContainsString('encode/strict', $message);
    }

    /** A wrong expected answer fails in every case kind. */
    # sabotage: the encode comparison made against the expected id itself -> the runner accepted the encode case and self::fail failed
    public function testAWrongExpectedAnswerFailsInEveryKind(): void
    {
        $deterministic = json_encode([
            'id' => 'deterministic/strict',
            'expected' => 'usr_zwrong',
            'input' => ['case' => 'lower', 'from' => 'alice@example.com', 'prefix' => 'usr', 'size' => 'xl'],
        ], JSON_THROW_ON_ERROR);
        $decode = json_encode([
            'id' => 'decode/strict',
            'input' => 'usr_01hf7yat0004hmasw9nf6yy093',
            'expected' => [
                'prefix' => 'cus', 'size' => 'xl', 'compact' => false, 'deterministic' => false, 'time' => 1700000000000,
            ],
        ], JSON_THROW_ON_ERROR);
        $invalid = json_encode([
            'id' => 'invalid/strict-decode',
            'input' => ['decode' => ''],
            'expected_error' => 'invalid_length',
        ], JSON_THROW_ON_ERROR);
        $monotonic = json_encode([
            'id' => 'monotonic/strict',
            'expected' => ['usr_hf7yat0000000000000000'],
            'input' => [
                'compact' => true, 'initial_seed' => 0, 'overflow_seed' => null, 'prefix' => 'usr',
                'size' => 'l', 'steps' => [], 'times' => [1700000000000],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->failure(static fn() => CorpusCases::deterministic($deterministic));
        $this->failure(static fn() => CorpusCases::decode($decode));
        $this->failure(static fn() => CorpusCases::invalid($invalid));
        $this->failure(static fn() => CorpusCases::monotonic($monotonic));
        $this->failure(static fn() => CorpusCases::encode(self::encodeLine(['expected' => 'usr_01hf7yat01'])));
    }

    /** A generate request that is not refused fails the case. */
    # sabotage: the not-refused failure dropped from CorpusCases::refused -> the runner accepted the case and self::fail failed
    public function testAnAcceptedGenerateRequestFails(): void
    {
        $line = json_encode([
            'id' => 'invalid/strict-accepted',
            'expected_error' => 'rand_size_too_large',
            'input' => ['generate' => ['prefix' => 'usr', 'size' => 'xs', 'time' => 1700000000000]],
        ], JSON_THROW_ON_ERROR);

        $message = $this->failure(static fn() => CorpusCases::invalid($line));

        self::assertStringContainsString('not refused', $message);
    }

    /** A generate request refused with a different error fails the case. */
    # sabotage: the error name comparison dropped from CorpusCases::refused -> the runner accepted the case and self::fail failed
    public function testAGenerateRequestRefusedWithAnotherErrorFails(): void
    {
        $line = json_encode([
            'id' => 'invalid/strict-other-error',
            'expected_error' => 'rand_width_mismatch',
            'input' => ['generate' => ['prefix' => 'usr', 'rand_size' => 11, 'size' => 'xl', 'time' => 1700000000000]],
        ], JSON_THROW_ON_ERROR);

        $this->failure(static fn() => CorpusCases::invalid($line));
        $this->addToAssertionCount(1);
    }

    /** An invalid input holding both decode and generate, or neither, fails. */
    # sabotage: the exactly-one check dropped from CorpusCases::invalid -> the assertStringContainsString of exactly one failed
    public function testAnInvalidInputNeedsExactlyOneOfDecodeOrGenerate(): void
    {
        $both = json_encode([
            'id' => 'invalid/strict-both',
            'expected_error' => 'empty',
            'input' => ['decode' => '', 'generate' => []],
        ], JSON_THROW_ON_ERROR);
        $neither = json_encode([
            'id' => 'invalid/strict-neither',
            'expected_error' => 'empty',
            'input' => [],
        ], JSON_THROW_ON_ERROR);

        self::assertStringContainsString('exactly one', $this->failure(static fn() => CorpusCases::invalid($both)));
        self::assertStringContainsString('exactly one', $this->failure(static fn() => CorpusCases::invalid($neither)));
    }

    /** A monotonic case that lists draws the generator never makes fails. */
    # sabotage: the replayed-draw count assertion dropped from CorpusCases::monotonic -> the runner accepted the case and self::fail failed
    public function testAMonotonicCaseWithUnconsumedDrawsFails(): void
    {
        $line = json_encode([
            'id' => 'monotonic/strict-spare-step',
            'expected' => ['usr_hf7yat000000000000000'],
            'input' => [
                'compact' => true, 'initial_seed' => 0, 'overflow_seed' => null, 'prefix' => 'usr',
                'size' => 'l', 'steps' => [5], 'times' => [1700000000000],
            ],
        ], JSON_THROW_ON_ERROR);

        $message = $this->failure(static fn() => CorpusCases::monotonic($line));

        self::assertStringContainsString('draws replayed', $message);
    }
}
