<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Command;

use Sineflow\ElasticsearchBundle\Command\IndexCreateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class IndexCreateCommandTest extends AbstractCommandTestCase
{
    /**
     * Tests creating index
     */
    public function testExecute()
    {
        $manager = $this->getIndexManager('foo');

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

        $expectedOutput = sprintf(
            'Created index for "foo"'
        );

        // Test if the command output matches the expected output or not
        $this->assertStringMatchesFormat($expectedOutput . '%a', $commandTester->getDisplay());

        $manager->dropIndex();
    }

    /**
     * Tests creating index in case of existing this index.
     */
    public function testExecuteWithExistingIndex()
    {
        $manager = $this->getIndexManager('foo');

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

        $expectedOutput = sprintf(
            'Index creation failed'
        );

        // Test if the command output matches the expected output or not
        $this->assertStringMatchesFormat($expectedOutput . '%a', $commandTester->getDisplay());

        $manager->dropIndex();
    }

    /**
     * Returns create index command with assigned container.
     *
     * @return IndexCreateCommand
     */
    protected function getCommand()
    {
        $command = new IndexCreateCommand();
        $command->setContainer($this->getContainer());

        return $command;
    }
}
