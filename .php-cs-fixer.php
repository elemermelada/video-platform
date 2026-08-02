<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        // Collapse the stray blank lines this codebase puts inside braces.
        'no_extra_blank_lines' => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
            ],
        ],
        'no_whitespace_in_blank_line' => true,
        'blank_line_before_statement' => [
            'statements' => ['return'],
        ],
    ])
    ->setFinder($finder);
