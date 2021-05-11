<?php

namespace Functional\Document\Provider;

use Sineflow\ElasticsearchBundle\Document\Provider\ElasticsearchProvider;
use Sineflow\ElasticsearchBundle\Mapping\DocumentMetadataCollector;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;

class ElasticsearchProviderTest extends AbstractElasticsearchTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'bar' => [
                [
                    '_id' => 1,
                    'title' => 'Product 1',
                ],
                [
                    '_id' => 2,
                    'title' => 'Product 2',
                ],
                [
                    '_id' => 3,
                    'title' => 'Product 3',
                ],
            ],
        ];
    }

    public function testGetDocument()
    {
        $esProvider = $this->getProvider();

        $doc = $esProvider->getDocument(3);

        $this->assertEquals([
            '_id' => 3,
            'title' => 'Product 3',
        ], $doc);
    }

    public function testGetDocuments()
    {
        $esProvider = $this->getProvider();

        foreach ($esProvider->getDocuments() as $document) {
            $this->assertArrayHasKey('_id', $document);
            $this->assertArrayHasKey('title', $document);
            $ids[] = $document['_id'];
        }

        // Since we're retrieving source docs ordered by _doc, they don't necessarily come in the order inserted
        // so we order them in order to compare
        sort($ids);

        // Make sure all and exact documents were returned
        $this->assertEquals([1, 2, 3], $ids);
    }

    private function getProvider()
    {
        return new ElasticsearchProvider(
            'AcmeBarBundle:Product',
            $this->getContainer()->get(DocumentMetadataCollector::class),
            $this->getIndexManager('bar'),
            'AcmeBarBundle:Product'
        );
    }
}
