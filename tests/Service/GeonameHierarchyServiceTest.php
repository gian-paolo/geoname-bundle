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
        $expr = new \Doctrine\DBAL\Query\Expression\ExpressionBuilder($connection);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        // Mock the target node lookup (e.g., a city in Turin)
        $targetNode = [
            'geonameid' => 123,
            'country_code' => 'IT',
            'admin1_code' => '01',
            'admin2_code' => 'TO',
            'admin3_code' => 'A123',
            'admin4_code' => null,
            'feature_code' => 'PPL'
        ];

        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('expr')->willReturn($expr);
        
        $queryBuilder->method('executeQuery')->willReturn($result);
        
        // 1st call: target node. 
        $result->expects($this->exactly(1))
            ->method('fetchAssociative')
            ->willReturn($targetNode);

        // 2nd call: all ancestors in one shot
        $result->expects($this->exactly(1))
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'Italy', 'feature_code' => 'PCLI'],
                ['name' => 'Piemonte', 'feature_code' => 'ADM1'],
                ['name' => 'Torino', 'feature_code' => 'ADM2'],
            ]);

        $service = new GeonameHierarchyService($connection, 'geoname', 'geohierarchy');
        $ancestors = $service->getAncestors(123);

        $this->assertCount(3, $ancestors);
        $this->assertEquals('PCLI', $ancestors[0]['feature_code']);
        $this->assertEquals('ADM1', $ancestors[1]['feature_code']);
        $this->assertEquals('ADM2', $ancestors[2]['feature_code']);
    }

    public function testGetAncestorsViaHierarchyTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $queryBuilder = $this->createMock(\Doctrine\DBAL\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $connection->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('executeQuery')->willReturn($result);

        // 1st call: target node
        $result->method('fetchAssociative')
            ->willReturn(['geonameid' => 456, 'feature_code' => 'ADM5']);

        // 2nd call: hierarchy query (recursive CTE)
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with($this->stringContains('WITH RECURSIVE tree'))
            ->willReturn($result);

        $result->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'Italy', 'feature_code' => 'PCLI', 'depth' => 3],
                ['name' => 'Piemonte', 'feature_code' => 'ADM1', 'depth' => 2],
                ['name' => 'Torino', 'feature_code' => 'ADM2', 'depth' => 1],
            ]);

        $service = new GeonameHierarchyService($connection, 'geoname', 'geohierarchy');
        $ancestors = $service->getAncestors(456, ['*'], true); // Force hierarchy

        $this->assertCount(3, $ancestors);
        $this->assertEquals('PCLI', $ancestors[0]['feature_code']);
    }
}
