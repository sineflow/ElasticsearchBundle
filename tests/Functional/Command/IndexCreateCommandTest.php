<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Command;

use Sineflow\ElasticsearchBundle\Command\IndexCreateCommand;
use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;

class IndexCreateCommandTest extends AbstractCommandTestCase
{
    /**
     * Tests creating index
     */
    public function testExecute(): void
    {
        $manager = $this->getIndexManager('customer');

        // Make sure we don't have pre-existing index
        $manager->dropIndex();

        // Initialize command
        $commandName = 'sineflow:es:index:create';
        $commandTester = $this->getCommandTester($commandName);
        $options = [];
        $arguments['command'] = $commandName;
        $arguments['index'] = $manager->getManagerName();

        // Test if the command returns 0 or not
        $this->assertSame(
            0,
            $commandTester->execute($arguments, $options)
        );

        $expectedOutput =
            'Created index for "customer"'
        ;

        // Test if the command output matches the expected output or not
        $this->assertStringMatchesFormat($expectedOutput.'%a', $commandTester->getDisplay());

        $manager->dropIndex();
    }

    /**
     * Tests creating index in case of existing this index.
     */
    public function testExecuteWithExistingIndex(): void
    {
        $manager = $this->getIndexManager('customer');

        // Make sure we don't have pre-existing index
        $manager->dropIndex();
        $manager->createIndex();

        // Initialize command
        $commandName = 'sineflow:es:index:create';
        $commandTester = $this->getCommandTester($commandName);
        $options = [];
        $arguments['command'] = $commandName;
        $arguments['index'] = $manager->getManagerName();

        // Test if the command returns 0 or not
        $this->assertSame(
            0,
            $commandTester->execute($arguments, $options)
        );

        $expectedOutput =
            'Index creation failed'
        ;

        // Test if the command output matches the expected output or not
        $this->assertStringMatchesFormat($expectedOutput.'%a', $commandTester->getDisplay());

        $manager->dropIndex();
    }

    /**
     * Returns create index command
     *
     * @return IndexCreateCommand
     */
    protected function getCommand()
    {
        $registry = $this->getContainer()->get(IndexManagerRegistry::class);
        $command = new IndexCreateCommand($registry);

        return $command;
    }
}
