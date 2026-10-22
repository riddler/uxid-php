<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runs the README's basic-usage block and checks the pointer page to the
 * reference spec, so neither can drift from the code and the corpus.
 */
#[CoversNothing]
final class ReadmeTest extends TestCase
{
    # sabotage: change `// int(30)` to `// int(31)` in the README's basic-usage block -> assertSame failed: the output line 'int(30)' differs from the expected 'int(31)'
    public function testBasicUsageBlockRunsAndPrintsWhatItsCommentsSay(): void
    {
        $readme = self::read('README.md');
        $found = preg_match('/^## Basic usage\n\n```php\n(.*?)```/ms', $readme, $match);
        self::assertSame(1, $found, 'README.md has no php block under "Basic usage"');

        $expected = [];
        foreach (explode("\n", $match[1]) as $line) {
            if (preg_match('/var_dump\(.*\); \/\/ (.+)$/', $line, $comment) === 1) {
                $expected[] = $comment[1];
            }
        }

        self::assertNotSame([], $expected);

        $file = tempnam(sys_get_temp_dir(), 'uxid-readme-');
        self::assertIsString($file);
        file_put_contents($file, $match[1]);

        ob_start();

        try {
            (static function (string $path): void {
                require $path;
            })($file);
            $output = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            unlink($file);
        }

        self::assertSame($expected, explode("\n", rtrim($output, "\n")));
    }

    # sabotage: change the tag in docs/wire-format.md's spec link to v2.9.0 -> assertStringContainsString failed: the page does not contain the link for the tag conformance/SOURCE.json names
    public function testWireFormatPageLinksTheSpecAtTheCorpusTag(): void
    {
        $source = json_decode(self::read('conformance/SOURCE.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($source);
        $tag = $source['tag'] ?? null;
        self::assertIsString($tag);

        $link = 'https://github.com/riddler/uxid-ex/blob/' . $tag . '/guides/wire-format.md';
        self::assertStringContainsString($link, self::read('docs/wire-format.md'));
        self::assertStringContainsString($link, self::read('README.md'));
    }

    private static function read(string $path): string
    {
        $text = file_get_contents(\dirname(__DIR__) . '/' . $path);
        self::assertIsString($text);

        return $text;
    }
}
