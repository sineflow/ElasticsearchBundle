<?php

namespace Sineflow\ElasticsearchBundle\Command;

use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for creating elasticsearch index.
 */
#[AsCommand('sineflow:es:index:create', 'Creates elasticsearch index.')]
class IndexCreateCommand extends Command
{
    public function __construct(private readonly IndexManagerRegistry $indexManagerRegistry)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'index',
                InputArgument::REQUIRED,
                'The identifier of the index'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexManagerName = $input->getArgument('index');
        $indexManager = $this->indexManagerRegistry->get($indexManagerName);
        try {
            $indexManager->createIndex();
            $output->writeln(
                \sprintf(
                    '<info>Created index for "</info><comment>%s</comment><info>"</info>',
                    $indexManagerName
                )
            );
        } catch (\Exception $e) {
            $output->writeln(
                \sprintf(
                    '<error>Index creation failed:</error> <comment>%s</comment>',
                    $e->getMessage()
                )
            );
        }

        return 0;
    }
}
