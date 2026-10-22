<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Assert;
use Riddler\Uxid\Decoded;
use Riddler\Uxid\Invalid;
use Riddler\Uxid\LetterCase;
use Riddler\Uxid\Monotonic;
use Riddler\Uxid\Size;
use Riddler\Uxid\Uxid;
use Riddler\Uxid\UxidException;

/**
 * Runs one conformance case, as the corpus README's "Running the cases"
 * describes, one static method per case kind, each taking a raw case line.
 *
 * Every object a method reads goes through {@see CaseFields} and ends with
 * done(), so a case carrying a key this runner does not know fails.
 *
 * How the corpus fields map onto this port's API:
 *
 * - the corpus field `compact` is the `compactTime` argument of
 *   {@see Uxid::generate()} and {@see Monotonic::generate()}, and the
 *   `compactTime` property of {@see Decoded};
 * - a `prefix` of null or "" is no prefix, which is the empty string here;
 * - `rand` is lowercase hex and is decoded to the bytes the `rand` argument
 *   takes;
 * - `rand_size` is the `randSize` argument;
 * - `size` and `case` are the values of {@see Size} and {@see LetterCase};
 * - the delimiter is `_`, the default, in every case.
 */
final class CorpusCases
{
    /** A decoder clock inside the window the corpus README gives for its compact decode cases. */
    private const int DECODER_CLOCK = 1700000000000;

    /** The id of a case line. */
    public static function id(string $line): string
    {
        return CaseFields::fromLine($line)->string('id');
    }

    /** Runs one encode case: the id built from the input equals the expected one. */
    public static function encode(string $line): void
    {
        $case = CaseFields::fromLine($line);
        $id = $case->string('id');
        $input = $case->object('input');
        $prefix = $input->nullableString('prefix') ?? '';
        $size = Size::from($input->string('size'));
        $compact = $input->bool('compact');
        $letters = LetterCase::from($input->string('case'));
        $time = $input->int('time');
        $rand = self::bytes($input->string('rand'), $id);
        $input->done();
        $expected = $case->string('expected');
        $case->done();

        Assert::assertSame($expected, Uxid::generate(
            prefix: $prefix,
            size: $size,
            compactTime: $compact,
            case: $letters,
            time: $time,
            rand: $rand,
        ), $id);
    }

    /** Runs one deterministic case: the id derived from the input equals the expected one. */
    public static function deterministic(string $line): void
    {
        $case = CaseFields::fromLine($line);
        $id = $case->string('id');
        $input = $case->object('input');
        $prefix = $input->nullableString('prefix') ?? '';
        $from = $input->string('from');
        $size = Size::from($input->string('size'));
        $letters = LetterCase::from($input->string('case'));
        $input->done();
        $expected = $case->string('expected');
        $case->done();

        Assert::assertSame($expected, Uxid::generate(
            prefix: $prefix,
            size: $size,
            case: $letters,
            from: $from,
        ), $id);
    }

    /** Runs one decode case: the decoded parts equal the expected ones. */
    public static function decode(string $line): void
    {
        $case = CaseFields::fromLine($line);
        $id = $case->string('id');
        $input = $case->string('input');
        $expected = $case->object('expected');
        $prefix = $expected->nullableString('prefix');
        $size = $expected->string('size');
        $compact = $expected->bool('compact');
        $deterministic = $expected->bool('deterministic');
        $time = $expected->nullableInt('time');
        $expected->done();
        $case->done();

        $decoded = Uxid::decode($input, clock: static fn(): int => self::DECODER_CLOCK);

        Assert::assertInstanceOf(Decoded::class, $decoded, $id);
        Assert::assertSame($prefix, $decoded->prefix, $id . ' prefix');
        Assert::assertSame($size, $decoded->size->value, $id . ' size');
        Assert::assertSame($compact, $decoded->compactTime, $id . ' compact');
        Assert::assertSame($deterministic, $decoded->deterministic, $id . ' deterministic');
        Assert::assertSame($time, $decoded->time, $id . ' time');
    }

