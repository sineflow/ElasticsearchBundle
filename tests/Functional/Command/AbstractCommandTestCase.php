<?php

declare(strict_types=1);

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Command;

use Sineflow\ElasticsearchBundle\Manager\IndexManager;
use Sineflow\ElasticsearchBundle\Tests\AbstractContainerAwareTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Helper test case for testing commands.
 */
abstract class AbstractCommandTestCase extends AbstractContainerAwareTestCase
{
    /**
     * @param string $name
     *
     * @return IndexManager
     */
    protected function getIndexManager($name)
    {
        return $this->getContainer()->get(\sprintf('sfes.index.%s', $name));
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
     * @param string $commandName
     *
     * @return CommandTester
     */
    protected function getCommandTester($commandName)
    {
        $app = new Application();
        if (method_exists($app, 'addCommand')) {
            // Symfony >= 7.4 (Application::add() is removed in Symfony 8)
            $app->addCommand($this->getCommand());
        } else {
            $app->add($this->getCommand());
        }

        $command = $app->find($commandName);
        $commandTester = new CommandTester($command);

        return $commandTester;
    }
}
