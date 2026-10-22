<?php

declare(strict_types=1);

// The PHP-CS-Fixer configuration the gate checks (a dry run that fails on any
// file it would change).
//
// single_line_comment_style is limited to `/* */` comments: a test's
// sabotage note is a `#` comment above the test method, and the gate's note
// scan reads `#` lines only, so `#` comments are never rewritten to `//`,
// whichever rule set is in force.

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/scripts'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        '@PHP8x3Migration' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_line_comment_style' => ['comment_types' => ['asterisk']],
    ])
    ->setFinder($finder);
