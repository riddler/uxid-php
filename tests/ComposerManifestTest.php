<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Pins the package's runtime requirements in composer.json: PHP 8.3 or later
 * and ext-random, and no other package or extension.
 */
#[CoversNothing]
final class ComposerManifestTest extends TestCase
{
    # sabotage: add "ext-mbstring": "*" to require in composer.json -> assertSame failed: the require keys carried ext-mbstring after php and ext-random
    public function testRuntimeRequirementsArePhpAndExtRandomOnly(): void
    {
        self::assertSame(['php', 'ext-random'], array_keys(self::manifestSection('require')));
    }

    # sabotage: change "php" in require to "^8.2" -> assertSame failed: '^8.2' is not identical to '^8.3'
    public function testPhpFloorIsEightThree(): void
    {
        self::assertSame('^8.3', self::manifestSection('require')['php'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestSection(string $key): array
    {
        $json = file_get_contents(\dirname(__DIR__) . '/composer.json');
        self::assertIsString($json);

        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);

        $section = $manifest[$key] ?? null;
        self::assertIsArray($section);

        /** @var array<string, mixed> $section */
        return $section;
    }
}
