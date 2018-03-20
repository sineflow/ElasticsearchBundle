<?php

namespace Sineflow\ElasticsearchBundle\Tests;

use Sineflow\ElasticsearchBundle\Exception\BulkRequestException;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;

/**
 * Base test which creates unique connection to test with.
 */
abstract class AbstractElasticsearchTestCase extends AbstractContainerAwareTestCase
{
    /**
     * @var IndexManager[] Holds used index managers.
     */
    private $indexManagers = [];

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->getContainer();
    }

    /**
     * Can be overwritten in child class to populate elasticsearch index with the data.
     *
     * Example:
     *      "managername" =>
     *      [
     *          'acmetype' => [
     *              [
     *                  '_id' => 1,
     *                  'title' => 'foo',
     *              ],
     *              [
     *                  '_id' => 2,
     *                  'title' => 'bar',
     *              ]
     *          ]
     *      ]
     *
     * @return array
     */
    protected function getDataArray()
    {
        return [];
    }

    /**
     * Populates elasticsearch with data.
     *
     * @param IndexManager $indexManager
     * @param array        $data
     */
    protected function populateElasticsearchWithData($indexManager, array $data)
    {
        if (!empty($data)) {
            foreach ($data as $type => $documents) {
                foreach ($documents as $document) {
                    $indexManager->persistRaw($type, $document);
                }
            }
            try {
                $indexManager->getConnection()->commit();
                $indexManager->getConnection()->refresh();
            } catch (BulkRequestException $e) {
                print_r($e->getBulkResponseItems());
                throw $e;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();

        foreach ($this->indexManagers as $name => $indexManager) {
            try {
                $indexManager->dropIndex();
            } catch (\Exception $e) {
                // Do nothing.
            }
        }
    }

    /**
     * Returns index manager instance with injected connection if does not exist creates new one.
     *
     * @param string $name          Index manager name
     * @param bool   $createIndex   Whether to drop and recreate the index
     *
     * @return IndexManager
     *
     * @throws \LogicException
     */
    protected function getIndexManager($name, $createIndex = true)
    {
        $serviceName = sprintf('sfes.index.%s', $name);

        if (!$this->getContainer()->has($serviceName)) {
            throw new \LogicException(sprintf('Index manager "%s" does not exist', $name));
        }

        /** @var IndexManager $indexManager */
        $indexManager = $this
            ->getContainer()
            ->get($serviceName);

        if ($createIndex) {
            // Drops and creates index.
            $indexManager->dropIndex();
            $indexManager->createIndex();

            // Populates elasticsearch index with data.
            $data = $this->getDataArray();
            if (!empty($data[$name])) {
                $this->populateElasticsearchWithData($indexManager, $data[$name]);
            }
        }

        $this->indexManagers[$name] = $indexManager;

        return $indexManager;
    }

    /**
     * Return whether a given index manager has already been created in the current class instance
     *
     * @param $name
     * @return bool
     */
    protected function hasCreatedIndexManager($name)
    {
        return isset($this->indexManagers[$name]);
    }
}
