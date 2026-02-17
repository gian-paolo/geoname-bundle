<?php

namespace Pallari\GeonameBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\ArrayParameterType;

class GeonameSearchService
{
    public const PRESET_MINIMAL = ['geonameid', 'name'];
    public const PRESET_GEO = ['geonameid', 'name', 'latitude', 'longitude'];
    public const PRESET_FULL = ['geonameid', 'name', 'ascii_name', 'country_code', 'latitude', 'longitude', 'population', 'feature_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'admin5_code', 'timezone', 'modification_date'];

    public function __construct(
        private readonly Connection $connection,
        private readonly string $geonameTable,
        private readonly string $admin1Table,
        private readonly string $admin2Table,
        private readonly string $admin3Table,
        private readonly string $admin4Table,
        private readonly string $admin5Table,
        private readonly bool $useFulltext = false,
        private readonly int $maxResults = 100
    ) {}

    /**
     * Search for toponyms with optional administrative names and coordinates.
     *
     * Options:
     * - select: array of columns or a PRESET_* constant (default: PRESET_FULL)
     * - countries: array of country codes (e.g. ['IT', 'FR'])
     * - feature_classes: array of feature classes (e.g. ['P', 'A'])
     * - feature_codes: array of feature codes (e.g. ['PPL', 'ADM1'])
     * - limit: max results (default from config)
     * - with_admin_names: join with admin1/admin2 tables to get names
     * - min_population: filter by population
     * - id: filter by geonameid
     * - order_by: 'population_desc' (default), 'name_asc', 'relevance'
     */
    public function search(string $term, array $options = []): array
    {
        $term = trim($term);
        
        // Validation: Search term must be at least 3 characters if no other primary filters are present
        $hasPrimaryFilter = isset($options['id']) || !empty($options['countries']) || isset($options['admin1_code']);
        if ($term !== '' && strlen($term) < 3 && !$hasPrimaryFilter) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $platformClass = strtolower(get_class($this->connection->getDatabasePlatform()));
        
        // 1. Column Selection
        $select = $options['select'] ?? self::PRESET_FULL;
        if (!is_array($select)) {
            $select = self::PRESET_FULL;
        }
        
        foreach ($select as $column) {
            $qb->addSelect('g.' . $column);
        }

        $qb->from($this->geonameTable, 'g');
        $qb->where('g.is_deleted = 0');

        // 2. Hybrid Search Strategy
        if ($term !== '') {
            $orConditions = [
                'g.name LIKE :prefix',
                'g.ascii_name LIKE :prefix'
            ];
            $qb->setParameter('prefix', $term . '%');

            if ($this->useFulltext && strlen($term) >= 3) {
                if (str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb')) {
                    $orConditions[] = 'MATCH(g.name, g.ascii_name, g.alternate_names) AGAINST(:ft_term IN BOOLEAN MODE)';
                    $qb->setParameter('ft_term', $term . '*');
                } elseif (str_contains($platformClass, 'postgresql')) {
                    $orConditions[] = "to_tsvector('simple', g.name || ' ' || g.ascii_name || ' ' || g.alternate_names) @@ to_tsquery('simple', :ft_term)";
                    $qb->setParameter('ft_term', $term . ':*');
                }
            } else {
                $orConditions[] = 'g.alternate_names LIKE :prefix';
            }

            $qb->andWhere($qb->expr()->or(...$orConditions));
        }

        // 3. Optional: Join Admin Names
        if ($options['with_admin_names'] ?? false) {
            $this->applyAdminJoins($qb);
        }

        // 4. Filters
        if (!empty($options['countries'])) {
            $qb->andWhere('g.country_code IN (:countries)')
               ->setParameter('countries', $options['countries'], ArrayParameterType::STRING);
        }

        if (!empty($options['feature_classes'])) {
            $qb->andWhere('g.feature_class IN (:fclasses)')
               ->setParameter('fclasses', $options['feature_classes'], ArrayParameterType::STRING);
        }

        if (!empty($options['feature_codes'])) {
            $qb->andWhere('g.feature_code IN (:fcodes)')
               ->setParameter('fcodes', $options['feature_codes'], ArrayParameterType::STRING);
        }

        if (isset($options['min_population'])) {
            $qb->andWhere('g.population >= :minpop')
               ->setParameter('minpop', $options['min_population']);
        }

        if (isset($options['id'])) {
            $qb->andWhere('g.geonameid = :id')
               ->setParameter('id', $options['id']);
        }

        // Parent code filters for hierarchy navigation
        foreach (['admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'admin5_code'] as $adminLevel) {
            if (isset($options[$adminLevel])) {
                $qb->andWhere("g.$adminLevel = :$adminLevel")
                   ->setParameter($adminLevel, $options[$adminLevel]);
            }
        }

        // 5. Sorting
        $orderBy = $options['order_by'] ?? 'population_desc';
        
        if ($orderBy === 'name_asc') {
            $qb->orderBy('g.name', 'ASC');
        } elseif ($orderBy === 'relevance' && $this->useFulltext && strlen($term) >= 3) {
            if (str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb')) {
                $qb->addSelect('MATCH(g.name, g.ascii_name, g.alternate_names) AGAINST(:ft_term IN BOOLEAN MODE) as score');
                $qb->orderBy('score', 'DESC');
            } else {
                $qb->orderBy('g.population', 'DESC');
            }
        } else {
            // Default: population
            $qb->orderBy('g.population', 'DESC');
            $qb->addOrderBy('g.name', 'ASC');
        }

        // 6. Limits (Clamped between 1 and 1000)
        $limit = $options['limit'] ?? $this->maxResults;
        $limit = max(1, min(1000, (int)$limit));
        $qb->setMaxResults($limit);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get a single toponym by its geonameid.
     */
    public function getById(int $id, bool $withAdminNames = false, array $select = self::PRESET_FULL): ?array
    {
        $results = $this->search('', [
            'id' => $id,
            'with_admin_names' => $withAdminNames,
            'select' => $select,
            'limit' => 1
        ]);
        
        return $results[0] ?? null;
    }

    /**
     * Get children or descendants by administrative codes.
     */
    public function getChildren(string $countryCode, array $parentCodes = [], array $options = []): array
    {
        $searchOptions = array_merge([
            'countries' => [$countryCode],
            'order_by' => 'name_asc',
            'limit' => $this->maxResults
        ], $options);

        foreach ($parentCodes as $level => $code) {
            if (in_array($level, ['admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'admin5_code'])) {
                $searchOptions[$level] = $code;
            }
        }

        return $this->search('', $searchOptions);
    }

    /**
     * Get all descendants (children, grandchildren, etc.) of a parent toponym.
     * Automatically resolves administrative codes from the parent ID.
     */
    public function getDescendantsByParentId(int $parentId, array $options = []): array
    {
        // 1. Get parent data to identify its administrative location
        $parent = $this->getById($parentId, false, ['country_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'feature_code', 'feature_class']);
        
        if (!$parent) {
            return [];
        }

        // 2. Build filter catena based on parent's type
        $parentCodes = [];
        $fCode = $parent['feature_code'] ?? '';
        $fClass = $parent['feature_class'] ?? '';

        // If it's a country, we only filter by country_code (already handled by getChildren)
        // If it's an administrative division, we add the corresponding codes
        if ($fClass === 'A') {
            if ($fCode === 'ADM1') {
                $parentCodes['admin1_code'] = $parent['admin1_code'];
            } elseif ($fCode === 'ADM2') {
                $parentCodes['admin1_code'] = $parent['admin1_code'];
                $parentCodes['admin2_code'] = $parent['admin2_code'];
            } elseif ($fCode === 'ADM3') {
                $parentCodes['admin1_code'] = $parent['admin1_code'];
                $parentCodes['admin2_code'] = $parent['admin2_code'];
                $parentCodes['admin3_code'] = $parent['admin3_code'];
            } elseif ($fCode === 'ADM4') {
                $parentCodes['admin1_code'] = $parent['admin1_code'];
                $parentCodes['admin2_code'] = $parent['admin2_code'];
                $parentCodes['admin3_code'] = $parent['admin3_code'];
                $parentCodes['admin4_code'] = $parent['admin4_code'];
            }
        }

        return $this->getChildren($parent['country_code'], $parentCodes, $options);
    }

    private function applyAdminJoins(QueryBuilder $qb): void
    {
        $qb->addSelect('a1.name as admin1_name', 'a1.geonameid as admin1_id', 
                      'a2.name as admin2_name', 'a2.geonameid as admin2_id',
                      'a3.name as admin3_name', 'a3.geonameid as admin3_id',
                      'a4.name as admin4_name', 'a4.geonameid as admin4_id',
                      'a5.name as admin5_name', 'a5.geonameid as admin5_id');
        
        $qb->leftJoin('g', $this->admin1Table, 'a1', 
            'a1.country_code = g.country_code AND a1.admin1_code = g.admin1_code');
        
        $qb->leftJoin('g', $this->admin2Table, 'a2', 
            'a2.country_code = g.country_code AND a2.admin1_code = g.admin1_code AND a2.admin2_code = g.admin2_code');

        $qb->leftJoin('g', $this->admin3Table, 'a3', 
            'a3.country_code = g.country_code AND a3.admin1_code = g.admin1_code AND a2.admin2_code = g.admin2_code AND a3.admin3_code = g.admin3_code');

        $qb->leftJoin('g', $this->admin4Table, 'a4', 
            'a4.country_code = g.country_code AND a4.admin1_code = g.admin1_code AND a2.admin2_code = g.admin2_code AND a3.admin3_code = g.admin3_code AND a4.admin4_code = g.admin4_code');

        $qb->leftJoin('g', $this->admin5Table, 'a5', 
            'a5.country_code = g.country_code AND a5.admin1_code = g.admin1_code AND a2.admin2_code = g.admin2_code AND a3.admin3_code = g.admin3_code AND a4.admin4_code = g.admin4_code AND a5.admin5_code = g.admin5_code');
    }
}
