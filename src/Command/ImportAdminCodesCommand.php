<?php

namespace Gpp\GeonameBundle\Command;

use Gpp\GeonameBundle\Service\GeonameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'gpp:geoname:import-admin-codes',
    description: 'Imports Admin1 and Admin2 codes (Region and Province names)',
)]
class ImportAdminCodesCommand extends Command
{
    public function __construct(
        private GeonameImporter $importer,
        private string $admin1EntityClass = 'App\Entity\GeoAdmin1',
        private string $admin2EntityClass = 'App\Entity\GeoAdmin2'
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Importing Administrative Codes');

        $io->section('Importing Admin1 (Regions)...');
        $url1 = 'https://download.geonames.org/export/dump/admin1CodesASCII.txt';
        $count1 = $this->importer->importAdminCodes($url1, $this->admin1EntityClass);
        $io->success(sprintf('Imported %d Admin1 records.', $count1));

        $io->section('Importing Admin2 (Provinces)...');
        $url2 = 'https://download.geonames.org/export/dump/admin2Codes.txt';
        $count2 = $this->importer->importAdminCodes($url2, $this->admin2EntityClass);
        $io->success(sprintf('Imported %d Admin2 records.', $count2));

        return Command::SUCCESS;
    }
}
