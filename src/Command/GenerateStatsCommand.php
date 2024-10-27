<?php

namespace App\Command;

use App\Repository\ProviderRepository;
use App\Repository\ServiceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Trait\LoggerTrait;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:stats:generate',
    description: 'Generates statistics about providers and services',
)]
class GenerateStatsCommand extends Command
{
    use LoggerTrait;

    public function __construct(
        private ProviderRepository $providerRepository,
        private ServiceRepository $serviceRepository,
        private LoggerInterface $logger
    ) {
        parent::__construct();
        $this->setLogger($logger);
    }

    protected function configure(): void
    {
        $this->addOption(
            'format',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Output format (table or json)',
            'table'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');

        try {
            $this->logInfo('Generating system statistics', [
                'command' => 'stats:generate',
                'format' => $format
            ]);

            $providers = $this->providerRepository->findAll();
            $services = $this->serviceRepository->findAll();

            $stats = [
                'total_providers' => count($providers),
                'total_services' => count($services),
                'avg_services_per_provider' => count($providers) > 0
                    ? round(count($services) / count($providers), 2)
                    : 0,
                'providers_without_services' => count(array_filter($providers, fn($p) => $p->getServices()->isEmpty())),
                'total_service_value' => array_reduce(
                    $services,
                    fn($carry, $service) => $carry + floatval($service->getPrice()),
                    0
                ),
            ];

            if ($format === 'json') {
                $io->write(json_encode($stats, JSON_PRETTY_PRINT));
            } else {
                $io->section('System Statistics');
                $io->table(
                    ['Metric', 'Value'],
                    array_map(fn($k, $v) => [$k, $v], array_keys($stats), $stats)
                );
            }

            $this->logInfo('Statistics generated successfully', [
                'command' => 'stats:generate',
                'stats' => $stats
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logError('Error generating statistics', [
                'command' => 'stats:generate',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $io->error('Failed to generate statistics: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
