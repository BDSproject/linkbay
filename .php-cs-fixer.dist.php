<?php
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['node_modules', 'vendor', 'translations', 'sql', 'views'])
    ->name('*.php')
    ->notName('index.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'binary_operator_spaces' => true,
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder);
