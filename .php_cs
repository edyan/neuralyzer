<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->files()
    ->name('*.php')
    ->in('src');

return Symfony\CS\Config\Config::create()
    ->level(\Symfony\CS\FixerInterface::PSR2_LEVEL)
    ->fixers(array(
        // All items of the @param, @throws, @return, @var, and @type phpdoc
        // tags must be aligned vertically.
        'phpdoc_params',
        // Convert double quotes to single quotes for simple strings.
        'single_quote',
        // Group and seperate @phpdocs with empty lines.
        'phpdoc_separation',
        // An empty line feed should precede a return statement.
        'return',
        // Remove trailing whitespace at the end of blank lines.
        'whitespacy_lines',
        // Removes extra empty lines.
        'extra_empty_lines',
        // Unused use statements must be removed.
        'unused_use',
        // PHP code MUST use only UTF-8 without BOM (remove BOM).
        'encoding',
        // A file must always end with a single empty line feed.
        'eof_ending',
        // All PHP files must use the Unix LF (linefeed) line ending.
        'linefeed',
        // Remove trailing whitespace at the end of non-blank lines.
        'trailing_spaces',
    ))
    ->finder($finder);
