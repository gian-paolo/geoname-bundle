<?php

namespace Pallari\GeonameBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\ArrayParameterType;

class GeonameSearchService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $geonameTable,
        private readonly string $admin1Table,
        private readonly string $admin2Table,
        private readonly string $admin3Table,
        private readonly string $admin4Table,
        private readonly string $admin5Table,
        private readonly bool $useFulltext = false
    ) {}

    /**
     * Search for toponyms with optional administrative names and coordinates.
     *
     * Options:
     * - countries: array of country codes (e.g. ['IT', 'FR'])
     * - feature_classes: array of feature classes (e.g. ['P', 'A'])
     * - limit: max results (default 10)
     * - with_admin_names: join with admin1/admin2 tables to get names (Piemonte, Torino)
     * - min_population: filter by population
     */
    public function search(string $term, array $options = []): array
    {
        $term = trim($term);
        if (strlen($term) < 3) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $platformClass = strtolower(get_class($this->connection->getDatabasePlatform()));
        
        // Base selection
        $qb->select('g.geonameid', 'g.name', 'g.ascii_name', 'g.country_code', 'g.latitude', 'g.longitude', 'g.population', 'g.feature_code', 'g.admin5_code');
        $qb->from($this->geonameTable, 'g');
        $qb->where('g.is_deleted = 0');

        // Text search strategy
        if ($this->useFulltext) {
            if (str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb')) {
                // MySQL/MariaDB Full-Text Search
                $qb->andWhere('MATCH(g.name, g.ascii_name, g.alternate_names) AGAINST(:term IN BOOLEAN MODE)');
                $qb->setParameter('term', $term . '*');
            } elseif (str_contains($platformClass, 'postgresql')) {
                // PostgreSQL Full-Text Search (simple configuration for multi-language)
                $qb->andWhere("to_tsvector('simple', g.name || ' ' || g.ascii_name || ' ' || g.alternate_names) @@ to_tsquery('simple', :term)");
                $qb->setParameter('term', $term . ':*');
            } else {
                // Fallback for other platforms
                $this->applyLikeSearch($qb, $term);
            }
        } else {
            $this->applyLikeSearch($qb, $term);
        }

        // Optional: Join Admin Names
        if ($options['with_admin_names'] ?? false) {
            $qb->addSelect('a1.name as admin1_name', 'a1.geonameid as admin1_id', 
                          'a2.name as admin2_name', 'a2.geonameid as admin2_id',
                          'a3.name as admin3_name', 'a3.geonameid as admin3_id',
                          'a4.name as admin4_name', 'a4.geonameid as admin4_id',
                          'a5.name as admin5_name', 'a5.geonameid as admin5_id');
            
            // Join Admin1 (Region)
            $qb->leftJoin('g', $this->admin1Table, 'a1', 
                'a1.country_code = g.country_code AND a1.admin1_code = g.admin1_code');
            
            // Join Admin2 (Province)
            $qb->leftJoin('g', $this->admin2Table, 'a2', 
                'a2.country_code = g.country_code AND a2.admin1_code = g.admin1_code AND a2.admin2_code = g.admin2_code');

            // Join Admin3 (Municipality/Comune)
            $qb->leftJoin('g', $this->admin3Table, 'a3', 
                'a3.country_code = g.country_code AND a3.admin1_code = g.admin1_code AND a3.admin2_code = g.admin2_code AND a3.admin3_code = g.admin3_code');

            // Join Admin4
            $qb->leftJoin('g', $this->admin4Table, 'a4', 
                'a4.country_code = g.country_code AND a4.admin1_code = g.admin1_code AND a4.admin2_code = g.admin2_code AND a4.admin3_code = g.admin3_code AND a4.admin4_code = g.admin4_code');

            // Join Admin5
            $qb->leftJoin('g', $this->admin5Table, 'a5', 
                'a5.country_code = g.country_code AND a5.admin1_code = g.admin1_code AND a5.admin2_code = g.admin2_code AND a5.admin3_code = g.admin3_code AND a5.admin4_code = g.admin4_code AND a5.admin5_code = g.admin5_code');
        }

        // Filters
        if (!empty($options['countries'])) {
            $qb->andWhere('g.country_code IN (:countries)')
               ->setParameter('countries', $options['countries'], ArrayParameterType::STRING);
        }

        if (!empty($options['feature_classes'])) {
            $qb->andWhere('g.feature_class IN (:fclasses)')
               ->setParameter('fclasses', $options['feature_classes'], ArrayParameterType::STRING);
        }

        if (isset($options['min_population'])) {
            $qb->andWhere('g.population >= :minpop')
               ->setParameter('minpop', $options['min_population']);
        }

        // Sorting: by population (desc) to get main cities first
        $qb->orderBy('g.population', 'DESC');
        $qb->addOrderBy('g.name', 'ASC');

        // Safety limit: clamp requested limit between 1 and 500
        $limit = isset($options['limit']) ? (int)$options['limit'] : 10;
        $limit = max(1, min(500, $limit));
        
        $qb->setMaxResults($limit);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    private function applyLikeSearch(QueryBuilder $qb, string $term): void
    {
        $qb->andWhere(
            $qb->expr()->or(
                'g.name LIKE :term',
                'g.ascii_name LIKE :term',
                'g.alternate_names LIKE :term'
            )
        )->setParameter('term', $term . '%');
    }
}
