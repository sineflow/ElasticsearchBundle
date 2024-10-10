<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Mapping;

use Jchook\AssertThrows\AssertThrows;
use Sineflow\ElasticsearchBundle\Mapping\DocumentLocator;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadata;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Mapping\DocumentParser;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\ObjCategory;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\ObjTag;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Repository\ProductRepository;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Customer;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Provider\CustomerProvider;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Class DocumentMetadataCollectorTest
 */
class DocumentMetadataCollectorTest extends AbstractContainerAwareTestCase
{
    use AssertThrows;

    private DocumentMetadataCollector $metadataCollector;
    private array $indexManagers;
    private DocumentLocator $docLocator;
    private DocumentParser $docParser;
    private CacheInterface $cache;
    private CacheInterface $nullCache;

    /**
     * @var array Expected metadata for customer index
     */
    private array $expectedCustomerMetadata = [
        'properties' => [
            'name' => [
                'type' => 'keyword',
            ],
            'active' => [
                'type' => 'boolean',
            ],
        ],
        'fields' => [
        ],
        'propertiesMetadata' => [
            'name' => [
                'propertyName'   => 'name',
                'type'           => 'keyword',
                'multilanguage'  => null,
                'propertyAccess' => 1,
            ],
            'active' => [
                'propertyName'  => 'active',
                'type'          => 'boolean',
                'multilanguage' => null,
                'methods'       => [
                    'getter' => 'isActive',
                    'setter' => 'setActive',
                ],
                'propertyAccess' => 2,
            ],
            '_id' => [
                'propertyName'   => 'id',
                'type'           => 'keyword',
                'propertyAccess' => 1,
            ],
            '_score' => [
                'propertyName'   => 'score',
                'type'           => 'float',
                'propertyAccess' => 1,
            ],
        ],
        'repositoryClass' => null,
        'providerClass'   => CustomerProvider::class,
        'className'       => Customer::class,
    ];

