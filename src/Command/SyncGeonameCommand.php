<?php

namespace Gpp\GeonameBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Gpp\GeonameBundle\Entity\AbstractGeoCountry;
use Gpp\GeonameBundle\Service\GeonameImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'gpp:geoname:sync',
    description: 'Synchronizes GeoNames data (Incremental or Full)',
)]
class SyncGeonameCommand extends Command
{
    private string $countryEntityClass;

    public function __construct(
        private EntityManagerInterface $em,
        private GeonameImporter $importer,
        // These would be injected via configuration in a real bundle
        string $geonameEntityClass = 'App\Entity\GeoName',
        string $importEntityClass = 'App\Entity\DataImport',
        string $countryEntityClass = 'App\Entity\GeoCountry'
    ) {
        parent::__construct();
        $this->importer->setEntityClasses($geonameEntityClass, $importEntityClass);
        $this->countryEntityClass = $countryEntityClass;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('GeoNames Synchronization');

        $countries = $this->em->getRepository($this->countryEntityClass)->findBy(['isEnabled' => true]);

        if (empty($countries)) {
            $io->warning('No enabled countries found.');
            return Command::SUCCESS;
        }

        $yesterday = new \DateTime('yesterday');
        $needsFull = [];
        $needsDaily = [];

        foreach ($countries as $country) {
            $lastImport = $country->getLastImportedAt();
            
            // GAP Detection
            if ($lastImport === null || $lastImport->format('Y-m-d') < $yesterday->format('Y-m-d')) {
                $needsFull[] = $country;
            } else {
                $needsDaily[] = $country->getCode();
            }
        }

        // 1. Process Full Imports (GAP or New)
        foreach ($needsFull as $country) {
            $io->note(sprintf('Gap detected for %s. Executing full country import...', $country->getCode()));
            try {
                $url = sprintf('https://download.geonames.org/export/dump/%s.zip', strtoupper($country->getCode()));
                $this->importer->importFull($url, [$country->getCode()]);
                
                $country->setLastImportedAt(new \DateTime());
                $this->em->flush();
                $io->success(sprintf('Full import for %s completed.', $country->getCode()));
            } catch (\Exception $e) {
                $io->error(sprintf('Failed full import for %s: %s', $country->getCode(), $e->getMessage()));
            }
        }

        // 2. Process Daily Incremental Updates
        if (!empty($needsDaily)) {
            $io->note(sprintf('Syncing daily updates for: %s', implode(', ', $needsDaily)));
            try {
                $this->importer->importDailyUpdates($yesterday, $needsDaily);
                
                foreach ($needsDaily as $code) {
                    $country = $this->em->getRepository($this->countryEntityClass)->find($code);
                    if ($country) {
                        $country->setLastImportedAt(new \DateTime());
                    }
                }
                $this->em->flush();
                $io->success('Daily updates completed.');
            } catch (\Exception $e) {
                $io->error('Failed daily updates: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
