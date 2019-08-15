<?php

namespace Sineflow\ElasticsearchBundle\Command;

use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for creating elasticsearch index.
 */
class IndexCreateCommand extends Command
{
    protected static $defaultName = 'sineflow:es:index:create';

    /**
     * @var IndexManagerRegistry
     */
    private $indexManagerRegistry;

    /**
     * @param IndexManagerRegistry $indexManagerRegistry
     */
    public function __construct(IndexManagerRegistry $indexManagerRegistry)
    {
        $this->indexManagerRegistry = $indexManagerRegistry;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Creates elasticsearch index.')
            ->addArgument(
                'index',
                InputArgument::REQUIRED,
                'The identifier of the index'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indexManagerName = $input->getArgument('index');
        $indexManager = $this->indexManagerRegistry->get($indexManagerName);
        try {
            $indexManager->createIndex();
            $output->writeln(
                sprintf(
                    '<info>Created index for "</info><comment>%s</comment><info>"</info>',
                    $indexManagerName
                )
            );
        } catch (\Exception $e) {
            $output->writeln(
                sprintf(
                    '<error>Index creation failed:</error> <comment>%s</comment>',
                    $e->getMessage()
                )
            );
        }
    }
}
