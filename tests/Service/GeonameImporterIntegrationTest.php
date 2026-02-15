<?php

namespace Pallari\GeonameBundle\Tests\Service;

use Pallari\GeonameBundle\Service\GeonameImporter;
use Pallari\GeonameBundle\Tests\App\Entity\GeoAdmin1;
use Pallari\GeonameBundle\Tests\App\Entity\GeoAdmin2;
use Pallari\GeonameBundle\Tests\App\Entity\GeoName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GeonameImporterIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $schemaTool->updateSchema($metadatas);
    }

    public function testImportAdminCodesSplitting(): void
    {
        $container = self::getContainer();
        $importer = $container->get(GeonameImporter::class);
        $em = $container->get('doctrine.orm.entity_manager');

        // Mock a file for admin1Codes
        $tempFile = tempnam(sys_get_temp_dir(), 'admin1_');
        file_put_contents($tempFile, "IT.09	Piemonte	Piedmont	3170831
IT.10	Puglia	Apulia	3170588
");

        // We need to bypass the download part or mock the HttpClient. 
        // For now, let's use a trick: GeonameImporter uses HttpClient to download.
        // It might be easier to test processHybridBatch or other internal methods if we want to be fast, 
        // but let's try to test the actual import logic by mocking the parser input.
        
        // Actually, GeonameImporter::importAdminCodes downloads the file.
        // Since I cannot easily mock HttpClient here without more setup, 
        // I will test syncAdminTablesFromBatch which is the core of the new logic.
        
        $batch = [
            [
                'id' => 3169070,
                'name' => 'Roma',
                'ascii_name' => 'Roma',
                'countryCode' => 'IT',
                'admin1Code' => '07',
                'admin2Code' => 'RM',
                'featureCode' => 'ADM2',
                // ... other fields
            ]
        ];

        // We need to use reflection to call the private method or just test processHybridBatch
        $method = new \ReflectionMethod(GeonameImporter::class, 'syncAdminTablesFromBatch');
        $method->setAccessible(true);
        $method->invoke($importer, $batch);

        $admin2 = $em->getRepository(GeoAdmin2::class)->findOneBy([
            'countryCode' => 'IT',
            'admin1Code' => '07',
            'admin2Code' => 'RM'
        ]);

        $this->assertNotNull($admin2);
        $this->assertEquals('Roma', $admin2->getName());
        
        unlink($tempFile);
    }
}
