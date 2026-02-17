<?php

namespace Pallari\GeonameBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\ArrayParameterType;

/**
 * High-performance service for searching and navigating GeoNames data.
 * 
 * Provides hybrid text search (LIKE + Full-Text), geospatial proximity queries,
 * and administrative hierarchy navigation.
 * 
 * @author Gian-Paolo Pallari
 */
class GeonameSearchService
{
    /** Minimal columns: only ID and Name */
    public const PRESET_MINIMAL = ['geonameid', 'name'];
    
    /** Geospatial columns: ID, Name and Coordinates */
    public const PRESET_GEO = ['geonameid', 'name', 'latitude', 'longitude'];
    
    /** All available columns from the main geoname table */
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
     * Search for toponyms using a hybrid strategy (Prefix LIKE + Full-Text).
     *
     * @param string $term    The search string (min 3 chars if no other filters are applied).
     * @param array  $options {
     *     Optional search criteria:
     *     @var array  $select            List of columns to return or PRESET_* constant.
     *     @var array  $countries         Array of 2-letter ISO country codes (e.g. ['IT', 'FR']).
     *     @var array  $feature_classes   Array of feature classes (e.g. ['P' for cities, 'A' for admin).
     *     @var array  $feature_codes     Array of specific feature codes (e.g. ['PPL', 'ADM1']).
     *     @var int    $limit             Maximum number of results (1-1000).
     *     @var bool   $with_admin_names  If true, joins and returns names for all admin levels.
     *     @var int    $min_population    Minimum population filter.
     *     @var int    $id                Filter by specific geonameid.
     *     @var string $admin1_code       Filter by ADM1 code (Region).
     *     @var string $admin2_code       Filter by ADM2 code (Province).
     *     @var string $admin3_code       Filter by ADM3 code (Municipality).
     *     @var string $order_by          'population_desc' (default), 'name_asc', 'relevance'.
     * }
     * @return array List of associative arrays matching the search.
     */
    public function search(string $term, array $options = []): array
    {
        $term = trim($term);
        
        $hasPrimaryFilter = isset($options['id']) || !empty($options['countries']) || isset($options['admin1_code']);
        if ($term !== '' && strlen($term) < 3 && !$hasPrimaryFilter) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $platformClass = strtolower(get_class($this->connection->getDatabasePlatform()));
        
        $select = $options['select'] ?? self::PRESET_FULL;
        if (!is_array($select)) {
            $select = self::PRESET_FULL;
        }
        
        foreach ($select as $column) {
            $qb->addSelect('g.' . $column);
        }

        $qb->from($this->geonameTable, 'g');
        $qb->where('g.is_deleted = 0');

        if ($term !== '') {
            $orConditions = ['g.name LIKE :prefix', 'g.ascii_name LIKE :prefix'];
            $qb->setParameter('prefix', $term . '%');

            if ($this->useFulltext && strlen($term) >= 3) {
                if (str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb')) {
                    $orConditions[] = 'MATCH(g.name, g.alternate_names) AGAINST(:ft_term IN BOOLEAN MODE)';
                    $qb->setParameter('ft_term', $term . '*');
                } elseif (str_contains($platformClass, 'postgresql')) {
                    $orConditions[] = "to_tsvector('simple', g.name || ' ' || COALESCE(g.alternate_names, '')) @@ to_tsquery('simple', :ft_term)";
                    $qb->setParameter('ft_term', $term . ':*');
                }
            } else {
                $orConditions[] = 'g.alternate_names LIKE :prefix';
            }
            $qb->andWhere($qb->expr()->or(...$orConditions));
        }

        if ($options['with_admin_names'] ?? false) {
            $this->applyAdminJoins($qb);
        }

        if (!empty($options['countries'])) {
            $qb->andWhere('g.country_code IN (:countries)')->setParameter('countries', $options['countries'], ArrayParameterType::STRING);
        }

        if (!empty($options['feature_classes'])) {
            $qb->andWhere('g.feature_class IN (:fclasses)')->setParameter('fclasses', $options['feature_classes'], ArrayParameterType::STRING);
        }

        if (!empty($options['feature_codes'])) {
            $qb->andWhere('g.feature_code IN (:fcodes)')->setParameter('fcodes', $options['feature_codes'], ArrayParameterType::STRING);
        }

        if (isset($options['min_population'])) {
            $qb->andWhere('g.population >= :minpop')->setParameter('minpop', $options['min_population']);
        }

        if (isset($options['id'])) {
            $qb->andWhere('g.geonameid = :id')->setParameter('id', $options['id']);
        }

        foreach (['admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'admin5_code'] as $adminLevel) {
            if (isset($options[$adminLevel])) {
                $qb->andWhere("g.$adminLevel = :$adminLevel")->setParameter($adminLevel, $options[$adminLevel]);
            }
        }

        $orderBy = $options['order_by'] ?? 'population_desc';
        if ($orderBy === 'name_asc') {
            $qb->orderBy('g.name', 'ASC');
        } elseif ($orderBy === 'relevance' && $this->useFulltext && strlen($term) >= 3) {
            if (str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb')) {
                $qb->addSelect('MATCH(g.name, g.alternate_names) AGAINST(:ft_term IN BOOLEAN MODE) as score');
                $qb->orderBy('score', 'DESC');
            } else {
                $qb->orderBy('g.population', 'DESC');
            }
        } else {
            $qb->orderBy('g.population', 'DESC');
            $qb->addOrderBy('g.name', 'ASC');
        }

        $limit = $options['limit'] ?? $this->maxResults;
        $limit = max(1, min(1000, (int)$limit));
        $qb->setMaxResults($limit);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Retrieve a single record by its geonameid.
     *
     * @param int   $id               The unique geonameid.
     * @param bool  $withAdminNames   Whether to resolve and include administrative labels.
     * @param array $select           Columns to return (defaults to PRESET_FULL).
     * @return array|null The record as associative array, or null if not found.
     */
    public function getById(int $id, bool $withAdminNames = false, array $select = self::PRESET_FULL): ?array
    {
        $results = $this->search('', ['id' => $id, 'with_admin_names' => $withAdminNames, 'select' => $select, 'limit' => 1]);
        return $results[0] ?? null;
    }

    /**
     * Build an ordered administrative chain (breadcrumbs) for a toponym.
     *
     * Result example:
     * [
     *   ['name' => 'Italy', 'geonameid' => 3175395],
     *   ['name' => 'Piedmont', 'geonameid' => 3170831],
     *   ['name' => 'Turin', 'geonameid' => 3165524]
     * ]
     *
     * @param int $id The geonameid of the target place.
     * @return array List of parent units including the item itself.
     */
    public function getBreadcrumbs(int $id): array
    {
        $item = $this->getById($id, false, ['geonameid', 'name', 'country_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'admin5_code', 'feature_code']);
        if (!$item) return [];

        $crumbs = [];
        
        $adminLevels = [
            ['table' => $this->admin1Table, 'code' => $item['admin1_code'], 'fields' => ['country_code', 'admin1_code']],
            ['table' => $this->admin2Table, 'code' => $item['admin2_code'], 'fields' => ['country_code', 'admin1_code', 'admin2_code']],
            ['table' => $this->admin3Table, 'code' => $item['admin3_code'], 'fields' => ['country_code', 'admin1_code', 'admin2_code', 'admin3_code']],
            ['table' => $this->admin4Table, 'code' => $item['admin4_code'], 'fields' => ['country_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code']],
            ['table' => $this->admin5Table, 'code' => $item['admin5_code'], 'fields' => ['country_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'admin5_code']],
        ];

        foreach ($adminLevels as $level) {
            if (empty($level['code']) || $level['code'] === '00') continue;

            $qb = $this->connection->createQueryBuilder();
            $qb->select('name', 'geonameid')->from($level['table']);
            foreach ($level['fields'] as $field) {
                $qb->andWhere("$field = :$field")->setParameter($field, $item[$field]);
            }
            
            $adminData = $qb->executeQuery()->fetchAssociative();
            if ($adminData) {
                if ((int)$adminData['geonameid'] === (int)$item['geonameid']) break;
                $crumbs[] = $adminData;
            }
        }

        $crumbs[] = ['name' => $item['name'], 'geonameid' => $item['geonameid']];

        return $crumbs;
    }

    /**
     * Find places nearest to a given GPS coordinate (Reverse Geocoding).
     * Uses Haversine formula for spherical distance calculation.
     *
     * @param float $lat     Latitude.
     * @param float $lon     Longitude.
     * @param array $options {
     *     @var int   $limit            Max results.
     *     @var array $select           Columns to return.
     *     @var array $feature_classes  Filter by class (e.g. ['P']).
     * }
     * @return array List of records including a 'distance' field (in KM).
     */
    public function findNearest(float $lat, float $lon, array $options = []): array
    {
        $qb = $this->connection->createQueryBuilder();
        $select = $options['select'] ?? self::PRESET_GEO;
        foreach ($select as $column) { $qb->addSelect('g.' . $column); }

        $qb->addSelect('(6371 * acos(cos(radians(:lat)) * cos(radians(g.latitude)) * cos(radians(g.longitude) - radians(:lon)) + sin(radians(:lat)) * sin(radians(g.latitude)))) AS distance');
        $qb->from($this->geonameTable, 'g');
        $qb->where('g.is_deleted = 0');
        $qb->setParameter('lat', $lat);
        $qb->setParameter('lon', $lon);

        if (!empty($options['feature_classes'])) {
            $qb->andWhere('g.feature_class IN (:fclasses)')->setParameter('fclasses', $options['feature_classes'], ArrayParameterType::STRING);
        }

        $qb->orderBy('distance', 'ASC');
        $qb->setMaxResults($options['limit'] ?? 10);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Find places within a rectangular geographic area.
     *
     * @param float $north   North latitude bound.
     * @param float $east    East longitude bound.
     * @param float $south   South latitude bound.
     * @param float $west    West longitude bound.
     * @param array $options {
     *     @var int   $limit            Max results.
     *     @var array $select           Columns to return.
     *     @var array $feature_classes  Filter by class.
     * }
     * @return array List of records within the bounds.
     */
    public function findInBoundingBox(float $north, float $east, float $south, float $west, array $options = []): array
    {
        $options['select'] = $options['select'] ?? self::PRESET_GEO;
        $qb = $this->connection->createQueryBuilder();
        foreach ($options['select'] as $column) { $qb->addSelect('g.' . $column); }
        
        $qb->from($this->geonameTable, 'g')
           ->where('g.is_deleted = 0')
           ->andWhere('g.latitude <= :north AND g.latitude >= :south')
           ->andWhere('g.longitude <= :east AND g.longitude >= :west')
           ->setParameter('north', $north)
           ->setParameter('south', $south)
           ->setParameter('east', $east)
           ->setParameter('west', $west);

        if (!empty($options['feature_classes'])) {
            $qb->andWhere('g.feature_class IN (:fclasses)')->setParameter('fclasses', $options['feature_classes'], ArrayParameterType::STRING);
        }

        $qb->orderBy('g.population', 'DESC');
        $qb->setMaxResults($options['limit'] ?? 50);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get immediate children or matching records by administrative codes.
     *
     * @param string $countryCode 2-letter country code.
     * @param array  $parentCodes Associative array of codes (e.g. ['admin1_code' => '09']).
     * @param array  $options     Standard search options.
     * @return array List of matching records.
     */
    public function getChildren(string $countryCode, array $parentCodes = [], array $options = []): array
    {
        $searchOptions = array_merge(['countries' => [$countryCode], 'order_by' => 'name_asc', 'limit' => $this->maxResults], $options);
        foreach ($parentCodes as $level => $code) {
            if (in_array($level, ['admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'admin5_code'])) {
                $searchOptions[$level] = $code;
            }
        }
        return $this->search('', $searchOptions);
    }

    /**
     * Get all descendants of a specific parent by its geonameid.
     * Automatically resolves the parent's administrative location.
     *
     * @param int   $parentId The geonameid of the parent place.
     * @param array $options  Standard search options (e.g. feature_codes => ['ADM3']).
     * @return array List of all matching descendants.
     */
    public function getDescendantsByParentId(int $parentId, array $options = []): array
    {
        $parent = $this->getById($parentId, false, ['country_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'feature_code', 'feature_class']);
        if (!$parent) return [];

        $parentCodes = [];
        $fCode = $parent['feature_code'] ?? '';
        $fClass = $parent['feature_class'] ?? '';

        if ($fClass === 'A') {
            if ($fCode === 'ADM1') { $parentCodes['admin1_code'] = $parent['admin1_code']; }
            elseif ($fCode === 'ADM2') { $parentCodes['admin1_code'] = $parent['admin1_code']; $parentCodes['admin2_code'] = $parent['admin2_code']; }
            elseif ($fCode === 'ADM3') { $parentCodes['admin1_code'] = $parent['admin1_code']; $parentCodes['admin2_code'] = $parent['admin2_code']; $parentCodes['admin3_code'] = $parent['admin3_code']; }
            elseif ($fCode === 'ADM4') { $parentCodes['admin1_code'] = $parent['admin1_code']; $parentCodes['admin2_code'] = $parent['admin2_code']; $parentCodes['admin3_code'] = $parent['admin3_code']; $parentCodes['admin4_code'] = $parent['admin4_code']; }
        }

        return $this->getChildren($parent['country_code'], $parentCodes, $options);
    }

    /**
     * Joins administrative tables to the main query to provide name labels.
     */
    private function applyAdminJoins(QueryBuilder $qb): void
    {
        $qb->addSelect('a1.name as admin1_name', 'a1.geonameid as admin1_id', 'a2.name as admin2_name', 'a2.geonameid as admin2_id', 'a3.name as admin3_name', 'a3.geonameid as admin3_id', 'a4.name as admin4_name', 'a4.geonameid as admin4_id', 'a5.name as admin5_name', 'a5.geonameid as admin5_id');
        $qb->leftJoin('g', $this->admin1Table, 'a1', 'a1.country_code = g.country_code AND a1.admin1_code = g.admin1_code');
        $qb->leftJoin('g', $this->admin2Table, 'a2', 'a2.country_code = g.country_code AND a2.admin1_code = g.admin1_code AND a2.admin2_code = g.admin2_code');
        $qb->leftJoin('g', $this->admin3Table, 'a3', 'a3.country_code = g.country_code AND a3.admin1_code = g.admin1_code AND a2.admin2_code = g.admin2_code AND a3.admin3_code = g.admin3_code');
        $qb->leftJoin('g', $this->admin4Table, 'a4', 'a4.country_code = g.country_code AND a4.admin1_code = g.admin1_code AND a2.admin2_code = g.admin2_code AND a3.admin3_code = g.admin3_code AND a4.admin4_code = g.admin4_code');
        $qb->leftJoin('g', $this->admin5Table, 'a5', 'a5.country_code = g.country_code AND a5.admin1_code = g.admin1_code AND a5.admin2_code = g.admin2_code AND a3.admin3_code = g.admin3_code AND a4.admin4_code = g.admin4_code AND a5.admin5_code = g.admin5_code');
    }
}
