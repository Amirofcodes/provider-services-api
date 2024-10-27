<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use App\Trait\LoggerTrait;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:cache:clear-tags',
    description: 'Clears cache for specified tags or all tagged cache',
)]
class CacheClearTagsCommand extends Command
{
    use LoggerTrait;

    public function __construct(
        private TagAwareCacheInterface $cache,
        private LoggerInterface $logger
    ) {
        parent::__construct();
        $this->setLogger($logger);
    }

    protected function configure(): void
    {
        $this
            ->addOption('tags', 't', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Specific tags to clear', [])
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Clear all tagged cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tags = $input->getOption('tags');
        $clearAll = $input->getOption('all');

        try {
            if ($clearAll) {
                $this->logInfo('Clearing all tagged cache', [
                    'command' => 'cache:clear-tags',
                    'mode' => 'all'
                ]);

                // Clear providers and services cache
                $this->cache->invalidateTags(['providers_tag', 'services_tag']);
                $io->success('All tagged cache cleared successfully.');

                return Command::SUCCESS;
            }

            if (empty($tags)) {
                $io->error('Please specify tags to clear or use --all option.');
                return Command::INVALID;
            }

            $this->logInfo('Clearing specific tagged cache', [
                'command' => 'cache:clear-tags',
                'tags' => $tags
            ]);

            $this->cache->invalidateTags($tags);
            $io->success(sprintf('Cache cleared for tags: %s', implode(', ', $tags)));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logError('Error clearing cache', [
                'command' => 'cache:clear-tags',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $io->error('Failed to clear cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
