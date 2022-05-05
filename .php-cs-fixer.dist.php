<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
;

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRules([
        '@PHP73Migration' => true,
        '@PHPUnit75Migration:risky' => true,
        '@PSR2' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'modernize_strpos' => true,
        'is_null' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'native_constant_invocation' => true,
        'native_function_invocation' => [
            'include' => ['@all'],
        ],
        'no_alias_functions' => true,
        'no_useless_else' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'ordered_imports' => true,
        'php_unit_dedicate_assert' => ['target' => 'newest'],
        'static_lambda' => true,
        'ternary_to_null_coalescing' => true,
        'visibility_required' => ['elements' => ['property', 'method', 'const']],
        'void_return' => false,
        'protected_to_private' => false,
        'phpdoc_summary' => false,
        'phpdoc_annotation_without_dot' => false,
        'phpdoc_to_comment' => ['ignored_tags' => ['@var']]
    ])
;

