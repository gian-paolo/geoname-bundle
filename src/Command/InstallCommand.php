<?php

namespace Pallari\GeonameBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Pallari\GeonameBundle\Entity\AbstractGeoName;
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

    private const CONTINENT_MAP = [
        'EU' => 'AD,AL,AT,AX,BA,BE,BG,BY,CH,CY,CZ,DE,DK,EE,ES,FI,FO,FR,GB,GG,GI,GR,HR,HU,IE,IM,IS,IT,JE,LI,LT,LU,LV,MC,MD,ME,MK,MT,NL,NO,PL,PT,RO,RS,RU,SE,SI,SJ,SK,SM,UA,VA',
        'NA' => 'CA,US,MX,BS,CU,DO,HT,JM,PA,CR,NI,HN,SV,GT,BZ',
        'SA' => 'AR,BO,BR,CL,CO,EC,FK,GY,PY,PE,SR,UY,VE',
        'AS' => 'AF,AM,AZ,BD,BH,BN,BT,CN,GE,ID,IL,IN,IQ,IR,JO,JP,KG,KH,KP,KR,KW,KZ,LA,LB,LK,MM,MN,MY,NP,OM,PH,PK,PS,QA,SA,SG,SY,TH,TJ,TL,TM,TR,TW,UZ,VN,YE',
        'AF' => 'AO,BF,BI,BJ,BW,CD,CF,CG,CI,CM,CV,DJ,DZ,EG,ER,ET,GA,GH,GM,GN,GQ,GW,KE,KM,LR,LS,LY,MA,MG,ML,MR,MU,MW,MZ,NA,NE,NG,RW,SC,SD,SL,SN,SO,SS,ST,SZ,TD,TG,TN,TZ,UG,ZA,ZM,ZW',
        'OC' => 'AU,FJ,KI,MH,FM,NR,NZ,PW,PG,WS,SB,TO,TV,VU',
    ];

    private bool $fulltextRequested = false;

    public function __construct(
        private readonly string $projectDir,
        private readonly EntityManagerInterface $em,
        private readonly string $geonameEntityClass,
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
        if ($this->confirm($io, 'Step 1: Generate configuration file?', true)) {
            $this->generateConfig($io, $namespace);
        }

        // 2. Generate Entities
        if ($this->confirm($io, 'Step 2: Generate entity files?', true)) {
            $this->generateEntities($io, $namespace, $entityDir);
        }

        // 3. Update Schema
        if ($this->confirm($io, 'Step 3: Create/Update database schema?', true)) {
            $this->updateSchema($io);
        }

        // 4. Initial Data
        if ($this->confirm($io, 'Step 4: Enable initial countries and languages?', true)) {
            $this->setupInitialData($io);
        }

        // 5. Advanced Search Optimization (Full-Text)
        if ($this->fulltextRequested) {
            $this->optimizeDatabase($io);
        }

        // 6. Run First Sync
        if ($this->confirm($io, 'Step 6: Run initial synchronization now? (This may take a while)', false)) {
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

    private function confirm(SymfonyStyle $io, string $question, bool $default): bool
    {
        $label = $default ? ' (Y/n)' : ' (y/N)';
        $answer = $io->ask($question . $label, $default ? 'y' : 'n');
        $answer = strtolower(trim((string)$answer));
        
        if (empty($answer)) {
            return $default;
        }

        return in_array($answer, ['y', 'yes', 'si', 's', '1', 'true']);
    }

    private function generateConfig(SymfonyStyle $io, string $namespace): void
    {
        $fs = new Filesystem();
        $configDir = $this->projectDir . '/config/packages';
        if (!$fs->exists($configDir)) {
            $fs->mkdir($configDir);
        }
        
        $configFile = $configDir . '/pallari_geoname.yaml';

        if ($fs->exists($configFile)) {
            if (!$this->confirm($io, 'Configuration file already exists. Overwrite?', false)) {
                return;
            }
        }

        $this->fulltextRequested = $this->confirm($io, 'Enable Full-Text search? (Adds indexes to DB and enables advanced search features)', true);
        $fulltextString = $this->fulltextRequested ? 'true' : 'false';

        $enableAdmin5 = $this->confirm($io, 'Enable Admin5 support?', false);
        $admin5String = $enableAdmin5 ? 'true' : 'false';

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
        enabled: $admin5String

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
            $io->warning('No metadata found.');
            return;
        }

        $tool->updateSchema($metadatas, true);
        $io->success('Database schema updated.');
    }

    private function setupInitialData(SymfonyStyle $io): void
    {
        $choice = $io->choice('How do you want to select countries to enable?', [
            'manual' => 'Manual entry (comma separated codes)',
            'continents' => 'Select by Continents',
            'all' => 'Enable ALL countries (Warning: very slow synchronization)',
        ], 'manual');

        $enabledCodes = [];
        if ($choice === 'all') {
            foreach (self::CONTINENT_MAP as $codes) {
                $enabledCodes = array_merge($enabledCodes, explode(',', $codes));
            }
        } elseif ($choice === 'manual') {
            $answer = $io->ask('Enter country codes to enable (e.g. IT,US,FR) or "all" to enable everything', 'IT');
            if (strtolower(trim($answer)) === 'all') {
                foreach (self::CONTINENT_MAP as $codes) {
                    $enabledCodes = array_merge($enabledCodes, explode(',', $codes));
                }
            } else {
                $enabledCodes = array_map('trim', explode(',', $answer));
            }
        } else {
            $continentChoices = ['EU' => 'Europe (EU)', 'NA' => 'North America (NA)', 'SA' => 'South America (SA)', 'AS' => 'Asia (AS)', 'AF' => 'Africa (AF)', 'OC' => 'Oceania (OC)'];
            $selectedContinents = $io->choice('Select continents (comma separated)', $continentChoices, null, true);
            foreach ($selectedContinents as $continentKey) {
                $enabledCodes = array_merge($enabledCodes, explode(',', self::CONTINENT_MAP[$continentKey]));
            }
        }

        $existingCountries = $this->em->getRepository($this->countryEntityClass)->findAll();
        $existingCountryCodes = array_map(fn($c) => strtoupper($c->getCode()), $existingCountries);

        foreach ($enabledCodes as $code) {
            $code = strtoupper(trim($code));
            if (empty($code)) continue;
            
            if (strlen($code) > 2) {
                $io->warning(sprintf('Skipping invalid country code: "%s" (must be 2 characters)', $code));
                continue;
            }

            if (in_array($code, $existingCountryCodes)) continue;
            
            $country = new $this->countryEntityClass();
            $country->setCode($code);
            $country->setName($code);
            $country->setIsEnabled(true);
            $this->em->persist($country);
            $existingCountryCodes[] = $code;
        }

        $languagesInput = $io->ask('Enter search languages to enable (comma separated, e.g. it,en)', 'it,en');
        $existingLanguages = $this->em->getRepository($this->languageEntityClass)->findAll();
        $existingLangCodes = array_map(fn($l) => strtolower($l->getCode()), $existingLanguages);

        foreach (explode(',', $languagesInput) as $lang) {
            $lang = strtolower(trim($lang));
            if (empty($lang) || in_array($lang, $existingLangCodes)) continue;
            
            if (strlen($lang) > 7) {
                $io->warning(sprintf('Skipping invalid language code: "%s" (max 7 characters)', $lang));
                continue;
            }

            $language = new $this->languageEntityClass();
            $language->setCode($lang);
            $language->setName(strtoupper($lang));
            $language->setIsEnabled(true);
            $this->em->persist($language);
            $existingLangCodes[] = $lang;
        }

        $this->em->flush();
        $io->success('Initial settings saved to database.');
    }

    private function optimizeDatabase(SymfonyStyle $io): void
    {
        $conn = $this->em->getConnection();
        $platform = $conn->getDatabasePlatform();
        $platformClass = strtolower(get_class($platform));
        
        $geonameMetadata = $this->em->getClassMetadata($this->geonameEntityClass);
        $tableName = $geonameMetadata->getTableName();
        $tableNameQuoted = $platform->quoteIdentifier($tableName);

        $io->section('Advanced Search Optimization');

        try {
            if (str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb')) {
                $io->note('Creating MySQL/MariaDB Full-Text index...');
                $sql = sprintf("ALTER TABLE %s ADD FULLTEXT INDEX idx_geoname_fulltext (name, alternate_names)", $tableNameQuoted);
                $conn->executeStatement($sql);
            } elseif (str_contains($platformClass, 'postgresql')) {
                $io->note('Creating PostgreSQL GIN index...');
                $sql = sprintf("CREATE INDEX idx_geoname_fulltext ON %s USING GIN (to_tsvector('simple', name || ' ' || COALESCE(alternate_names, '')))", $tableNameQuoted);
                $conn->executeStatement($sql);
            } else {
                $io->warning('Full-Text index optimization is not available for this database platform.');
            }
            $io->success('Advanced search optimization completed.');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already exists') || str_contains($e->getMessage(), 'Duplicate key name')) {
                $io->note('Advanced search optimization already exists.');
            } else {
                $io->warning('Search optimization failed: ' . $e->getMessage());
            }
        }
    }

    private function runCommand(string $name, array $params, OutputInterface $output): void
    {
        $command = $this->getApplication()->find($name);
        $input = new ArrayInput($params);
        $command->run($input, $output);
    }
}
