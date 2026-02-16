<?php

namespace Pallari\GeonameBundle\Command;

use Pallari\GeonameBundle\Service\GeonameImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pallari:geoname:import-admin-codes',
    description: 'Imports administrative codes (Region, Province, etc. names)',
)]
class ImportAdminCodesCommand extends Command
{
    public function __construct(
        private GeonameImporter $importer,
        private EntityManagerInterface $em,
        private string $countryEntityClass = 'App\Entity\GeoCountry',
        private string $admin1EntityClass = 'App\Entity\GeoAdmin1',
        private string $admin2EntityClass = 'App\Entity\GeoAdmin2',
        private string $admin3EntityClass = 'App\Entity\GeoAdmin3',
        private string $admin4EntityClass = 'App\Entity\GeoAdmin4',
        private string $admin5EntityClass = 'App\Entity\GeoAdmin5',
        private string $geonameTable = 'geoname'
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Administrative Codes Management');

        // 1. Get enabled countries
        $countries = $this->em->getRepository($this->countryEntityClass)->findBy(['isEnabled' => true]);
        $countryCodes = array_map(fn($c) => strtoupper($c->getCode()), $countries);

        if (empty($countryCodes)) {
            $io->warning('No countries enabled in GeoCountry table. Please enable at least one country first.');
            return Command::FAILURE;
        }

        $io->note('Enabled countries: ' . implode(', ', $countryCodes));

        $choice = $io->choice('Select synchronization mode', [
            'smart' => 'Smart Sync: Extract labels from your local Geoname table (Fastest, best for full imports)',
            'external' => 'External Sync: Download official labels from GeoNames files (Best for partial population-based imports)',
        ], 'smart');

        if ($choice === 'smart') {
            $io->section('Syncing Admin names from local Geoname table...');
            $stats = $this->importer->syncAdminTablesFromTable($countryCodes);
            foreach ($stats as $level => $count) {
                $io->writeln(sprintf('- %s: %d records synchronized.', $level, $count));
            }
            $io->success('Smart synchronization completed.');
        } else {
            $io->section('Importing labels from external GeoNames files...');
            
            // 1. Admin1
            $io->text('Detecting used Admin1 codes...');
            $usedAdmin1 = $this->importer->getUsedAdminCodes('ADM1', $countryCodes);
            $io->writeln(sprintf('- Found %d unique Admin1 codes in your database.', count($usedAdmin1)));

            $io->text('Importing Admin1 (Regions)...');
            $url1 = 'https://download.geonames.org/export/dump/admin1CodesASCII.txt';
            $count1 = $this->importer->importAdminCodes($url1, $this->admin1EntityClass, $usedAdmin1);
            $io->writeln(sprintf('- Admin1: %d labels imported/updated.', $count1));

            // 2. Admin2
            $io->text('Detecting used Admin2 codes...');
            $usedAdmin2 = $this->importer->getUsedAdminCodes('ADM2', $countryCodes);
            $io->writeln(sprintf('- Found %d unique Admin2 codes in your database.', count($usedAdmin2)));

            $io->text('Importing Admin2 (Provinces)...');
            $url2 = 'https://download.geonames.org/export/dump/admin2Codes.txt';
            $count2 = $this->importer->importAdminCodes($url2, $this->admin2EntityClass, $usedAdmin2);
            $io->writeln(sprintf('- Admin2: %d labels imported/updated.', $count2));

            // 3. Admin5 (Special case)
            if ($io->confirm('Do you want to import Admin5 (sub-municipal) codes from external file? (Usually not needed)', false)) {
                $io->text('Importing Admin5 codes (this updates the main geoname table)...');
                $url5 = 'https://download.geonames.org/export/dump/adminCode5.zip';
                $count5 = $this->importer->importAdmin5($url5, $this->geonameTable);
                $io->writeln(sprintf('- Admin5: %d records updated in geoname table.', $count5));
                
                $io->text('Syncing Admin5 table from updated geoname table...');
                $stats = $this->importer->syncAdminTablesFromTable($countryCodes);
                if (isset($stats['ADM5'])) {
                    $io->writeln(sprintf('- Admin5: %d records synchronized to Admin5 table.', $stats['ADM5']));
                }
            }
            
            $io->success('External import completed.');
        }

        return Command::SUCCESS;
    }
}
