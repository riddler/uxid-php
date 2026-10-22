<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

/**
 * The vendored conformance corpus on disk: where it is, its case files split
 * into lines, its manifest, and the corpus hash by the corpus README's rule
 * (the lowercase hex SHA-256 of the case files' bytes concatenated in
 * manifest order, behind the `sha256:` marker).
 */
final class Corpus
{
    /** The case files in the order the manifest lists them, with their kinds. */
    public const array FILES = [
        'encode' => 'encode.json',
        'deterministic' => 'deterministic.json',
        'decode' => 'decode.json',
        'invalid' => 'invalid.json',
        'monotonic' => 'monotonic.json',
    ];

    /** The conformance directory, relative to the tests directory. */
    public static function directory(): string
    {
        return __DIR__ . '/../conformance';
    }

    /** The bytes of one file under the conformance directory, by its path relative to it. */
    public static function read(string $file): string
    {
        $text = file_get_contents(self::directory() . '/' . $file);
        if ($text === false) {
            throw new \RuntimeException('Cannot read the corpus file ' . $file . '.');
        }

        return $text;
    }

    /**
     * The cases of one case file, one raw line each. A case file ends in a
     * newline; the empty piece after the last one is not a case.
     *
     * @return list<string>
     */
    public static function lines(string $file): array
    {
        $text = self::read($file);
        if ($text === '' || !str_ends_with($text, "\n")) {
            throw new \RuntimeException('The corpus file ' . $file . ' does not end in a newline.');
        }

        $pieces = explode("\n", $text);
        array_pop($pieces);

        return $pieces;
    }

    /**
     * The parsed manifest, `manifest.json`.
     *
     * @return array<mixed>
     */
    public static function manifest(): array
    {
        return self::decodeObject(self::read('manifest.json'));
    }

    /**
     * The parsed `SOURCE.json`, which records where the corpus was vendored from.
     *
     * @return array<mixed>
     */
    public static function source(): array
    {
        return self::decodeObject(self::read('SOURCE.json'));
    }

    /**
     * The corpus hash of the given case files, read in the order given: the
     * `sha256:` marker and the lowercase hex SHA-256 of their bytes
     * concatenated.
     *
     * @param list<string> $files file names under the conformance directory
     */
    public static function hash(array $files): string
    {
        $bytes = '';
        foreach ($files as $file) {
            $bytes .= self::read($file);
        }

        return 'sha256:' . hash('sha256', $bytes);
    }

    /** @return array<mixed> */
    private static function decodeObject(string $json): array
    {
        $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($value)) {
            throw new \RuntimeException('A corpus JSON file is not an object.');
        }

        return $value;
    }
}
