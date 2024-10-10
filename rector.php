<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
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

    ->withSets([
        SymfonySetList::SYMFONY_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
    ])

    ->withPhpSets()
;
