<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Mapping;

use Sineflow\ElasticsearchBundle\Exception\BulkRequestException;
use Sineflow\ElasticsearchBundle\Finder\Finder;
use Sineflow\ElasticsearchBundle\Tests\AbstractElasticsearchTestCase;
use Sineflow\ElasticsearchBundle\Tests\app\fixture\Acme\FooBundle\Document\Answer;

/**
 * Class MetaFieldsTest
 */
class MetaFieldsTest extends AbstractElasticsearchTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'qanda' => [
                'AcmeFooBundle:Question' => [
                    [
                        '_id' => 1,
                        'text' => 'What is your favourite colour?',
                    ],
                    [
                        '_id' => 2,
                        'text' => 'Best direction?',
                    ],
                ],
                'AcmeFooBundle:Answer' => [
                    [
                        '_parent' => 1,
                        'text' => 'Red',
                    ],
                    [
                        '_parent' => 1,
                        'text' => 'Blue',
                    ],
                    [
                        '_parent' => 2,
                        'text' => 'South',
                    ],
                    [
                        '_parent' => 2,
                        'text' => 'North',
                    ],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        // Create and populate indices just once for all tests in this class
        $this->getIndexManager('qanda', !$this->hasCreatedIndexManager('qanda'));
    }

    public function testCreateChildDocument()
    {
        $im = $this->getIndexManager('qanda');
        $esVersion = $im->getConnection()->getClient()->info()['version']['number'];

        $answer = new Answer();
        $answer->id = 'NEWID';
        $answer->text = 'East';
        $answer->parentId = 2;
        $im->persist($answer);

        try {
            $im->getConnection()->commit();
        } catch (BulkRequestException $e) {
            print_r($e->getBulkResponseItems());
        }

        // Verify that the document is there
        $finder = $this->getContainer()->get('sfes.finder');
        $searchBody = [
            'query' => [
                'term' => [
                    '_id' => 'NEWID',
                ],
            ],
        ];

        $res = $finder->find(['AcmeFooBundle:Answer'], $searchBody, Finder::RESULTS_OBJECT, [], $totalHits);

        $this->assertEquals(1, $totalHits, 'Child document was not indexed');

        /** @var Answer $doc */
        $doc = $res->current();

        if (version_compare($esVersion, '2.0', '>=')) {
            $this->assertEquals('2', $doc->parentId, 'Parent document id is wrong');
        } else {
            // In ES versions before 2.0, _parent is not returned by default and thus not populated in the entity
            // @see https://github.com/elastic/elasticsearch/issues/8068
            $this->assertEquals(null, $doc->parentId, 'Parent document id is wrong');
        }
    }

    public function testParentChildSearch()
    {
        $finder = $this->getContainer()->get('sfes.finder');

        $searchBody = [
            'query' => [
                'has_parent' => [
                    'parent_type' => 'questions',
                    'query' => ['term' => ['_id' => 1]],
                ],
            ],
        ];

        try {
            $res = $finder->find(['AcmeFooBundle:Answer'], $searchBody, Finder::RESULTS_ARRAY, [], $totalHits);
        } catch (\Exception $e) {
            // For some reason, a normal exception is simply ignored by phpunit and the test shows green, but it's not
            throw new \PHPUnit_Framework_Exception($e->getMessage());
        }

        $this->assertCount(2, $res);
    }
}
