<?php

namespace Pallari\GeonameBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pallari\GeonameBundle\Entity\AbstractGeoCountry;
use Pallari\GeonameBundle\Service\GeonameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pallari:geoname:sync',
    description: 'Synchronizes GeoNames data (Incremental or Full)',
)]
class SyncGeonameCommand extends Command
{
    private string $countryEntityClass;
    private string $languageEntityClass;
    private bool $alternateNamesEnabled;
    private bool $admin5Enabled;
    private string $geonameTable;

    public function __construct(
        private EntityManagerInterface $em,
        private GeonameImporter $importer,
        string $countryEntityClass = 'App\Entity\GeoCountry',
        string $languageEntityClass = 'App\Entity\GeoLanguage',
        bool $alternateNamesEnabled = false,
        bool $admin5Enabled = false,
        string $geonameTable = 'geoname'
    ) {
        parent::__construct();
        $this->countryEntityClass = $countryEntityClass;
        $this->languageEntityClass = $languageEntityClass;
        $this->alternateNamesEnabled = $alternateNamesEnabled;
        $this->admin5Enabled = $admin5Enabled;
        $this->geonameTable = $geonameTable;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('GeoNames Synchronization');

        $this->importer->setOutput($io);

        $countries = $this->em->getRepository($this->countryEntityClass)->findBy(['isEnabled' => true]);

        if (empty($countries)) {
            $io->warning('No enabled countries found in entity: ' . $this->countryEntityClass);
            return Command::SUCCESS;
        }

        $io->note(sprintf('Found %d enabled countries.', count($countries)));

        $yesterday = new \DateTime('yesterday');
        $needsFull = [];
        $needsDaily = [];

        foreach ($countries as $country) {
            $lastImport = $country->getLastImportedAt();
            $status = $lastImport ? $lastImport->format('Y-m-d') : 'NEVER';
            
            // GAP Detection
            if ($lastImport === null || $lastImport->format('Y-m-d') < $yesterday->format('Y-m-d')) {
                $needsFull[] = $country;
                // $io->writeln(sprintf('  - %s: FULL NEEDED (Last: %s)', $country->getCode(), $status));
            } else {
                $needsDaily[] = $country->getCode();
                // $io->writeln(sprintf('  - %s: DAILY ONLY (Last: %s)', $country->getCode(), $status));
            }
        }

        if (empty($needsFull) && empty($needsDaily)) {
            $io->warning('No countries identified for import after logic check.');
        }

        // 1. Process Full Imports (GAP or New)
        foreach ($needsFull as $country) {
            $io->note(sprintf('Gap detected for %s. Executing full country import...', $country->getCode()));
            $countryCode = $country->getCode();
            try {
                $url = sprintf('https://download.geonames.org/export/dump/%s.zip', strtoupper($countryCode));
                $this->importer->importFull($url, [$countryCode]);
                
                // Use direct SQL to update the date, bypassing EM clear/detach issues
                $conn = $this->em->getConnection();
                $countryTable = $this->em->getClassMetadata($this->countryEntityClass)->getTableName();
                $conn->executeStatement(
                    sprintf("UPDATE %s SET last_imported_at = ? WHERE code = ?", $conn->getDatabasePlatform()->quoteIdentifier($countryTable)),
                    [(new \DateTime())->format('Y-m-d H:i:s'), $countryCode]
                );
                
                // CRITICAL: Clear EM and collect garbage after each country
                $this->em->clear();
                gc_collect_cycles();
                if (function_exists('gc_mem_caches')) gc_mem_caches();
                
                $io->success(sprintf('Full import for %s completed.', $countryCode));
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $errorMessage .= "\n" . $e->getTraceAsString();
                }
                $io->error(sprintf('Failed full import for %s: %s', $country->getCode(), $errorMessage));
            }
        }

        // 2. Process Daily Incremental Updates
        if (!empty($needsDaily)) {
            $io->note(sprintf('Syncing daily updates for: %s', implode(', ', $needsDaily)));
            try {
                $this->importer->importDailyUpdates($yesterday, $needsDaily, !empty($allowedLanguages));
                
                $conn = $this->em->getConnection();
                $countryTable = $this->em->getClassMetadata($this->countryEntityClass)->getTableName();
                foreach ($needsDaily as $code) {
                    $conn->executeStatement(
                        sprintf("UPDATE %s SET last_imported_at = ? WHERE code = ?", $conn->getDatabasePlatform()->quoteIdentifier($countryTable)),
                        [(new \DateTime())->format('Y-m-d H:i:s'), $code]
                    );
                }
                $io->success('Daily updates completed.');
            } catch (\Exception $e) {
                $io->error('Failed daily updates: ' . $e->getMessage());
            }
        }

        // 3. Process Admin5 Codes (Global file)
        if ($this->admin5Enabled) {
            $io->note('Syncing Admin5 codes...');
            try {
                $this->importer->importAdmin5('https://download.geonames.org/export/dump/adminCode5.zip', $this->geonameTable);
                $io->success('Admin5 codes synced.');
            } catch (\Exception $e) {
                $io->error('Failed Admin5 sync: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
