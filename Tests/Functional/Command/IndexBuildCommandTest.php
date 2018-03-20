<?php

namespace Sineflow\ElasticsearchBundle\Tests\Functional\Command;

use Sineflow\ElasticsearchBundle\Command\IndexBuildCommand;

/**
 * Class IndexBuildCommandTest
 */
class IndexBuildCommandTest extends AbstractCommandTestCase
{
    /**
     * Tests building index
     */
    public function testExecute()
    {
        $manager = $this->getIndexManager('foo');

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

        $expectedOutput = sprintf(
            'Built index for "foo"'
        );

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
        $command = new IndexBuildCommand();
        $command->setContainer($this->getContainer());

        return $command;
    }
}
