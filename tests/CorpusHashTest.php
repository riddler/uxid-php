<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The vendored corpus is the one the manifest describes and the one
 * SOURCE.json says it was taken from: the corpus hash and every case count
 * are recomputed from the files by the corpus README's rule.
 */
#[Group('corpus')]
final class CorpusHashTest extends TestCase
{
    /** The corpus hash recomputed from the case files, in manifest order, equals the manifest's. */
    # sabotage: Corpus::hash reads each case file twice -> the assertSame of the manifest's corpus_hash failed
    public function testTheCorpusHashMatchesTheCaseFiles(): void
    {
        $manifest = Corpus::manifest();

        self::assertSame($manifest['corpus_hash'] ?? null, Corpus::hash(array_values(Corpus::FILES)));
        self::assertMatchesRegularExpression('/^sha256:[0-9a-f]{64}$/', Corpus::hash(array_values(Corpus::FILES)));
    }

    /** Each file's case count in the manifest equals the number of lines in the file. */
    # sabotage: Corpus::lines keeps the empty piece after the last newline -> the case_count assertSame failed for encode.json
    public function testEachCaseCountMatchesItsFile(): void
    {
        $files = Corpus::manifest()['files'] ?? null;
        self::assertIsArray($files);

        foreach ($files as $entry) {
            self::assertIsArray($entry);
            $file = $entry['file'] ?? null;
            self::assertIsString($file);
            self::assertSame($entry['case_count'] ?? null, \count(Corpus::lines($file)), $file);
        }
    }

    /** The manifest lists exactly the five case files, in order, at corpus version 1. */
    # sabotage: decode.json and invalid.json swapped in Corpus::FILES -> the assertSame of the listed files failed
    public function testTheManifestListsTheFiveCaseFilesInOrder(): void
    {
        $manifest = Corpus::manifest();
        self::assertSame(1, $manifest['corpus_version'] ?? null);

        $files = $manifest['files'] ?? null;
        self::assertIsArray($files);

        $listed = [];
        foreach ($files as $entry) {
            self::assertIsArray($entry);
            $kind = $entry['kind'] ?? null;
            self::assertIsString($kind);
            $listed[$kind] = $entry['file'] ?? null;
        }

        self::assertSame(Corpus::FILES, $listed);
        self::assertSame(array_keys(Corpus::FILES), array_keys($listed));
    }

    /** SOURCE.json names the uxid-ex release the corpus came from and the same hash as the manifest. */
    # sabotage: the tag in a copy of SOURCE.json set to v2.9.0 -> the assertSame of the tag failed
    public function testSourceRecordsTheReleaseAndTheHash(): void
    {
        $source = Corpus::source();

        self::assertSame('riddler/uxid-ex', $source['repo'] ?? null);
        self::assertSame('v2.10.0', $source['tag'] ?? null);
        self::assertSame('57235777cb29c70f751552ce7aa20ab5fe71fb74', $source['sha'] ?? null);
        self::assertSame(Corpus::manifest()['corpus_hash'] ?? null, $source['corpus_hash'] ?? null);
        self::assertSame(Corpus::hash(array_values(Corpus::FILES)), $source['corpus_hash'] ?? null);
    }

    /** No case file holds a carriage return, so a checkout cannot have changed its line endings. */
    # sabotage: the first newline of decode.json made a CRLF -> the assertStringNotContainsString failed for decode.json
    public function testNoCaseFileHoldsACarriageReturn(): void
    {
        foreach (Corpus::FILES as $file) {
            self::assertStringNotContainsString("\r", Corpus::read($file), $file);
        }
    }

    /** Case ids are unique within each file, so a data provider keyed by id drops no case. */
    # sabotage: encode/xs-standard-upper renamed encode/xs-standard-lower in encode.json -> the assertSame of the unique ids failed
    public function testCaseIdsAreUnique(): void
    {
        foreach (Corpus::FILES as $file) {
            $ids = array_map(CorpusCases::id(...), Corpus::lines($file));
            self::assertSame(array_values(array_unique($ids)), $ids, $file);
        }
    }
}
