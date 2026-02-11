<?php

namespace Gpp\GeonameBundle\Tests\Service;

use Doctrine\DBAL\Connection;
use Gpp\GeonameBundle\Service\GeonameHierarchyService;
use PHPUnit\Framework\TestCase;

class GeonameHierarchyServiceTest extends TestCase
{
    public function testGetAncestorsViaCodes(): void
    {
        $connection = $this->createMock(Connection::class);
        $queryBuilder = $this->createMock(\Doctrine\DBAL\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        // Mock the target node lookup (e.g., a city in Turin)
        $targetNode = [
            'geonameid' => 123,
            'country_code' => 'IT',
            'admin1_code' => '01',
            'admin2_code' => 'TO',
            'admin3_code' => 'A123',
            'feature_code' => 'PPL'
        ];

        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        
        $queryBuilder->method('executeQuery')->willReturn($result);
        
        // First call returns the city, subsequent calls return parents
        $result->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                $targetNode, // Target city
                ['name' => 'Torino', 'feature_code' => 'ADM2'], // Prov
                ['name' => 'Piemonte', 'feature_code' => 'ADM1'], // Reg
                ['name' => 'Italy', 'feature_code' => 'PCLI'] // Country
            );

        $service = new GeonameHierarchyService($connection, 'geo_name');
        $ancestors = $service->getAncestors(123);

        // We expect Country, Region, Province (reversed)
        $this->assertCount(3, $ancestors);
        $this->assertEquals('PCLI', $ancestors[0]['feature_code']);
        $this->assertEquals('ADM1', $ancestors[1]['feature_code']);
        $this->assertEquals('ADM2', $ancestors[2]['feature_code']);
    }
}
