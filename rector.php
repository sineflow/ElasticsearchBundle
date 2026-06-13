<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEmptyNullableObjectToAssertInstanceofRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withCache(__DIR__.'/.rector-cache', FileCacheStorage::class)
    ->withSymfonyContainerXml(__DIR__.'/var/cache/test/Sineflow_ElasticsearchBundle_Tests_App_AppKernelTestDebugContainer.xml')
    ->withPHPStanConfigs([__DIR__.'/phpstan.dist.neon'])
    ->withParallel()

    ->withImportNames(importShortClasses: false) // Allow global classes without use statements

    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])

    ->withAttributesSets(symfony: true, doctrine: true)

    ->withSkip([
        // The @Required tags in the legacy annotation classes are doctrine/annotations markers,
        // not Symfony's DI #[Required] attribute, so they must not be converted
        AnnotationToAttributeRector::class => [
            __DIR__.'/src/Annotation',
        ],

        // Rewrites assertNull() on nullable-object returns to the weaker assertNotInstanceOf(),
        // which no longer verifies the value is actually null
        AssertEmptyNullableObjectToAssertInstanceofRector::class,
    ])

    ->withSets([
        SymfonySetList::SYMFONY_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_100,
    ])

    ->withPhpSets()
;
