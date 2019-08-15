<?php

namespace Sineflow\ElasticsearchBundle\Command;

use Sineflow\ElasticsearchBundle\Manager\IndexManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for (re)building elasticsearch index.
 */
class IndexBuildCommand extends Command
{
    protected static $defaultName = 'sineflow:es:index:build';

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
            ->setDescription('(Re)builds elasticsearch index.')
            ->addArgument(
                'index',
                InputArgument::REQUIRED,
                'The identifier of the index'
            )
            ->addOption(
                'delete-old',
                null,
                InputOption::VALUE_NONE,
                'If set, the old index will be deleted upon successful rebuilding'
            )
            ->addOption(
                'cancel-current',
                null,
                InputOption::VALUE_NONE,
                'If set, any indices the write alias points to (except the live one) will be deleted'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indexManagerName = $input->getArgument('index');
        $indexManager = $this->indexManagerRegistry->get($indexManagerName);

        $deleteOldIndex = (bool) $input->getOption('delete-old');
        $cancelCurrent = (bool) $input->getOption('cancel-current');

        try {
            $indexManager->rebuildIndex($deleteOldIndex, $cancelCurrent);
            $output->writeln(
                sprintf(
                    '<info>Built index for "</info><comment>%s</comment><info>"</info>',
                    $indexManagerName
                )
            );
        } catch (\Exception $e) {
            $output->writeln(
                sprintf(
                    '<error>Index building failed:</error> <comment>%s</comment>',
                    $e->getMessage()
                )
            );
        }
    }
}
