<?php

namespace Pallari\GeonameBundle\Service;

use Doctrine\DBAL\Connection;

class GeonameHierarchyService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $geonameTable,
        private readonly string $hierarchyTable
    ) {}

    /**
     * Returns the full path from the root to the given geonameId.
     */
    public function getAncestors(int $geonameId, array $fields = ['*'], bool $forceHierarchy = false): array
    {
        $target = $this->connection->createQueryBuilder()
            ->select('*') // Get all fields immediately to avoid double query
            ->from($this->geonameTable)
            ->where('geonameid = :id')
            ->setParameter('id', $geonameId)
            ->executeQuery()
            ->fetchAssociative();

        if (!$target) return [];

        // If it's a standard admin level (1-4) or a city, try the fast way first
        if (!$forceHierarchy && preg_match('/^(ADM[1-4]|PPL)/', $target['feature_code'])) {
            return $this->resolveAncestorsViaCodes($target, $fields);
        }

        // Fallback or explicit request for hierarchy table (needed for ADM5+)
        return $this->resolveAncestorsViaHierarchy($geonameId, $fields);
    }

    private function resolveAncestorsViaHierarchy(int $geonameId, array $fields): array
    {
        $selectFields = implode(', ', array_map(fn($f) => "g.$f", $fields));
        
        $sql = "
            WITH RECURSIVE tree AS (
                SELECT parentid, childid, 1 as depth
                FROM {$this->hierarchyTable}
                WHERE childid = :id
                
                UNION ALL
                
                SELECT h.parentid, h.childid, t.depth + 1
                FROM {$this->hierarchyTable} h
                JOIN tree t ON h.childid = t.parentid
            )
            SELECT DISTINCT $selectFields, t.depth
            FROM tree t
            JOIN {$this->geonameTable} g ON t.parentid = g.geonameid
            ORDER BY t.depth DESC
        ";

        return $this->connection->executeQuery($sql, ['id' => $geonameId])->fetchAllAssociative();
    }

    private function resolveAncestorsViaCodes(array $target, array $fields): array
    {
        $country = $target['country_code'];
        $a1 = $target['admin1_code'];
        $a2 = $target['admin2_code'];
        $a3 = $target['admin3_code'];
        $a4 = $target['admin4_code'];

        $levels = [];
        $fCode = $target['feature_code'];

        if ($fCode === 'PCLI') return [];

        $levels[] = ['PCLI', null];

        if (str_starts_with($fCode, 'PPL') || $fCode === 'ADM4') {
            if ($a1) $levels[] = ['ADM1', $a1];
            if ($a2) $levels[] = ['ADM2', $a2];
            if ($a3) $levels[] = ['ADM3', $a3];
            if ($a4 && $fCode !== 'ADM4') $levels[] = ['ADM4', $a4];
        } elseif ($fCode === 'ADM3') {
            if ($a1) $levels[] = ['ADM1', $a1];
            if ($a2) $levels[] = ['ADM2', $a2];
        } elseif ($fCode === 'ADM2') {
            if ($a1) $levels[] = ['ADM1', $a1];
        }

        if (empty($levels)) return [];

        $qb = $this->connection->createQueryBuilder();
        $qb->select(implode(', ', $fields))->from($this->geonameTable);

        $conditions = [];
        foreach ($levels as $index => [$levelFCode, $val]) {
            $levelConditions = [
                'country_code = ' . $qb->createNamedParameter($country, \PDO::PARAM_STR, ":cc_$index"),
                'feature_code = ' . $qb->createNamedParameter($levelFCode, \PDO::PARAM_STR, ":fcode_$index")
            ];

            if ($levelFCode === 'ADM2') {
                $levelConditions[] = 'admin1_code = ' . $qb->createNamedParameter($a1, \PDO::PARAM_STR, ":a1_$index");
                $levelConditions[] = 'admin2_code = ' . $qb->createNamedParameter($val, \PDO::PARAM_STR, ":val_$index");
            } elseif ($levelFCode === 'ADM3') {
                $levelConditions[] = 'admin1_code = ' . $qb->createNamedParameter($a1, \PDO::PARAM_STR, ":a1_$index");
                $levelConditions[] = 'admin2_code = ' . $qb->createNamedParameter($a2, \PDO::PARAM_STR, ":a2_$index");
                $levelConditions[] = 'admin3_code = ' . $qb->createNamedParameter($val, \PDO::PARAM_STR, ":val_$index");
            } elseif ($levelFCode === 'ADM4') {
                $levelConditions[] = 'admin1_code = ' . $qb->createNamedParameter($a1, \PDO::PARAM_STR, ":a1_$index");
                $levelConditions[] = 'admin2_code = ' . $qb->createNamedParameter($a2, \PDO::PARAM_STR, ":a2_$index");
                $levelConditions[] = 'admin3_code = ' . $qb->createNamedParameter($a3, \PDO::PARAM_STR, ":a3_$index");
                $levelConditions[] = 'admin4_code = ' . $qb->createNamedParameter($val, \PDO::PARAM_STR, ":val_$index");
            } elseif ($levelFCode === 'ADM1') {
                $levelConditions[] = 'admin1_code = ' . $qb->createNamedParameter($val, \PDO::PARAM_STR, ":val_$index");
            }

            $conditions[] = '(' . implode(' AND ', $levelConditions) . ')';
        }

        $qb->where(implode(' OR ', $conditions));
        $results = $qb->executeQuery()->fetchAllAssociative();

        $order = ['PCLI' => 1, 'ADM1' => 2, 'ADM2' => 3, 'ADM3' => 4, 'ADM4' => 5];
        usort($results, fn($a, $b) => ($order[$a['feature_code']] ?? 99) <=> ($order[$b['feature_code']] ?? 99));

        return $results;
    }

    public function getDescendants(int $geonameId, ?string $featureClass = 'P'): array
    {
        return [];
    }
}
