<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the PHP script of release.yml's "Check the tag names the version in
 * src/Uxid.php" step, as the workflow holds it, against a throwaway package
 * whose Uxid::VERSION is the value under test: the script answers the
 * version for a plain MAJOR.MINOR.PATCH value and refuses anything else, so
 * a pre-release placeholder can never pass the check as a release.
 */
#[CoversNothing]
final class ReleaseVersionCheckTest extends TestCase
{
    private string $package = '';

    protected function tearDown(): void
    {
        if ($this->package !== '') {
            foreach (['src/Uxid.php', 'vendor/autoload.php', 'check.php'] as $file) {
                @unlink($this->package . '/' . $file);
            }

            @rmdir($this->package . '/src');
            @rmdir($this->package . '/vendor');
            @rmdir($this->package);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function releaseVersions(): array
    {
        return [
            '1.0.0' => ['1.0.0'],
            '0.1.0' => ['0.1.0'],
            '10.20.30' => ['10.20.30'],
        ];
    }

    # sabotage: narrow release.yml's version pattern to /^\d+\.\d+$/D -> assertSame failed: 1 is not identical to 0 (every data set)
    #[DataProvider('releaseVersions')]
    public function testAPlainReleaseVersionPasses(string $version): void
    {
        [$exit, $output] = $this->runCheck($version);

        self::assertSame(0, $exit, $output);
        self::assertSame($version . "\n", $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notReleaseVersions(): array
    {
        return [
            'the placeholder' => ['0.0.0-dev'],
            'a pre-release' => ['1.0.0-rc.1'],
            'build metadata' => ['1.0.0+build'],
            'two parts' => ['1.0'],
            'a v prefix' => ['v1.0.0'],
            'a trailing newline' => ["1.0.0\n"],
        ];
    }

    # sabotage: delete the MAJOR.MINOR.PATCH check from release.yml's version script -> assertSame failed: 0 is not identical to 1 (the placeholder 0.0.0-dev and every other data set)
    #[DataProvider('notReleaseVersions')]
    public function testAnythingButAPlainReleaseVersionIsRefused(string $version): void
    {
        [$exit, $output] = $this->runCheck($version);

        self::assertSame(1, $exit, $output);
        self::assertStringContainsString('not a plain MAJOR.MINOR.PATCH release version', $output);
    }

    /**
     * Writes a package whose Riddler\Uxid\Uxid::VERSION is `$version`, then
     * runs the workflow's script in it.
     *
     * @return array{int, string} the exit code, and the standard output followed by the standard error
     */
    private function runCheck(string $version): array
    {
        $this->package = sys_get_temp_dir() . '/uxid-release-check-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->package . '/src', 0o700, true));
        self::assertTrue(mkdir($this->package . '/vendor', 0o700, true));

        file_put_contents(
            $this->package . '/src/Uxid.php',
            "<?php\nnamespace Riddler\\Uxid;\nfinal class Uxid\n{\n    public const string VERSION = " . var_export($version, true) . ";\n}\n",
        );
        file_put_contents($this->package . '/vendor/autoload.php', "<?php\nrequire __DIR__ . '/../src/Uxid.php';\n");
        file_put_contents($this->package . '/check.php', self::workflowScript());

        $process = proc_open(
            [\PHP_BINARY, 'check.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->package,
        );
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertIsString($stdout);
        self::assertIsString($stderr);
        $output = $stdout . $stderr;

        return [$exit, $output];
    }

    /**
     * The PHP heredoc of the version step in release.yml, with the step's
     * indentation removed.
     */
    private static function workflowScript(): string
    {
        $workflow = file_get_contents(\dirname(__DIR__) . '/.github/workflows/release.yml');
        self::assertIsString($workflow);
        if (preg_match('/^( +)file_version="\$\(php <<\'PHP\'\n(.*?)\n\1PHP\n/ms', $workflow, $match) !== 1) {
            self::fail('release.yml holds no version script in a PHP heredoc');
        }

        $indent = \strlen($match[1]);
        $lines = array_map(
            static fn(string $line): string => substr($line, $indent),
            explode("\n", $match[2]),
        );

        return implode("\n", $lines) . "\n";
    }
}
