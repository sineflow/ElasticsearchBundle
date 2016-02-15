<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Command;

use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Symfony\Component\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Helper test case for testing commands.
 */
abstract class AbstractCommandTestCase extends WebTestCase
{
    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected function getContainer()
    {
        return self::createClient()->getContainer();
    }

    /**
     * @param string $name
     *
     * @return IndexManager
     */
    protected function getIndexManager($name)
    {
        return $this->getContainer()->get(sprintf('sfes.index.%s', $name));
    }

    /**
     * Returns command
     *
     * @return Command
     */
    abstract protected function getCommand();

    /**
     * Returns command tester.
     *
     * @param string commandName
     * @return CommandTester
     */
    protected function getCommandTester($commandName)
    {
        $app = new Application();
        $app->add($this->getCommand());

        $command = $app->find($commandName);
        $commandTester = new CommandTester($command);

        return $commandTester;
    }

}
