<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
;

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,

        // Exclude "phpdoc_summary" rule: "PHPDoc summary should end in either a full stop, exclamation mark, or question mark"
        'phpdoc_summary'                => false,
        'phpdoc_annotation_without_dot' => false,
        'no_superfluous_phpdoc_tags'    => ['allow_mixed' => true, 'remove_inheritdoc' => false],
        // Indent '=>' operator
        'binary_operator_spaces' => ['operators' => ['=>' => 'align_single_space_minimal']],
        // PSR12 imports order
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
    ])
;
