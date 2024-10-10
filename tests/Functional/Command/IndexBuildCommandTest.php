<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Command;

use Sineflow\ElasticsearchBundle\Command\IndexBuildCommand;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;

/**
 * Class IndexBuildCommandTest
 */
class IndexBuildCommandTest extends AbstractCommandTestCase
{
    /**
     * Tests building index
     */
    public function testExecute(): void
    {
        $manager = $this->getIndexManager('customer');

        // Initialize command
        $commandName = 'sineflow:es:index:build';
        $commandTester = $this->getCommandTester($commandName);
        $options = [];
        $arguments['command'] = $commandName;
        $arguments['index'] = $manager->getManagerName();
        $arguments['--delete-old'] = true;

        // Test if the command returns 0 or not
        $this->assertSame(
            0,
            $commandTester->execute($arguments, $options)
        );

        $expectedOutput =
            'Built index for "customer"'
        ;

        // Test if the command output matches the expected output or not
        $this->assertStringMatchesFormat($expectedOutput.'%a', $commandTester->getDisplay());

        $manager->dropIndex();
    }

    /**
     * Returns build index command with assigned container.
     *
     * @return IndexBuildCommand
     */
    protected function getCommand()
    {
        $registry = $this->getContainer()->get(IndexManagerRegistry::class);
        $command = new IndexBuildCommand($registry);

        return $command;
    }
}
