<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Mapping;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Sineflow\ElasticsearchBundle\Mapping\DocumentLocator;
use Sineflow\ElasticsearchBundle\Mapping\DocumentParser;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\ObjCategory;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\ObjTag;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Product;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\BarBundle\Document\Repository\ProductRepository;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\Customer;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Document\EntityWithInvalidEnum;
use Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Enum\CustomerTypeEnum;

class DocumentParserTest extends AbstractContainerAwareTestCase
{
    private DocumentParser $documentParser;

    protected function setUp(): void
    {
        if (!class_exists(AnnotationReader::class)) {
            $this->markTestSkipped('doctrine/annotations is not installed, skipping DocumentParser tests.');
        }

        $reader = new AnnotationReader();
        $locator = $this->getContainer()->get(DocumentLocator::class);
        $separator = $this->getContainer()->getParameter('sfes.mlproperty.language_separator');
        $languages = $this->getContainer()->getParameter('sfes.languages');
        $this->documentParser = new DocumentParser($reader, $locator, $separator, $languages);
    }

    public function testParseNonDocument(): void
    {
        $reflection = new \ReflectionClass(ObjCategory::class);
        $res = $this->documentParser->parse($reflection, []);

        $this->assertSame([], $res);
    }

    public function testParseDocumentWithEnumProperty()
    {
        $reflection = new \ReflectionClass(Customer::class);
        $res = $this->documentParser->parse($reflection, []);
        $this->assertSame(CustomerTypeEnum::class, $res['propertiesMetadata']['customer_type']['enumType']);
    }

    public function testParseDocumentWithInvalidEnumFieldProperty()
    {
        $this->expectException(AnnotationException::class);
        $reflection = new \ReflectionClass(EntityWithInvalidEnum::class);
        $this->documentParser->parse($reflection, []);
    }

    public function testParse(): void
    {
        $reflection = new \ReflectionClass(Product::class);
        $indexAnalyzers = [
            'default_analyzer' => [
                'type' => 'standard',
            ],
            'en_analyzer' => [
                'type' => 'standard',
            ],
        ];

        $res = $this->documentParser->parse($reflection, $indexAnalyzers);

        $expected = [
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
                    'propertyAccess' => 1,
                ],
                'description' => [
                    'propertyName'   => 'description',
                    'type'           => 'text',
                    'propertyAccess' => 1,
                ],
                'category' => [
                    'propertyName'       => 'category',
                    'type'               => 'object',
                    'multiple'           => null,
                    'propertiesMetadata' => [
                        'id' => [
                            'propertyName'   => 'id',
                            'type'           => 'integer',
                            'propertyAccess' => 1,
                        ],
                        'title' => [
                            'propertyName'   => 'title',
                            'type'           => 'keyword',
                            'propertyAccess' => 1,
                        ],
                        'tags' => [
                            'propertyName'       => 'tags',
                            'type'               => 'object',
                            'multiple'           => true,
                            'propertiesMetadata' => [
                                'tagname' => [
                                    'propertyName'   => 'tagName',
                                    'type'           => 'text',
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
                    'multiple'           => true,
                    'propertiesMetadata' => [
                        'id' => [
                            'propertyName'   => 'id',
                            'type'           => 'integer',
                            'propertyAccess' => 1,
                        ],
                        'title' => [
                            'propertyName'   => 'title',
                            'type'           => 'keyword',
                            'propertyAccess' => 1,
                        ],
                        'tags' => [
                            'propertyName'       => 'tags',
                            'type'               => 'object',
                            'multiple'           => true,
                            'propertiesMetadata' => [
                                'tagname' => [
                                    'propertyName'   => 'tagName',
                                    'type'           => 'text',
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
                    'propertyAccess' => 1,
                ],
                'location' => [
                    'propertyName'   => 'location',
                    'type'           => 'geo_point',
                    'propertyAccess' => 1,
                ],
                'limited' => [
                    'propertyName'   => 'limited',
                    'type'           => 'boolean',
                    'propertyAccess' => 1,
                ],
                'released' => [
                    'propertyName'   => 'released',
                    'type'           => 'date',
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

        $this->assertEquals($expected, $res);
    }
}
