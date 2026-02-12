<?php

namespace Pallari\GeonameBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'pallari:geoname:install',
    description: 'Interactive installer for GeonameBundle',
)]
class InstallCommand extends Command
{
    private const ENTITIES = [
        'GeoName' => 'AbstractGeoName',
        'GeoCountry' => 'AbstractGeoCountry',
        'GeoLanguage' => 'AbstractGeoLanguage',
        'GeoImport' => 'AbstractGeoImport',
        'GeoAdmin1' => 'AbstractGeoAdmin1',
        'GeoAdmin2' => 'AbstractGeoAdmin2',
        'GeoAdmin3' => 'AbstractGeoAdmin3',
        'GeoAdmin4' => 'AbstractGeoAdmin4',
        'GeoAlternateName' => 'AbstractGeoAlternateName',
        'GeoHierarchy' => 'AbstractGeoHierarchy',
    ];

    public function __construct(
        private readonly string $projectDir,
        private readonly EntityManagerInterface $em,
        private readonly string $countryEntityClass,
        private readonly string $languageEntityClass
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🌍 GeonameBundle - Interactive Installer');

        // 1. Generate Entities
        if ($io->confirm('Step 1: Generate entity files in src/Entity/?', true)) {
            $this->generateEntities($io);
        }

        // 2. Update Schema
        if ($io->confirm('Step 2: Create/Update database schema?', true)) {
            $this->runCommand('doctrine:schema:update', ['--force' => true], $output);
            $io->success('Database schema updated.');
        }

        // 3. Initial Data
        if ($io->confirm('Step 3: Enable initial countries and languages?', true)) {
            $this->setupInitialData($io);
        }

        // 4. Run First Sync
        if ($io->confirm('Step 4: Run initial synchronization now? (This may take a while)', false)) {
            $this->runCommand('pallari:geoname:import-admin-codes', [], $output);
            $this->runCommand('pallari:geoname:sync', [], $output);
        }

        $io->success('Installation completed successfully!');
        return Command::SUCCESS;
    }

    private function generateEntities(SymfonyStyle $io): void
    {
        $fs = new Filesystem();
        $entityDir = $this->projectDir . '/src/Entity';

        if (!$fs->exists($entityDir)) {
            $fs->mkdir($entityDir);
        }

        foreach (self::ENTITIES as $className => $abstractName) {
            $filePath = $entityDir . '/' . $className . '.php';
            if ($fs->exists($filePath)) {
                $io->note(sprintf('File %s already exists, skipping.', $className));
                continue;
            }

            $content = <<<PHP
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pallari\GeonameBundle\Entity\{$abstractName};

#[ORM\Entity]
class {$className} extends {$abstractName}
{
}
PHP;
            $fs->dumpFile($filePath, $content);
            $io->writeln(sprintf(' <info>✔</info> Created src/Entity/%s.php', $className));
        }
    }

    private function setupInitialData(SymfonyStyle $io): void
    {
        $countries = $io->ask('Enter country codes to enable (comma separated, e.g. IT,US,FR)', 'IT');
        $languages = $io->ask('Enter search languages to enable (comma separated, e.g. it,en)', 'it,en');

        foreach (explode(',', $countries) as $code) {
            $code = strtoupper(trim($code));
            if (empty($code)) continue;
            
            $country = new $this->countryEntityClass();
            $country->setCode($code);
            $country->setName($code); // Temporary name, sync will update it
            $country->setIsEnabled(true);
            $this->em->persist($country);
        }

        foreach (explode(',', $languages) as $lang) {
            $lang = strtolower(trim($lang));
            if (empty($lang)) continue;

            $language = new $this->languageEntityClass();
            $language->setCode($lang);
            $language->setName(strtoupper($lang));
            $language->setIsEnabled(true);
            $this->em->persist($language);
        }

        $this->em->flush();
        $io->success('Initial settings saved to database.');
    }

    private function runCommand(string $name, array $params, OutputInterface $output): void
    {
        $command = $this->getApplication()->find($name);
        $input = new ArrayInput($params);
        $command->run($input, $output);
    }
}
