<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        '@PHP84Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => false,
        ],
        'ordered_class_elements' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'single_line_throw' => false,
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => false,
            'remove_inheritdoc' => true,
        ],
        'phpdoc_to_comment' => false,
        'concat_space' => ['spacing' => 'one'],
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/cache/php-cs-fixer/php-cs-fixer.cache');
