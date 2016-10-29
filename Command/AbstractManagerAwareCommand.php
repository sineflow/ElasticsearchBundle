<?php

namespace Sineflow\ElasticsearchBundle\Command;

use Sineflow\ElasticsearchBundle\Exception\InvalidIndexManagerException;
use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class providing common methods for commands working with an index manager
 */
abstract class AbstractManagerAwareCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addArgument(
            'index',
            InputArgument::REQUIRED,
            'The identifier of the index'
        );
    }

    /**
     * Returns index manager by name from the service container.
     *
     * @param string $name Index name as defined in the configuration.
     *
     * @return IndexManager
     *
     * @throws InvalidIndexManagerException If index manager was not found.
     */
    protected function getManager($name)
    {
        $id = $this->getIndexManagerId($name);

        if (!$this->getContainer()->has($id)) {
            throw new InvalidIndexManagerException(
                sprintf(
                    'Index manager named `%s` not found. Available: `%s`.',
                    $name,
                    implode('`, `', array_keys($this->getContainer()->getParameter('sfes.indices')))
                )
            );
        }

        $indexManager = $this->getContainer()->get($id);

        if (!$indexManager instanceof IndexManager) {
            throw new InvalidIndexManagerException(sprintf('Manager must be instance of "%s", "%s" given', IndexManager::class, get_class($indexManager)));
        }

        return $indexManager;
    }

    /**
     * Formats manager service id from its name.
     *
     * @param string $name Manager name.
     *
     * @return string Service id.
     */
    private function getIndexManagerId($name)
    {
        return sprintf('sfes.index.%s', $name);
    }
}