    /** Runs one invalid case: the decode or the generate request is refused with the expected error. */
    public static function invalid(string $line): void
    {
        $case = CaseFields::fromLine($line);
        $id = $case->string('id');
        $input = $case->object('input');
        $expectedError = $case->string('expected_error');

        if ($input->has('decode') === $input->has('generate')) {
            Assert::fail('Case ' . $id . ': input has to hold exactly one of decode or generate');
        }

        if ($input->has('decode')) {
            $string = $input->string('decode');
            $input->done();
            $case->done();

            $result = Uxid::decode($string);
            Assert::assertInstanceOf(Invalid::class, $result, $id);
            Assert::assertSame($expectedError, $result->reason->value, $id);

            return;
        }

        $request = $input->object('generate');
        $input->done();
        $case->done();

        self::refused($id, $request, $expectedError);
    }

    /** Runs one monotonic case: the ids a replayed monotonic generator emits equal the expected ones. */
    public static function monotonic(string $line): void
    {
        $case = CaseFields::fromLine($line);
        $id = $case->string('id');
        $input = $case->object('input');
        $prefix = $input->nullableString('prefix') ?? '';
        $size = Size::from($input->string('size'));
        $compact = $input->bool('compact');
        $times = $input->intList('times');
        $initialSeed = $input->int('initial_seed');
        $steps = $input->intList('steps');
        $overflowSeed = $input->nullableInt('overflow_seed');
        $input->done();
        $expected = $case->stringList('expected');
        $case->done();

        $seeds = [$initialSeed];
        if ($overflowSeed !== null) {
            $seeds[] = $overflowSeed;
        }
        $draws = new ReplayDraws(seeds: $seeds, steps: $steps);
        $generator = new Monotonic($draws);

        $ids = [];
        foreach ($times as $time) {
            $ids[] = $generator->generate(
                prefix: $prefix,
                size: $size,
                compactTime: $compact,
                case: LetterCase::Lower,
                time: $time,
            );
        }

        Assert::assertSame($expected, $ids, $id);
        Assert::assertCount(\count($seeds) + \count($steps), $draws->calls, $id . ' draws replayed');
    }

    /** Makes a refused generate request and asserts it throws the expected error. */
    private static function refused(string $id, CaseFields $request, string $expectedError): void
    {
        $prefix = '';
        $size = Size::XLarge;
        $randSize = null;
        $compact = null;
        $letters = LetterCase::Lower;
        $time = null;
        $rand = null;
        $from = null;
        $monotonic = false;

        if ($request->has('prefix')) {
            $prefix = $request->nullableString('prefix') ?? '';
        }
        if ($request->has('size')) {
            $size = Size::from($request->string('size'));
        }
        if ($request->has('compact')) {
            $compact = $request->bool('compact');
        }
        if ($request->has('case')) {
            $letters = LetterCase::from($request->string('case'));
        }
        if ($request->has('time')) {
            $time = $request->int('time');
        }
        if ($request->has('rand')) {
            $rand = self::bytes($request->string('rand'), $id);
        }
        if ($request->has('rand_size')) {
            $randSize = $request->int('rand_size');
        }
        if ($request->has('from')) {
            $from = $request->string('from');
        }
        if ($request->has('monotonic')) {
            $monotonic = $request->bool('monotonic');
        }
        $request->done();

        try {
            if ($monotonic) {
                (new Monotonic())->generate(
                    prefix: $prefix,
                    size: $size,
                    randSize: $randSize,
                    compactTime: $compact,
                    case: $letters,
                    time: $time,
                    rand: $rand,
                    from: $from,
                );
            } else {
                Uxid::generate(
                    prefix: $prefix,
                    size: $size,
                    randSize: $randSize,
                    compactTime: $compact ?? false,
                    case: $letters,
                    time: $time,
                    rand: $rand,
                    from: $from,
                );
            }
        } catch (UxidException $exception) {
            Assert::assertSame($expectedError, $exception->errorCode->value, $id);

            return;
        }

        Assert::fail('Case ' . $id . ': the request was not refused, expected ' . $expectedError);
    }

    /** Decodes a lowercase hex string to bytes. */
    private static function bytes(string $hex, string $id): string
    {
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            Assert::fail('Case ' . $id . ': rand is not hex');
        }

        return $bytes;
    }
}
