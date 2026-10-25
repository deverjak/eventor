<?php

declare(strict_types=1);

// Code style (chapter 10): @Symfony + @PER-CS2.0 (the stricter of the two wins where they differ) and strict types.
// Run through Docker: `bin/check` (dry run) or `bin/check --fix` from the repository root.
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config', __DIR__.'/migrations', __DIR__.'/phpstan', __DIR__.'/public', __DIR__.'/bin'])
    ->notPath([
        'bootstrap.php',
        'reference.php',
        'bundles.php',
    ])
    ->name('*.php')
    ->append([__DIR__.'/bin/console'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
        'php_unit_method_casing' => ['case' => 'snake_case'], // test names are sentences: test_late_payment_creates_exception
        'phpdoc_to_comment' => false,
        'phpdoc_summary' => false,
        'phpdoc_align' => false,
        'single_line_throw' => false,
        'yoda_style' => ['equal' => true, 'identical' => true, 'less_and_greater' => false],
        'concat_space' => ['spacing' => 'none'],
        'class_definition' => ['single_line' => true],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => false],
        'fully_qualified_strict_types' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache')
;