    private array $expectedProductMetadata = [
        'properties' => [
            'title' => [
                'fields' => [
                    'raw' => [
                        'type' => 'keyword',
                    ],
                    'title' => [
                        'type' => 'text',
                    ],
                ],
                'type' => 'text',
            ],
            'description' => [
                'type' => 'text',
            ],
            'category' => [
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                    ],
                    'title' => [
                        'type' => 'keyword',
                    ],
                    'tags' => [
                        'properties' => [
                            'tagname' => [
                                'type' => 'text',
                            ],
                        ],
                    ],
                ],
            ],
            'related_categories' => [
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                    ],
                    'title' => [
                        'type' => 'keyword',
                    ],
                    'tags' => [
                        'properties' => [
                            'tagname' => [
                                'type' => 'text',
                            ],
                        ],
                    ],
                ],
            ],
            'price' => [
                'type' => 'float',
            ],
            'location' => [
                'type' => 'geo_point',
            ],
            'limited' => [
                'type' => 'boolean',
            ],
            'released' => [
                'type' => 'date',
            ],
            'ml_info-en' => [
                'analyzer' => 'en_analyzer',
                'fields'   => [
                    'ngram' => [
                        'type'     => 'text',
                        'analyzer' => 'en_analyzer',
                    ],
                ],
                'type' => 'text',
            ],
            'ml_info-fr' => [
                'analyzer' => 'default_analyzer',
                'fields'   => [
                    'ngram' => [
                        'type'     => 'text',
                        'analyzer' => 'default_analyzer',
                    ],
                ],
                'type' => 'text',
            ],
            'ml_info-default' => [
                'type'         => 'keyword',
                'ignore_above' => 256,
            ],
            'ml_more_info-en' => [
                'type' => 'text',
            ],
            'ml_more_info-fr' => [
                'type' => 'text',
            ],
            'ml_more_info-default' => [
                'type'  => 'text',
                'index' => false,
            ],
            'pieces_count' => [
                'fields' => [
                    'count' => [
                        'type'     => 'token_count',
                        'analyzer' => 'whitespace',
                    ],
                ],
                'type' => 'text',
            ],
        ],
        'fields' => [
            'dynamic' => 'strict',
        ],
        'propertiesMetadata' => [
            'title' => [
                'propertyName'   => 'title',
                'type'           => 'text',
                'multilanguage'  => null,
                'propertyAccess' => 1,
            ],
            'description' => [
                'propertyName'   => 'description',
                'type'           => 'text',
                'multilanguage'  => null,
                'propertyAccess' => 1,
            ],
            'category' => [
                'propertyName'       => 'category',
                'type'               => 'object',
                'multilanguage'      => null,
                'multiple'           => null,
                'propertiesMetadata' => [
                    'id' => [
                        'propertyName'   => 'id',
                        'type'           => 'integer',
                        'multilanguage'  => null,
                        'propertyAccess' => 1,
                    ],
                    'title' => [
                        'propertyName'   => 'title',
                        'type'           => 'keyword',
                        'multilanguage'  => null,
                        'propertyAccess' => 1,
                    ],
                    'tags' => [
                        'propertyName'       => 'tags',
                        'type'               => 'object',
                        'multilanguage'      => null,
                        'multiple'           => true,
                        'propertiesMetadata' => [
                            'tagname' => [
                                'propertyName'   => 'tagName',
                                'type'           => 'text',
                                'multilanguage'  => null,
                                'propertyAccess' => 1,
                            ],
                        ],
                        'className'      => ObjTag::class,
                        'propertyAccess' => 1,
                    ],
                ],
                'className'      => ObjCategory::class,
                'propertyAccess' => 1,
            ],
            'related_categories' => [
                'propertyName'       => 'relatedCategories',
                'type'               => 'object',
                'multilanguage'      => null,
                'multiple'           => true,
                'propertiesMetadata' => [
                    'id' => [
                        'propertyName'   => 'id',
                        'type'           => 'integer',
                        'multilanguage'  => null,
                        'propertyAccess' => 1,
                    ],
                    'title' => [
                        'propertyName'   => 'title',
                        'type'           => 'keyword',
                        'multilanguage'  => null,
                        'propertyAccess' => 1,
                    ],
                    'tags' => [
                        'propertyName'       => 'tags',
                        'type'               => 'object',
                        'multilanguage'      => null,
                        'multiple'           => true,
                        'propertiesMetadata' => [
                            'tagname' => [
                                'propertyName'   => 'tagName',
                                'type'           => 'text',
                                'multilanguage'  => null,
                                'propertyAccess' => 1,
                            ],
                        ],
                        'className'      => ObjTag::class,
                        'propertyAccess' => 1,
                    ],
                ],
                'className'      => ObjCategory::class,
                'propertyAccess' => 1,
            ],
            'price' => [
                'propertyName'   => 'price',
                'type'           => 'float',
                'multilanguage'  => null,
                'propertyAccess' => 1,
            ],
            'location' => [
                'propertyName'   => 'location',
                'type'           => 'geo_point',
                'multilanguage'  => null,
                'propertyAccess' => 1,
            ],
            'limited' => [
                'propertyName'   => 'limited',
                'type'           => 'boolean',
                'multilanguage'  => null,
                'propertyAccess' => 1,
            ],
            'released' => [
                'propertyName'   => 'released',
                'type'           => 'date',
                'multilanguage'  => null,
                'propertyAccess' => 1,
            ],
            'ml_info' => [
                'propertyName'   => 'mlInfo',
                'type'           => 'text',
                'multilanguage'  => true,
                'propertyAccess' => 1,
            ],
            'ml_more_info' => [
                'propertyName'   => 'mlMoreInfo',
                'type'           => 'text',
                'multilanguage'  => true,
                'propertyAccess' => 1,
            ],
            'pieces_count' => [
                'propertyName'   => 'tokenPiecesCount',
                'type'           => 'text',
                'multilanguage'  => null,
                'propertyAccess' => 1,
            ],
            '_id' => [
                'propertyName'   => 'id',
                'type'           => 'keyword',
                'propertyAccess' => 1,
            ],
            '_score' => [
                'propertyName'   => 'score',
                'type'           => 'float',
                'propertyAccess' => 1,
            ],
        ],
        'repositoryClass' => ProductRepository::class,
        'providerClass'   => null,
        'className'       => Product::class,
    ];

    protected function setUp(): void
    {
        $this->indexManagers = $this->getContainer()->getParameter('sfes.indices');
        $this->docLocator = $this->getContainer()->get(DocumentLocator::class);
        $this->docParser = $this->getContainer()->get(DocumentParser::class);
        $this->cache = $this->getContainer()->get('cache.system');
        $this->nullCache = $this->getContainer()->get('app.null_cache_adapter');

        $this->metadataCollector = new DocumentMetadataCollector($this->indexManagers, $this->docLocator, $this->docParser, $this->cache);
    }

    public function testGetDocumentMetadata(): void
    {
        $indexMetadataForAlias = $this->metadataCollector->getDocumentMetadata('AcmeFooBundle:Customer');
        $indexMetadata = $this->metadataCollector->getDocumentMetadata(Customer::class);

        // Make sure alias and FQN name work the same
        $this->assertEquals($indexMetadata, $indexMetadataForAlias);

        // Check metadata is as expected
        $this->assertEquals(new DocumentMetadata($this->expectedCustomerMetadata), $indexMetadata);

        // Check metadata for a more complex entity with nested objects
        $this->assertEquals(
            new DocumentMetadata($this->expectedProductMetadata),
            $this->metadataCollector->getDocumentMetadata('AcmeBarBundle:Product')
        );
    }

    public function testMetadataWithCacheVsNoCache(): void
    {
        $metadataCollectorWithCacheDisabled = new DocumentMetadataCollector($this->indexManagers, $this->docLocator, $this->docParser, $this->nullCache);
        $this->assertEquals($this->metadataCollector->getDocumentMetadata('AcmeFooBundle:Customer'), $metadataCollectorWithCacheDisabled->getDocumentMetadata('AcmeFooBundle:Customer'));
        $this->assertEquals($this->metadataCollector->getObjectPropertiesMetadata('AcmeFooBundle:Customer'), $metadataCollectorWithCacheDisabled->getObjectPropertiesMetadata('AcmeFooBundle:Customer'));
    }

    public function testGetObjectPropertiesMetadataWithValidClasses(): void
    {
        // Test document's metadata
        $metadata = $this->metadataCollector->getObjectPropertiesMetadata('AcmeFooBundle:Customer');
        $this->assertEquals($this->expectedCustomerMetadata['propertiesMetadata'], $metadata);

        // Test nested object's metadata
        $metadata = $this->metadataCollector->getObjectPropertiesMetadata(ObjTag::class);
        $this->assertEquals($this->expectedProductMetadata['propertiesMetadata']['category']['propertiesMetadata']['tags']['propertiesMetadata'], $metadata);

        // Test nested object in short notation metadata
        $metadata = $this->metadataCollector->getObjectPropertiesMetadata('AcmeBarBundle:ObjTag');
        $this->assertEquals($this->expectedProductMetadata['propertiesMetadata']['category']['propertiesMetadata']['tags']['propertiesMetadata'], $metadata);

        // Test non-existing bundle
        $this->assertThrows(\UnexpectedValueException::class, function (): void {
            $this->metadataCollector->getObjectPropertiesMetadata('NonExistingBundle:Test');
        });

        // Test non-existing class
        $this->assertThrows(\ReflectionException::class, function (): void {
            $this->metadataCollector->getObjectPropertiesMetadata('Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\NonExisting');
        });
    }

    public function testGetDocumentClassIndex(): void
    {
        $docClassIndex = $this->metadataCollector->getDocumentClassIndex('AcmeBarBundle:Product');
        $this->assertEquals('bar', $docClassIndex);

        $docClassIndex = $this->metadataCollector->getDocumentClassIndex(Product::class);
        $this->assertEquals('bar', $docClassIndex);

        $this->assertThrows(\InvalidArgumentException::class, function (): void {
            $this->metadataCollector->getDocumentClassIndex('Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\NonExistingClass');
        });
    }
}
