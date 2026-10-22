<?php

declare(strict_types=1);

// Fails the gate when the line coverage of src/ is under the minimum.
//
// Usage: php scripts/check-coverage.php <clover.xml> <minimum percent>
//
// PHPUnit writes the Clover report; this reads the project's statement
// totals from it. The gate runs it only once src/ holds a PHP file, and a
// report that measured no line fails, so the minimum cannot be met by
// measuring nothing.

$args = $_SERVER['argv'] ?? null;
if (!is_array($args) || count($args) !== 3 || !is_string($args[1]) || !is_string($args[2]) || !is_numeric($args[2])) {
    fwrite(STDERR, "usage: php scripts/check-coverage.php <clover.xml> <minimum percent>\n");
    exit(2);
}

$report = $args[1];
$minimum = (float) $args[2];

if (!is_file($report)) {
    fwrite(STDERR, "coverage report {$report} not found: PHPUnit wrote no Clover report\n");
    exit(1);
}

$xml = simplexml_load_file($report);
if ($xml === false || !isset($xml->project->metrics)) {
    fwrite(STDERR, "coverage report {$report} has no project metrics\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    fwrite(STDERR, "the report measured no line in src/: is the coverage driver loaded?\n");
    exit(1);
}

$percent = 100.0 * $covered / $statements;
printf("src/ line coverage: %d of %d lines, %.2f percent (minimum %.2f)\n", $covered, $statements, $percent, $minimum);

if ($percent < $minimum) {
    fwrite(STDERR, "line coverage is under the minimum\n");
    exit(1);
}
