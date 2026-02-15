<?php

namespace Pallari\GeonameBundle\Tests\Service;

use Pallari\GeonameBundle\Service\GeonameSearchService;
use Pallari\GeonameBundle\Tests\App\Entity\GeoAdmin1;
use Pallari\GeonameBundle\Tests\App\Entity\GeoAdmin2;
use Pallari\GeonameBundle\Tests\App\Entity\GeoName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GeonameSearchIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $schemaTool->dropSchema($metadatas);
        $schemaTool->createSchema($metadatas);
    }

    public function testSearchWithCompositeJoins(): void
    {
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $searchService = $container->get(GeonameSearchService::class);

        // 1. Create Region (Admin1)
        $admin1 = new GeoAdmin1();
        $admin1->setCountryCode('IT');
        $admin1->setAdmin1Code('09');
        $admin1->setName('Piemonte');
        $em->persist($admin1);

        // 2. Create Province (Admin2)
        $admin2 = new GeoAdmin2();
        $admin2->setCountryCode('IT');
        $admin2->setAdmin1Code('09');
        $admin2->setAdmin2Code('TO');
        $admin2->setName('Torino');
        $em->persist($admin2);

        // 3. Create City (GeoName)
        $city = new GeoName();
        $city->setId(3165524);
        $city->setName('Torino');
        $city->setAsciiName('Torino');
        $city->setCountryCode('IT');
        $city->setAdmin1Code('09');
        $city->setAdmin2Code('TO');
        $city->setLatitude(45.07);
        $city->setLongitude(7.68);
        $em->persist($city);

        $em->flush();

        // 4. Perform Search
        $results = $searchService->search('Torino', [
            'countries' => ['IT'],
            'with_admin_names' => true
        ]);

        // 5. Verify Results
        $this->assertCount(1, $results);
        $result = $results[0];
        
        $this->assertEquals('Torino', $result['name']);
        $this->assertEquals('Piemonte', $result['admin1_name']);
        $this->assertEquals('Torino', $result['admin2_name']);
    }
}
