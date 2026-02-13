<?php

namespace Pallari\GeonameBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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
        'GeoAdmin5' => 'AbstractGeoAdmin5',
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

        // Detect project context
        $isTestEnv = str_contains($this->projectDir, 'tests/App');
        $defaultNamespace = $isTestEnv ? 'Pallari\GeonameBundle\Tests\App\Entity' : 'App\Entity';
        $defaultEntityDir = $this->projectDir . ($isTestEnv ? '/Entity' : '/src/Entity');

        $namespace = $io->ask('Entity namespace?', $defaultNamespace);
        $entityDir = $io->ask('Entity directory?', $defaultEntityDir);

        // 1. Generate Config YAML
        if ($io->confirm('Step 1: Generate configuration file?', true)) {
            $this->generateConfig($io, $namespace);
        }

        // 2. Generate Entities
        if ($io->confirm('Step 2: Generate entity files?', true)) {
            $this->generateEntities($io, $namespace, $entityDir);
        }

        // 3. Update Schema
        if ($io->confirm('Step 3: Create/Update database schema?', true)) {
            $this->updateSchema($io);
        }

        // 4. Initial Data
        if ($io->confirm('Step 4: Enable initial countries and languages?', true)) {
            $this->setupInitialData($io);
        }

        // 5. Run First Sync
        if ($io->confirm('Step 5: Run initial synchronization now? (This may take a while)', false)) {
            try {
                $this->runCommand('pallari:geoname:import-admin-codes', [], $output);
                $this->runCommand('pallari:geoname:sync', [], $output);
            } catch (\Exception $e) {
                $io->error('Synchronization failed: ' . $e->getMessage());
            }
        }

        $io->success('Installation completed successfully!');
        return Command::SUCCESS;
    }

    private function generateConfig(SymfonyStyle $io, string $namespace): void
    {
        $fs = new Filesystem();
        
        // Try to find the correct config directory
        $configDir = $this->projectDir . '/config/packages';
        if (!$fs->exists($configDir)) {
            $fs->mkdir($configDir);
        }
        
        $configFile = $configDir . '/pallari_geoname.yaml';

        if ($fs->exists($configFile)) {
            if (!$io->confirm('Configuration file already exists. Overwrite?', false)) {
                return;
            }
        }

        $useFulltext = $io->confirm('Enable Full-Text search? (Requires MySQL/PostgreSQL)', false);
        $fulltextString = $useFulltext ? 'true' : 'false';

        $namespace = rtrim($namespace, '\\');

        $content = <<<YAML
pallari_geoname:
    # Entities mapping
    entities:
        geoname: '$namespace\GeoName'
        country: '$namespace\GeoCountry'
        language: '$namespace\GeoLanguage'
        import: '$namespace\GeoImport'
        admin1: '$namespace\GeoAdmin1'
        admin2: '$namespace\GeoAdmin2'
        admin3: '$namespace\GeoAdmin3'
        admin4: '$namespace\GeoAdmin4'
        admin5: '$namespace\GeoAdmin5'
        alternate_name: '$namespace\GeoAlternateName'
        hierarchy: '$namespace\GeoHierarchy'

    # Performance options
    search:
        use_fulltext: $fulltextString

    # Optional features
    alternate_names:
        enabled: true
    admin5:
        enabled: false

    # Database naming
    table_prefix: 'geoname_'
YAML;

        $fs->dumpFile($configFile, $content);
        $io->writeln(sprintf(' <info>✔</info> Created %s', $configFile));
    }

    private function generateEntities(SymfonyStyle $io, string $namespace, string $entityDir): void
    {
        $fs = new Filesystem();

        if (!$fs->exists($entityDir)) {
            $fs->mkdir($entityDir);
        }

        $namespace = rtrim($namespace, '\\');

        foreach (self::ENTITIES as $className => $abstractName) {
            $filePath = $entityDir . '/' . $className . '.php';
            if ($fs->exists($filePath)) {
                $io->note(sprintf('File %s already exists, skipping.', $className));
                continue;
            }

            $content = <<<PHP
<?php

namespace $namespace;

use Doctrine\ORM\Mapping as ORM;
use Pallari\GeonameBundle\Entity\\$abstractName;

#[ORM\Entity]
class $className extends $abstractName
{
}
PHP;
            $fs->dumpFile($filePath, $content);
            $io->writeln(sprintf(' <info>✔</info> Created %s', $filePath));
        }
    }

    private function updateSchema(SymfonyStyle $io): void
    {
        $tool = new SchemaTool($this->em);
        $metadatas = $this->em->getMetadataFactory()->getAllMetadata();
        
        if (empty($metadatas)) {
            $io->warning('No metadata found. Make sure your entities are correctly configured and registered.');
            return;
        }

        $tool->updateSchema($metadatas, true);
        $io->success('Database schema updated using SchemaTool.');
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
