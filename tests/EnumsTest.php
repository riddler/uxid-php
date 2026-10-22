<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Riddler\Uxid\ErrorCode;
use Riddler\Uxid\LetterCase;
use Riddler\Uxid\Size;

/**
 * Pins the backed values of the public enums: the error codes are the closed
 * set of generation error names the wire format and its corpus give, and the
 * size and case values are the corpus's spellings.
 */
#[CoversNothing]
final class EnumsTest extends TestCase
{
    # sabotage: change ErrorCode::RandWithFrom's value to 'rand_with_deterministic' -> assertSame failed: index 4 was 'rand_with_deterministic', not 'rand_with_from'
    public function testErrorCodesAreTheClosedSetOfGenerationErrorNames(): void
    {
        self::assertSame(
            [
                'compact_time_out_of_range',
                'rand_width_mismatch',
                'rand_size_too_large',
                'from_with_monotonic',
                'rand_with_from',
                'rand_with_monotonic',
            ],
            array_map(static fn(ErrorCode $code): string => $code->value, ErrorCode::cases()),
        );
    }

    # sabotage: change Size::XLarge's value to 'xlarge' -> assertSame failed: index 4 was 'xlarge', not 'xl'
    public function testSizeValuesAreTheShortSpellings(): void
    {
        self::assertSame(
            ['xs', 's', 'm', 'l', 'xl'],
            array_map(static fn(Size $size): string => $size->value, Size::cases()),
        );
    }

    # sabotage: change LetterCase::Upper's value to 'UPPER' -> assertSame failed: index 1 was 'UPPER', not 'upper'
    public function testLetterCaseValuesAreLowerAndUpper(): void
    {
        self::assertSame(
            ['lower', 'upper'],
            array_map(static fn(LetterCase $case): string => $case->value, LetterCase::cases()),
        );
    }
}
