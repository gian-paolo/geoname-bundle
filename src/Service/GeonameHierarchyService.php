<?php

namespace Gpp\GeonameBundle\Service;

use Doctrine\DBAL\Connection;
use Gpp\GeonameBundle\Entity\AbstractGeoName;

class GeonameHierarchyService
{
    private string $tableName;
    private string $hierarchyTable;

    public function __construct(
        private Connection $connection,
        string $tableName = 'gpp_geoname',
        string $hierarchyTable = 'gpp_geohierarchy'
    ) {
        $this->tableName = $tableName;
        $this->hierarchyTable = $hierarchyTable;
    }

    /**
     * Set table names if configured differently
     */
    public function setTableNames(string $tableName, string $hierarchyTable): void
    {
        $this->tableName = $tableName;
        $this->hierarchyTable = $hierarchyTable;
    }

    /**
     * Returns the breadcrumbs (ancestors) using recursive CTE on the hierarchy table.
     */
    public function getAncestorsCTE(int $geonameId): array
    {
        $sql = "
            WITH RECURSIVE tree_path AS (
                -- Anchor: the starting node
                SELECT parentid, childid, 0 as level
                FROM {$this->hierarchyTable}
                WHERE childid = :id
                
                UNION ALL
                
                -- Recursive: find parents of parents
                SELECT h.parentid, h.childid, tp.level + 1
                FROM {$this->hierarchyTable} h
                JOIN tree_path tp ON h.childid = tp.parentid
            )
            SELECT tp.*, g.name, g.feature_code 
            FROM tree_path tp
            JOIN {$this->tableName} g ON g.geonameid = tp.parentid
            ORDER BY tp.level DESC
        ";

        return $this->connection->executeQuery($sql, ['id' => $geonameId])->fetchAllAssociative();
    }

    /**
     * Returns the breadcrumbs (ancestors) of a given geoname.
     * Uses CTE for deep hierarchy but can be optimized with admin codes if needed.
     */
    public function getAncestors(int $geonameId, array $extraFields = []): array
    {
        $fields = ['geonameid', 'name', 'feature_class', 'feature_code', 'country_code', 'admin1_code', 'admin2_code'];
        $fields = array_unique(array_merge($fields, $extraFields));
        
        $select = implode(', ', array_map(fn($f) => "t.$f", $fields));
        $selectTp = implode(', ', array_map(fn($f) => "tp.$f", $fields));

        $sql = "
            WITH RECURSIVE tree_path AS (
                SELECT $select, 0 as level
                FROM {$this->tableName} t
                WHERE t.geonameid = :id
                
                UNION ALL
                
                SELECT $select, tp.level + 1
                FROM {$this->tableName} t
                JOIN tree_path tp ON t.geonameid = tp.parent_id -- Assumes a parent_id field exists if using full hierarchy
                -- Note: GeoNames usually links via admin codes, but we can support a parent_id if added
            )
            SELECT * FROM tree_path ORDER BY level DESC
        ";

        // IMPORTANT: In standard GeoNames, there is NO 'parent_id' column.
        // The hierarchy is defined in a separate table or inferred by admin codes.
        // Let's implement the 'GeoNames way': resolving via codes and hierarchy table.
        
        return $this->resolveAncestorsViaCodes($geonameId, $fields);
    }

    /**
     * Resolves hierarchy using GeoNames administrative codes.
     * Fast for ADM1, ADM2, ADM3, ADM4 levels.
     */
    private function resolveAncestorsViaCodes(int $geonameId, array $fields): array
    {
        // 1. Get the target node
        $qb = $this->connection->createQueryBuilder();
        $target = $qb->select('*')
            ->from($this->tableName)
            ->where('geonameid = :id')
            ->setParameter('id', $geonameId)
            ->executeQuery()
            ->fetchAssociative();

        if (!$target) return [];

        $ancestors = [];
        $country = $target['country_code'];
        $a1 = $target['admin1_code'];
        $a2 = $target['admin2_code'];
        $a3 = $target['admin3_code'];

        // Logic: find parents by looking for ADM levels with matching codes
        // Country -> ADM1 -> ADM2 -> ADM3 -> ADM4
        
        $levels = [];
        if ($target['feature_code'] === 'PPL' || str_starts_with($target['feature_code'], 'PPL')) {
            // It's a city/place, look for its administrative parents
            $levels = [
                ['ADM3', $a3],
                ['ADM2', $a2],
                ['ADM1', $a1],
                ['PCLI', null] // Country
            ];
        } elseif ($target['feature_code'] === 'ADM3') {
            $levels = [['ADM2', $a2], ['ADM1', $a1], ['PCLI', null]];
        } elseif ($target['feature_code'] === 'ADM2') {
            $levels = [['ADM1', $a1], ['PCLI', null]];
        } elseif ($target['feature_code'] === 'ADM1') {
            $levels = [['PCLI', null]];
        }

        foreach ($levels as [$fCode, $val]) {
            if ($fCode !== 'PCLI' && empty($val)) continue;

            $aqb = $this->connection->createQueryBuilder();
            $aqb->select('*')->from($this->tableName)->where('country_code = :cc');
            
            if ($fCode === 'PCLI') {
                $aqb->andWhere('feature_code = :fcode')->setParameter('fcode', 'PCLI');
            } else {
                $aqb->andWhere('feature_code = :fcode')->setParameter('fcode', $fCode);
                // To be precise, ADM2 needs ADM1 to be unique
                if ($fCode === 'ADM2') {
                    $aqb->andWhere('admin1_code = :a1')->setParameter('a1', $a1);
                    $aqb->andWhere('admin2_code = :val')->setParameter('val', $val);
                } elseif ($fCode === 'ADM3') {
                    $aqb->andWhere('admin1_code = :a1')->setParameter('a1', $a1);
                    $aqb->andWhere('admin2_code = :a2')->setParameter('a2', $a2);
                    $aqb->andWhere('admin3_code = :val')->setParameter('val', $val);
                } else {
                    $aqb->andWhere('admin1_code = :val')->setParameter('val', $val);
                }
            }

            $parent = $aqb->setParameter('cc', $country)->executeQuery()->fetchAssociative();
            if ($parent) {
                $ancestors[] = $parent;
            }
        }

        return array_reverse($ancestors);
    }

    /**
     * Returns all descendants (e.g., all cities in a province).
     */
    public function getDescendants(int $geonameId, ?string $featureClass = 'P'): array
    {
        // 1. Get the parent node to know its codes
        $parent = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('geonameid = :id')
            ->setParameter('id', $geonameId)
            ->executeQuery()
            ->fetchAssociative();

        if (!$parent) return [];

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where('country_code = :cc')
            ->setParameter('cc', $parent['country_code']);

        // Optimization: use codes instead of recursive CTE if possible
        if ($parent['feature_code'] === 'ADM1') {
            $qb->andWhere('admin1_code = :a1')->setParameter('a1', $parent['admin1_code']);
        } elseif ($parent['feature_code'] === 'ADM2') {
            $qb->andWhere('admin1_code = :a1')->andWhere('admin2_code = :a2')
                ->setParameter('a1', $parent['admin1_code'])
                ->setParameter('a2', $parent['admin2_code']);
        }

        if ($featureClass) {
            $qb->andWhere('feature_class = :fc')->setParameter('fc', $featureClass);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }
}
