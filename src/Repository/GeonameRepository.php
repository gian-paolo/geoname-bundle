<?php

namespace Pallari\GeonameBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Optimized repository for GeoNames data operations.
 */
class GeonameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * Efficiently inserts multiple rows using raw SQL.
     * Clean multi-row INSERT.
     */
    public function bulkInsert(array $rows, int $chunkSize = 1000): int
    {
        if (empty($rows)) return 0;

        $conn = $this->getEntityManager()->getConnection();
        $metadata = $this->getClassMetadata();
        $tableName = $metadata->getTableName();
        
        $columnMap = $this->getColumnMap($metadata);
        $columns = array_values($columnMap);
        
        // Wrap column names with quotes/backticks based on platform
        $platform = $conn->getDatabasePlatform();
        $quotedColumns = array_map(fn($c) => $platform->quoteIdentifier($c), $columns);
        $columnsSql = implode(', ', $quotedColumns);

        $totalInserted = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $placeholders = [];
            $params = [];

            foreach ($chunk as $row) {
                $rowPlaceholders = [];
                foreach ($columnMap as $prop => $col) {
                    $val = $row[$prop] ?? null;
                    if ($val instanceof \DateTimeInterface) {
                        $val = $val->format('Y-m-d H:i:s');
                    } elseif (is_bool($val)) {
                        $val = $val ? 1 : 0;
                    }
                    $params[] = $val;
                    $rowPlaceholders[] = '?';
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $platform->quoteIdentifier($tableName),
                $columnsSql,
                implode(', ', $placeholders)
            );

            $totalInserted += $conn->executeStatement($sql, $params);
        }

        return $totalInserted;
    }

    /**
     * Efficiently updates multiple rows using CASE WHEN SQL syntax.
     */
    public function bulkUpdate(array $rows, string $pkField = 'id', int $chunkSize = 1000): int
    {
        if (empty($rows)) return 0;

        $conn = $this->getEntityManager()->getConnection();
        $metadata = $this->getClassMetadata();
        $tableName = $metadata->getTableName();
        $columnMap = $this->getColumnMap($metadata);
        
        if (!isset($columnMap[$pkField])) {
            throw new \InvalidArgumentException("Primary key field '$pkField' not found.");
        }
        $pkColumn = $columnMap[$pkField];

        $platform = $conn->getDatabasePlatform();
        $pkColumnQuoted = $platform->quoteIdentifier($pkColumn);

        $totalUpdated = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $ids = [];
            $setClauses = [];
            
            // Identify columns to update (all except PK)
            $updateCols = $columnMap;
            unset($updateCols[$pkField]);

            foreach ($updateCols as $prop => $col) {
                $colQuoted = $platform->quoteIdentifier($col);
                $setClauses[$col] = "$colQuoted = CASE $pkColumnQuoted ";
            }

            foreach ($chunk as $row) {
                $pkVal = $row[$pkField] ?? null;
                if ($pkVal === null) continue;

                $ids[] = $conn->quote($pkVal);

                foreach ($updateCols as $prop => $col) {
                    $val = $row[$prop] ?? null;
                    if ($val instanceof \DateTimeInterface) {
                        $val = $val->format('Y-m-d H:i:s');
                    } elseif (is_bool($val)) {
                        $val = $val ? 1 : 0;
                    }
                    
                    $quotedVal = ($val === null) ? 'NULL' : $conn->quote($val);
                    $setClauses[$col] .= sprintf("WHEN %s THEN %s ", $conn->quote($pkVal), $quotedVal);
                }
            }

            if (empty($ids)) continue;

            $sqlSet = [];
            foreach ($setClauses as $col => $clause) {
                $sqlSet[] = $clause . " END";
            }

            $sql = sprintf(
                "UPDATE %s SET %s WHERE %s IN (%s)",
                $platform->quoteIdentifier($tableName),
                implode(', ', $sqlSet),
                $pkColumnQuoted,
                implode(', ', $ids)
            );

            $totalUpdated += $conn->executeStatement($sql);
        }

        return $totalUpdated;
    }

    /**
     * Maps property names to database column names.
     */
    private function getColumnMap(ClassMetadata $metadata): array
    {
        $map = [];
        foreach ($metadata->getFieldNames() as $fieldName) {
            $map[$fieldName] = $metadata->getColumnName($fieldName);
        }
        return $map;
    }

    /**
     * Checks which IDs exist in the database.
     */
    public function findExistingIds(array $ids): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $metadata = $this->getClassMetadata();
        $tableName = $metadata->getTableName();
        $pkColumn = $metadata->getColumnName($metadata->getIdentifierFieldNames()[0]);
        $platform = $conn->getDatabasePlatform();

        $sql = sprintf("SELECT %s FROM %s WHERE %s IN (?)", 
            $platform->quoteIdentifier($pkColumn), 
            $platform->quoteIdentifier($tableName), 
            $platform->quoteIdentifier($pkColumn)
        );
        $result = $conn->executeQuery($sql, [$ids], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        
        return $result->fetchFirstColumn();
    }
}
