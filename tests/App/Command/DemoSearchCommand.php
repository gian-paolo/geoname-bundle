<?php

namespace Pallari\GeonameBundle\Tests\App\Command;

use Pallari\GeonameBundle\Service\GeonameSearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo:search',
    description: 'Demo command to test GeonameSearchService',
)]
class DemoSearchCommand extends Command
{
    public function __construct(
        private readonly GeonameSearchService $searchService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('term', InputArgument::REQUIRED, 'The search term (e.g. Torino)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $term = $input->getArgument('term');

        $io->title(sprintf('Searching for: %s', $term));

        $results = $this->searchService->search($term, [
            'with_admin_names' => true,
            'limit' => 5
        ]);

        if (empty($results)) {
            $io->warning('No results found.');
            return Command::SUCCESS;
        }

        foreach ($results as $res) {
            $io->section(sprintf('%s (%s)', $res['name'], $res['country_code']));
            $io->definitionList(
                ['ID' => $res['geonameid']],
                ['ASCII Name' => $res['ascii_name']],
                ['Coordinates' => sprintf('%f, %f', $res['latitude'], $res['longitude'])],
                ['Population' => $res['population'] ?? 'N/A'],
                ['Region (Admin1)' => $res['admin1_name'] ?? 'N/A'],
                ['Province (Admin2)' => $res['admin2_name'] ?? 'N/A'],
                ['Feature Code' => $res['feature_code']]
            );
        }

        return Command::SUCCESS;
    }
}
