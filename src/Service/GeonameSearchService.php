<?php

namespace Pallari\GeonameBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class GeonameSearchService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $geonameTable,
        private readonly string $admin1Table,
        private readonly string $admin2Table,
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
        $qb = $this->connection->createQueryBuilder();
        $platform = $this->connection->getDatabasePlatform()->getName();
        
        // Base selection
        $qb->select('g.geonameid', 'g.name', 'g.ascii_name', 'g.country_code', 'g.latitude', 'g.longitude', 'g.population', 'g.feature_code');
        $qb->from($this->geonameTable, 'g');
        $qb->where('g.is_deleted = 0');

        // Text search strategy
        if ($this->useFulltext) {
            if (str_contains($platform, 'mysql') || str_contains($platform, 'mariadb')) {
                // MySQL/MariaDB Full-Text Search
                $qb->andWhere('MATCH(g.name, g.ascii_name, g.alternate_names) AGAINST(:term IN BOOLEAN MODE)');
                $qb->setParameter('term', $term . '*');
            } elseif (str_contains($platform, 'postgresql')) {
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
            $qb->addSelect('a1.name as admin1_name', 'a1.geonameid as admin1_id', 'a2.name as admin2_name', 'a2.geonameid as admin2_id');
            
            // Join Admin1 (Region) - Key format is CC.ADM1
            $qb->leftJoin('g', $this->admin1Table, 'a1', 'a1.code = CONCAT(g.country_code, '.', g.admin1_code)');
            
            // Join Admin2 (Province) - Key format is CC.ADM1.ADM2
            $qb->leftJoin('g', $this->admin2Table, 'a2', 'a2.code = CONCAT(g.country_code, '.', g.admin1_code, '.', g.admin2_code)');
        }

        // Filters
        if (!empty($options['countries'])) {
            $qb->andWhere('g.country_code IN (:countries)')
               ->setParameter('countries', $options['countries'], Connection::PARAM_STR_ARRAY);
        }

        if (!empty($options['feature_classes'])) {
            $qb->andWhere('g.feature_class IN (:fclasses)')
               ->setParameter('fclasses', $options['feature_classes'], Connection::PARAM_STR_ARRAY);
        }

        if (isset($options['min_population'])) {
            $qb->andWhere('g.population >= :minpop')
               ->setParameter('minpop', $options['min_population']);
        }

        // Sorting: by population (desc) to get main cities first
        $qb->orderBy('g.population', 'DESC');
        $qb->addOrderBy('g.name', 'ASC');

        $qb->setMaxResults($options['limit'] ?? 10);

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
