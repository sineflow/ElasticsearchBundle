<?php

namespace Sineflow\ElasticsearchBundle\Tests\Unit\Mapping;

use Doctrine\Common\Cache\CacheProvider;
use Sineflow\ElasticsearchBundle\Mapping\DocumentLocator;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadata;
use Sineflow\ElasticsearchBundle\Mapping\DocumentParser;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;

class DocumentMetadataCollectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DocumentMetadataCollector
     */
    private $metadataCollector;

    /**
     * @var DocumentLocator
     */
    private $docLocator;

    /**
     * @var DocumentParser
     */
    private $docParser;

    /**
     * @var CacheProvider
     */
    private $cache;

    /**
     * Initialize MetadataCollector.
     */
    public function setUp()
    {
        $this->docLocator = $this->getMockBuilder('Sineflow\ElasticsearchBundle\Mapping\DocumentLocator')
            ->disableOriginalConstructor()
            ->getMock();
        $this->docLocator->method('getShortClassName')->willReturnArgument(0);
        $this->docLocator->method('getAllDocumentDirs')->willReturn([]);

        $this->docParser = $this->getMockBuilder('Sineflow\ElasticsearchBundle\Mapping\DocumentParser')
            ->disableOriginalConstructor()
            ->getMock();

        $this->cache = $this->getMockBuilder('Doctrine\Common\Cache\FilesystemCache')
            ->disableOriginalConstructor()
            ->getMock();

        $metadata = new DocumentMetadata(
            array (
                'type' => 'foo',
                'properties' =>
                    array (
                        'title' =>
                            array (
                                'null_value' => false,
                                'type' => 'boolean',
                            ),
                            '_id' =>
                            array (
                                'type' => 'string',
                            ),
                            '_score' =>
                            array (
                                'type' => 'float',
                            ),
                            '_parent' =>
                            array (
                                'type' => 'string',
                            ),
                    ),
                    'fields' =>
                    array (
                        '_all' =>
                            array (
                                'enabled' => true,
                            ),
                    ),
                    'propertiesMetadata' =>
                    array (
                        'title' =>
                            array (
                                'propertyName' => 'title',
                                'type' => 'boolean',
                                'multilanguage' => null,
                                'propertyAccess' => 1,
                            ),
                            '_id' =>
                            array (
                                'propertyName' => 'id',
                                'type' => 'string',
                                'multilanguage' => null,
                                'propertyAccess' => 1,
                            ),
                            '_score' =>
                            array (
                                'propertyName' => 'score',
                                'type' => 'float',
                                'multilanguage' => null,
                                'propertyAccess' => 1,
                            ),
                            '_parent' =>
                            array (
                                'propertyName' => 'parent',
                                'type' => 'string',
                                'multilanguage' => null,
                                'propertyAccess' => 1,
                            ),
                    ),
                    'objects' =>
                    array (
                        0 => 'AppBundle\\ElasticSearch\\Document\\ObjSome',
                        1 => 'AppBundle\\ElasticSearch\\Document\\ObjOther',
                    ),
                    'repositoryClass' => null,
                    'className' => 'AppBundle\\ElasticSearch\\Document\\Foo',
                    'shortClassName' => 'TestBundle:Foo',
            )
        );
        $this->cache->method('fetch')->willReturn(
            [
                'foo' => [
                    'TestBundle:Foo' => $metadata
                ]
            ]
        );

        $indexManagers = [
            'test' => [
                'name' => 'testname',
                'connection' => 'test1',
                'use_aliases' => false,
                'settings' => [
                    'refresh_interval' => 2,
                    'number_of_replicas' => 3,
                    'analysis' => [
                        'filter' => [
                            'test_filter' => [
                                'type' => 'nGram'
                            ]
                        ],
                        'tokenizer' => [
                            'test_tokenizer' => [
                                'type' => 'nGram'
                            ]
                        ],
                        'analyzer' => [
                            'test_analyzer' => [
                                'type' => 'custom'
                            ]
                        ]
                    ]
                ],
                'types' => [
                    'TestBundle:Foo',
                    'TestBundle:Bar',
                ],
            ],
        ];

        $this->metadataCollector = new DocumentMetadataCollector($indexManagers, $this->docLocator, $this->docParser, $this->cache);
    }

    /**
     * Test getting metadata for non-existing document class
     *
     * @expectedException \InvalidArgumentException
     */
    public function testGetDocumentMetadataForNonExistingDocument()
    {
        $this->metadataCollector->getDocumentMetadata('nonexisting');
    }

    /**
     * Test getting metadata for document class
     */
    public function testGetDocumentMetadata()
    {
        $this->assertInstanceOf(
            'Sineflow\ElasticsearchBundle\Mapping\DocumentMetadata',
            $this->metadataCollector->getDocumentMetadata('TestBundle:Foo'),
            'Incorrect metadata.'
        );
    }
}
