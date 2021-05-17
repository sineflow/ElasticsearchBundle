<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Mapping;

use Sineflow\ElasticsearchBundle\Mapping\DocumentLocator;
use Sineflow\ElasticsearchBundle\Mapping\DocumentParser;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;
use Doctrine\Common\Annotations\AnnotationReader;

/**
 * Class DocumentParserTest
 */
class DocumentParserTest extends AbstractContainerAwareTestCase
{
    /**
     * @var DocumentParser
     */
    private $documentParser;

    public function setUp(): void
    {
        $reader = new AnnotationReader;
        $locator = $this->getContainer()->get(DocumentLocator::class);
        $separator = $this->getContainer()->getParameter('sfes.mlproperty.language_separator');
        $this->documentParser = new DocumentParser($reader, $locator, $separator);
        $this->documentParser->setLanguageProvider($this->getContainer()->get('app.es.language_provider'));
    }

    public function testParseNonDocument()
    {

        $reflection = new \ReflectionClass('Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\ObjCategory');
        $res = $this->documentParser->parse($reflection, []);

        $this->assertEquals([], $res);
    }

    public function testParse()
    {
        $reflection = new \ReflectionClass('Sineflow\ElasticsearchBundle\Tests\App\fixture\Acme\BarBundle\Document\Product');
        $indexAnalyzers = [
            'default_analyzer' =>
                [
                    'type' => 'standard',
                ],
            'en_analyzer' =>
                [
                    'type' => 'standard',
                ],
        ];

        $res = $this->documentParser->parse($reflection, $indexAnalyzers);

        $expected = [
            'properties' =>
                [
                    'title' =>
                    [
                        'fields' =>
                            [
                                'raw' =>
                                    [
                                        'type' => 'keyword',
                                    ],
                                    'title' =>
                                    [
                                        'type' => 'text',
                                    ],
                            ],
                            'type' => 'text',
                    ],
                    'description' =>
                    [
                        'type' => 'text',
                    ],
                    'category' =>
                    [
                        'properties' =>
                            [
                                'id' =>
                                    [
                                        'type' => 'integer',
                                    ],
                                    'title' =>
                                    [
                                        'type' => 'keyword',
                                    ],
                                    'tags' =>
                                    [
                                        'properties' =>
                                            [
                                                'tagname' =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                            ],
                                    ],
                            ],
                    ],
                    'related_categories' =>
                    [
                        'properties' =>
                            [
                                'id' =>
                                    [
                                        'type' => 'integer',
                                    ],
                                    'title' =>
                                    [
                                        'type' => 'keyword',
                                    ],
                                    'tags' =>
                                    [
                                        'properties' =>
                                            [
                                                'tagname' =>
                                                    [
                                                        'type' => 'text',
                                                    ],
                                            ],
                                    ],
                            ],
                    ],
                    'price' =>
                    [
                        'type' => 'float',
                    ],
                    'location' =>
                    [
                        'type' => 'geo_point',
                    ],
                    'limited' =>
                    [
                        'type' => 'boolean',
                    ],
                    'released' =>
                    [
                        'type' => 'date',
                    ],
                    'ml_info-en' =>
                    [
                        'analyzer' => 'en_analyzer',
                        'fields'   =>
                            [
                                'ngram' =>
                                    [
                                        'type'     => 'text',
                                        'analyzer' => 'en_analyzer',
                                    ],
                            ],
                        'type' => 'text',
                    ],
                    'ml_info-fr' =>
                    [
                        'analyzer' => 'default_analyzer',
                        'fields'   =>
                            [
                                'ngram' =>
                                    [
                                        'type'     => 'text',
                                        'analyzer' => 'default_analyzer',
                                    ],
                            ],
                        'type' => 'text',
                    ],
                    'ml_info-default' =>
                    [
                        'type' => 'keyword',
                        'ignore_above' => 256,
                    ],
                    'ml_more_info-en' =>
                    [
                        'type' => 'text',
                    ],
                    'ml_more_info-fr' =>
                    [
                        'type' => 'text',
                    ],
                    'ml_more_info-default' =>
                    [
                        'type' => 'text',
                        'index' => false,
                    ],
                    'pieces_count' =>
                    [
                        'fields' =>
                            [
                                'count' =>
                                    [
                                        'type' => 'token_count',
                                        'analyzer' => 'whitespace',
                                    ],
                            ],
                            'type' => 'text',
                    ],
                ],
                'fields' =>
                [
                    'dynamic' => 'strict',
                ],
                'propertiesMetadata' =>
                [
                    'title' =>
                    [
                        'propertyName' => 'title',
                        'type' => 'text',
                        'multilanguage' => null,
                        'propertyAccess' => 1,
                    ],
                    'description' =>
                    [
                        'propertyName' => 'description',
                        'type' => 'text',
                        'multilanguage' => null,
                        'propertyAccess' => 1,
                    ],
                    'category' =>
                    [
                        'propertyName' => 'category',
                        'type' => 'object',
                        'multilanguage' => null,
                        'multiple' => null,
                        'propertiesMetadata' =>
                            [
                                'id' =>
                                    [
                                        'propertyName' => 'id',
                                        'type' => 'integer',
                                        'multilanguage' => null,
                                        'propertyAccess' => 1,
                                    ],
                                    'title' =>
                                    [
                                        'propertyName' => 'title',
                                        'type' => 'keyword',
                                        'multilanguage' => null,
                                        'propertyAccess' => 1,
                                    ],
                                    'tags' =>
                                    [
                                        'propertyName' => 'tags',
                                        'type' => 'object',
                                        'multilanguage' => null,
                                        'multiple' => true,
                                        'propertiesMetadata' =>
                                            [
                                                'tagname' =>
                                                    [
                                                        'propertyName' => 'tagName',
                                                        'type' => 'text',
                                                        'multilanguage' => null,
                                                        'propertyAccess' => 1,
                                                    ],
                                            ],
                                            'className' => 'Sineflow\\ElasticsearchBundle\\Tests\\App\\fixture\\Acme\\BarBundle\\Document\\ObjTag',
                                            'propertyAccess' => 1,
                                    ],
                            ],
                            'className' => 'Sineflow\\ElasticsearchBundle\\Tests\\App\\fixture\\Acme\\BarBundle\\Document\\ObjCategory',
                            'propertyAccess' => 1,
                    ],
                    'related_categories' =>
                    [
                        'propertyName' => 'relatedCategories',
                        'type' => 'object',
                        'multilanguage' => null,
                        'multiple' => true,
                        'propertiesMetadata' =>
                            [
                                'id' =>
                                    [
                                        'propertyName' => 'id',
                                        'type' => 'integer',
                                        'multilanguage' => null,
                                        'propertyAccess' => 1,
                                    ],
                                    'title' =>
                                    [
                                        'propertyName' => 'title',
                                        'type' => 'keyword',
                                        'multilanguage' => null,
                                        'propertyAccess' => 1,
                                    ],
                                    'tags' =>
                                    [
                                        'propertyName' => 'tags',
                                        'type' => 'object',
                                        'multilanguage' => null,
                                        'multiple' => true,
                                        'propertiesMetadata' =>
                                            [
                                                'tagname' =>
                                                    [
                                                        'propertyName' => 'tagName',
                                                        'type' => 'text',
                                                        'multilanguage' => null,
                                                        'propertyAccess' => 1,
                                                    ],
                                            ],
                                            'className' => 'Sineflow\\ElasticsearchBundle\\Tests\\App\\fixture\\Acme\\BarBundle\\Document\\ObjTag',
                                            'propertyAccess' => 1,
                                    ],
                            ],
                            'className' => 'Sineflow\\ElasticsearchBundle\\Tests\\App\\fixture\\Acme\\BarBundle\\Document\\ObjCategory',
                            'propertyAccess' => 1,
                    ],
                    'price' =>
                    [
                        'propertyName' => 'price',
                        'type' => 'float',
                        'multilanguage' => null,
                        'propertyAccess' => 1,
                    ],
                    'location' =>
                    [
                        'propertyName' => 'location',
                        'type' => 'geo_point',
                        'multilanguage' => null,
                        'propertyAccess' => 1,
                    ],
                    'limited' =>
                    [
                        'propertyName' => 'limited',
                        'type' => 'boolean',
                        'multilanguage' => null,
                        'propertyAccess' => 1,
                    ],
                    'released' =>
                    [
                        'propertyName' => 'released',
                        'type' => 'date',
                        'multilanguage' => null,
                        'propertyAccess' => 1,
                    ],
                    'ml_info' =>
                        [
                            'propertyName' => 'mlInfo',
                            'type' => 'text',
                            'multilanguage' => true,
                            'propertyAccess' => 1,
                        ],
                    'ml_more_info' =>
                        [
                            'propertyName' => 'mlMoreInfo',
                            'type' => 'text',
                            'multilanguage' => true,
                            'propertyAccess' => 1,
                        ],
                    'pieces_count' =>
                    [
                        'propertyName' => 'tokenPiecesCount',
                        'type' => 'text',
                        'multilanguage' => null,
                        'propertyAccess' => 1,
                    ],
                    '_id' =>
                    [
                        'propertyName' => 'id',
                        'type' => 'keyword',
                        'propertyAccess' => 1,
                    ],
                    '_score' =>
                    [
                        'propertyName' => 'score',
                        'type' => 'float',
                        'propertyAccess' => 1,
                    ],
                ],
                'repositoryClass' => 'Sineflow\\ElasticsearchBundle\\Tests\\App\\fixture\\Acme\\BarBundle\\Document\\Repository\\ProductRepository',
                'providerClass' => null,
                'className' => 'Sineflow\\ElasticsearchBundle\\Tests\\App\\fixture\\Acme\\BarBundle\\Document\\Product',
        ];

        $this->assertEquals($expected, $res);
    }
}
