<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Pins the rules every GitHub Actions workflow in .github/workflows/ keeps:
 * each action is pinned to a full commit SHA with its version in a trailing
 * comment, no workflow reads a secret, and no workflow grants a write
 * permission.
 */
#[CoversNothing]
final class WorkflowPinningTest extends TestCase
{
    # sabotage: change ci.yml's first checkout to actions/checkout@v4 -> assertMatchesRegularExpression failed: ci.yml line "      - uses: actions/checkout@v4" does not match the pinned form
    public function testEveryActionIsPinnedToACommitShaWithItsVersion(): void
    {
        $uses = [];
        foreach (self::workflowLines() as $name => $lines) {
            foreach ($lines as $line) {
                if (preg_match('/^\s*(-\s+)?uses:/', $line) === 1) {
                    $uses[] = $line;
                    self::assertMatchesRegularExpression(
                        '/^\s*(-\s+)?uses:\s+[A-Za-z0-9_.-]+\/[A-Za-z0-9_.\/-]+@[0-9a-f]{40} # v?\d+(\.\d+)*$/',
                        $line,
                        "{$name} line \"{$line}\" is not pinned to a full commit SHA with its version in a trailing comment",
                    );
                }
            }
        }

        self::assertNotSame([], $uses, 'no workflow uses an action, so nothing was checked');
    }

    # sabotage: add "TOKEN: ${{ secrets.PACKAGIST_TOKEN }}" to the env of release.yml's poll step -> assertDoesNotMatchRegularExpression failed: release.yml carried a secrets expression
    public function testNoWorkflowReadsASecret(): void
    {
        foreach (self::workflowLines() as $name => $lines) {
            self::assertDoesNotMatchRegularExpression('/\bsecrets\s*\./', implode("\n", $lines), "{$name} reads a secret");
        }
    }

    # sabotage: change ci.yml's workflow-level "contents: read" to "contents: write" -> assertDoesNotMatchRegularExpression failed: ci.yml granted a write permission
    public function testNoWorkflowGrantsAWritePermission(): void
    {
        foreach (self::workflowLines() as $name => $lines) {
            self::assertDoesNotMatchRegularExpression('/^\s*[a-z-]+:\s*write\s*$/m', implode("\n", $lines), "{$name} grants a write permission");
        }
    }

    /**
     * @return array<string, list<string>> each workflow file's name and its lines
     */
    private static function workflowLines(): array
    {
        $files = glob(\dirname(__DIR__) . '/.github/workflows/*.yml');
        self::assertIsArray($files);
        self::assertNotSame([], $files, 'no workflow file under .github/workflows/');

        $workflows = [];
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            self::assertIsArray($lines);
            $workflows[basename($file)] = $lines;
        }

        return $workflows;
    }
}
