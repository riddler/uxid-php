<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Riddler\Uxid\Uxid;

/**
 * Uxid::isValid and Uxid::isDeterministic: the same well-formedness rule as
 * decode, as a boolean, with a delimiter and, for isValid, a required
 * prefix.
 */
#[CoversClass(Uxid::class)]
final class ValidityTest extends TestCase
{
    # sabotage: drop the form check in isValid -> assertFalse failed: true is not false (the empty string)
    public function testAWellFormedIdIsValidAndARefusedOneIsNot(): void
    {
        self::assertTrue(Uxid::isValid('usr_01hf7yat0004h1'));
        self::assertTrue(Uxid::isValid('01hf7yat0004hmasw9nf6yy093'));
        self::assertTrue(Uxid::isValid('usr_zcvt7epac0t1ebcsjfyf7cwz25'));
        self::assertFalse(Uxid::isValid(''));
        self::assertFalse(Uxid::isValid('usr_'));
        self::assertFalse(Uxid::isValid('usr_01hf7yat000'));
        self::assertFalse(Uxid::isValid('usr_01hu7yat00000g40r40m30e209'));
        self::assertFalse(Uxid::isValid(str_repeat("\xff", 26)));
    }

    # sabotage: accept any prefix in isValid (true in place of the prefix comparison) -> assertFalse failed: true is not false (usr required cus)
    public function testARequiredPrefixMustMatchExactly(): void
    {
        self::assertTrue(Uxid::isValid('usr_01hf7yat0004h1', requiredPrefix: 'usr'));
        self::assertFalse(Uxid::isValid('usr_01hf7yat0004h1', requiredPrefix: 'cus'));
        self::assertFalse(Uxid::isValid('usr_01hf7yat0004h1', requiredPrefix: 'USR'));
        self::assertFalse(Uxid::isValid('a_usr_01hf7yat0004h1', requiredPrefix: 'usr'));
        self::assertTrue(Uxid::isValid('a_usr_01hf7yat0004h1', requiredPrefix: 'a_usr'));
        self::assertFalse(Uxid::isValid('01hf7yat0004h1', requiredPrefix: 'usr'));
        self::assertFalse(Uxid::isValid('usr_', requiredPrefix: 'usr'));
    }

    # sabotage: drop the empty required prefix arm in isValid -> assertTrue failed: false is not true (usr required '')
    public function testANullOrEmptyRequiredPrefixAcceptsAnyPrefixOrNone(): void
    {
        self::assertTrue(Uxid::isValid('usr_01hf7yat0004h1', requiredPrefix: null));
        self::assertTrue(Uxid::isValid('usr_01hf7yat0004h1', requiredPrefix: ''));
        self::assertTrue(Uxid::isValid('01hf7yat0004h1', requiredPrefix: ''));
    }

    # sabotage: split at a literal '_' in place of $delimiter in isValid -> assertTrue failed: false is not true (usr-01hf7yat0004h1 under '-')
    public function testIsValidTakesADelimiter(): void
    {
        self::assertTrue(Uxid::isValid('usr-01hf7yat0004h1', delimiter: '-', requiredPrefix: 'usr'));
        self::assertFalse(Uxid::isValid('usr-01hf7yat0004h1'));
        self::assertFalse(Uxid::isValid('usr_01hf7yat0004h1', delimiter: '-'));
    }

    # sabotage: drop the form check in isDeterministic -> assertFalse failed: true is not false (usr_z)
    public function testADeterministicIdMustBeWellFormed(): void
    {
        self::assertTrue(Uxid::isDeterministic('usr_zcvt7epac0t1ebcsjfyf7cwz25'));
        self::assertTrue(Uxid::isDeterministic('usr_ZCVT7EPAC0T1EBCSJFYF7CWZ25'));
        self::assertTrue(Uxid::isDeterministic('zqz4cwzgyw8ks97869'));
        self::assertFalse(Uxid::isDeterministic('usr_z'));
        self::assertFalse(Uxid::isDeterministic('usr_zcvt7epac0t1ebcsjfyf7cwzi5'));
        self::assertFalse(Uxid::isDeterministic(''));
        self::assertFalse(Uxid::isDeterministic('usr_'));
    }

    # sabotage: drop the upper-case marker 'Z' in isDeterministic -> assertTrue failed: false is not true (usr_Zt9vnyfd85bff0)
    public function testATimeBasedIdIsNotDeterministic(): void
    {
        self::assertFalse(Uxid::isDeterministic('usr_01hf7yat0004h1'));
        self::assertFalse(Uxid::isDeterministic('usr_yzzzzzzz04hm5'));
        self::assertTrue(Uxid::isDeterministic('usr_Zt9vnyfd85bff0'));
    }

    # sabotage: split at a literal '_' in place of $delimiter in isDeterministic -> assertTrue failed: false is not true (cus-zt9vnyfd85bff0 under '-')
    public function testIsDeterministicTakesADelimiter(): void
    {
        self::assertTrue(Uxid::isDeterministic('cus-zt9vnyfd85bff0', delimiter: '-'));
        self::assertFalse(Uxid::isDeterministic('cus-zt9vnyfd85bff0'));
    }

    # sabotage: drop checkDelimiter in isValid -> assertInstanceOf failed: a ValueError is not an InvalidArgumentException
    public function testIsValidRefusesAnEmptyDelimiter(): void
    {
        self::assertRefusesTheDelimiter(static fn(): bool => Uxid::isValid('usr_01hf7yat00', delimiter: ''));
    }

    # sabotage: drop checkDelimiter in isDeterministic -> assertInstanceOf failed: a ValueError is not an InvalidArgumentException
    public function testIsDeterministicRefusesAnEmptyDelimiter(): void
    {
        self::assertRefusesTheDelimiter(static fn(): bool => Uxid::isDeterministic('usr_zt9vnyfd85bff0', delimiter: ''));
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
