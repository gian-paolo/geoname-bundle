<?php

namespace Pallari\GeonameBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Pallari\GeonameBundle\Entity\AbstractGeoImport;
use Pallari\GeonameBundle\Entity\AbstractGeoName;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

class GeonameImporter
{
    private string $geonameEntityClass;
    private string $importEntityClass;
    private string $alternateNameEntityClass;
    private string $importTableName;
    private string $hierarchyTableName = 'geohierarchy';
    private string $alternateNameTableName = 'geoalternatename';
    private array $adminTableNames = [];

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private GeonameParser $parser,
        private string $tmpDir
    ) {}

    public function setEntityClasses(string $geonameEntityClass, string $importEntityClass, string $alternateNameEntityClass = ''): void
    {
        $this->geonameEntityClass = $geonameEntityClass;
        $this->importEntityClass = $importEntityClass;
        $this->alternateNameEntityClass = $alternateNameEntityClass;
    }

    public function setAdminTableNames(array $tables): void
    {
        $this->adminTableNames = $tables;
    }

    public function setTableNames(string $importTableName, string $hierarchyTableName = 'geohierarchy', string $alternateNameTableName = 'geoalternatename'): void
    {
        $this->importTableName = $importTableName;
        $this->hierarchyTableName = $hierarchyTableName;
        $this->alternateNameTableName = $alternateNameTableName;
    }

    public function importFull(string $url, ?array $allowedCountries = null): void
    {
        $this->disableLogging();
        $importLog = $this->createImportLog('full_import', $url);
        
        try {
            $filePath = $this->downloadFile($url);
            if (str_ends_with($url, '.zip')) {
                $filePath = $this->unzip($filePath);
            }

            $totalProcessed = 0;
            foreach ($this->parser->getBatches($filePath, 1000) as $batch) {
                $totalProcessed += $this->processHybridBatch($batch, $allowedCountries);
                $this->updateImportLog($importLog, $totalProcessed);
            }

            $this->completeImportLog($importLog, $totalProcessed);
            if (file_exists($filePath)) unlink($filePath);
        } catch (\Throwable $e) {
            $this->failImportLog($importLog, $e->getMessage());
            throw $e;
        }
    }

    public function importHierarchy(string $url): int
    {
        $this->disableLogging();
        $filePath = $this->downloadFile($url);
        if (str_ends_with($url, '.zip')) {
            $filePath = $this->unzip($filePath);
        }

        $total = 0;
        $conn = $this->em->getConnection();

        foreach ($this->parser->getBatches($filePath, 1000) as $batch) {
            $values = [];
            $placeholders = [];
            foreach ($batch as $row) {
                if (count($row) < 2) continue;
                
                $placeholders[] = '(?, ?, ?)';
                $values[] = (int)$row[0]; // parentid
                $values[] = (int)$row[1]; // childid
                $values[] = $row[2] ?? null; // type
                $total++;
            }

            if (empty($placeholders)) continue;

            $sql = sprintf(
                "INSERT IGNORE INTO `%s` (parentid, childid, type) VALUES %s",
                $this->hierarchyTableName,
                implode(', ', $placeholders)
            );
            $conn->executeStatement($sql, $values);
        }
        
        if (file_exists($filePath)) unlink($filePath);
        return $total;
    }

    public function importAlternateNames(string $url, ?array $allowedLanguages = null): void
    {
        $filePath = $this->downloadFile($url);
        if (str_ends_with($url, '.zip')) {
            $filePath = $this->unzip($filePath);
        }

        foreach ($this->parser->getBatches($filePath, 1000) as $batch) {
            $this->processAlternateNameBatch($batch, $allowedLanguages);
        }

        if (file_exists($filePath)) unlink($filePath);
    }

    private function processAlternateNameBatch(array $batch, ?array $allowedLanguages): int
    {
        $ids = [];
        $dataToProcess = [];

        foreach ($batch as $row) {
            if (count($row) < 4) continue;
            
            $lang = $row[2] ?? '';
            if ($allowedLanguages && !empty($allowedLanguages) && !in_array($lang, $allowedLanguages)) {
                continue;
            }

            $id = (int)$row[0];
            $ids[] = $id;
            $dataToProcess[$id] = [
                'id' => $id,
                'geonameId' => (int)$row[1],
                'isoLanguage' => substr($lang, 0, 7),
                'alternateName' => $row[3],
                'isPreferredName' => ($row[4] ?? '') === '1',
                'isShortName' => ($row[5] ?? '') === '1',
                'isColloquial' => ($row[6] ?? '') === '1',
                'isHistoric' => ($row[7] ?? '') === '1',
            ];
        }

        if (empty($ids)) return 0;

        $existingIds = $this->findExistingIds($this->alternateNameEntityClass, $ids);
        $toUpdate = [];
        $toInsert = [];

        foreach ($dataToProcess as $id => $data) {
            if (in_array($id, $existingIds)) {
                $toUpdate[] = $data;
            } else {
                $toInsert[] = $data;
            }
        }

        $count = 0;
        if (!empty($toInsert)) $count += $this->bulkInsert($this->alternateNameEntityClass, $toInsert);
        if (!empty($toUpdate)) $count += $this->bulkUpdate($this->alternateNameEntityClass, $toUpdate, 'id');

        return $count;
    }

    public function importDailyUpdates(\DateTimeInterface $date, array $allowedCountries, bool $withAlternateNames = false): void
    {
        $dateStr = $date->format('Y-m-d');
        
        // 1. Deletes
        $deleteUrl = sprintf('https://download.geonames.org/export/dump/deletes-%s.txt', $dateStr);
        $this->importIncremental($deleteUrl, 'daily_delete', [], true);

        // 2. Modifications
        $modUrl = sprintf('https://download.geonames.org/export/dump/modifications-%s.txt', $dateStr);
        $this->importIncremental($modUrl, 'daily_modification', $allowedCountries);

        // 3. Alternate Names Updates
        if ($withAlternateNames && $this->alternateNameEntityClass) {
            $altModUrl = sprintf('https://download.geonames.org/export/dump/alternateNamesModifications-%s.txt', $dateStr);
            $this->importIncremental($altModUrl, 'daily_alternate_modification', [], false, true);

            $altDelUrl = sprintf('https://download.geonames.org/export/dump/alternateNamesDeletes-%s.txt', $dateStr);
            $this->importIncremental($altDelUrl, 'daily_alternate_delete', [], true, true);
        }
    }

    public function importAdminCodes(string $url, string $entityClass): int
    {
        $this->disableLogging();
        $filePath = $this->downloadFile($url);
        $total = 0;
        $conn = $this->em->getConnection();
        
        // Find which admin level table to use
        $tableName = null;
        if (str_contains($url, 'admin1Codes')) $tableName = $this->adminTableNames['adm1'] ?? null;
        elseif (str_contains($url, 'admin2Codes')) $tableName = $this->adminTableNames['adm2'] ?? null;

        if (!$tableName) {
            $metadata = $this->em->getClassMetadata($entityClass);
            $tableName = $metadata->getTableName();
        }

        foreach ($this->parser->getBatches($filePath, 500) as $batch) {
            $values = [];
            $placeholders = [];
            foreach ($batch as $row) {
                if (count($row) < 4) continue;
                
                $placeholders[] = '(?, ?, ?, ?)';
                $values[] = $row[0]; // code
                $values[] = $row[1]; // name
                $values[] = $row[2]; // asciiname
                $values[] = (int)$row[3]; // geonameid
                $total++;
            }

            if (empty($placeholders)) continue;

            $sql = sprintf(
                "INSERT INTO `%s` (code, name, asciiname, geonameid) VALUES %s 
                 ON DUPLICATE KEY UPDATE name = VALUES(name), asciiname = VALUES(asciiname), geonameid = VALUES(geonameid)",
                $tableName,
                implode(', ', $placeholders)
            );
            
            $conn->executeStatement($sql, $values);
        }
        
        unlink($filePath);
        return $total;
    }

    public function importAdmin5(string $url, string $tableName): int
    {
        $this->disableLogging();
        $filePath = $this->downloadFile($url);
        if (str_ends_with($url, '.zip')) {
            $filePath = $this->unzip($filePath);
        }

        $total = 0;
        $conn = $this->em->getConnection();

        foreach ($this->parser->getBatches($filePath, 2000) as $batch) {
            $sql = "UPDATE `{$tableName}` SET admin5_code = CASE geonameid ";
            $ids = [];
            foreach ($batch as $row) {
                if (count($row) < 2) continue;
                $id = (int)$row[0];
                $code = $row[1];
                $sql .= "WHEN {$id} THEN " . $conn->quote($code) . " ";
                $ids[] = $id;
                $total++;
            }
            $sql .= "END WHERE geonameid IN (" . implode(',', $ids) . ")";
            
            if (!empty($ids)) {
                $conn->executeStatement($sql);
            }
        }

        if (file_exists($filePath)) unlink($filePath);
        return $total;
    }

    private function importIncremental(string $url, string $type, array $allowedCountries = [], bool $isDelete = false, bool $isAlternate = false): void
    {
        $importLog = $this->createImportLog($type, $url);
        
        try {
            $filePath = $this->downloadFile($url);
            $totalProcessed = 0;
            foreach ($this->parser->getBatches($filePath, 1000) as $batch) {
                if ($isAlternate) {
                    if ($isDelete) {
                        $totalProcessed += $this->processAlternateDeleteBatch($batch);
                    } else {
                        $totalProcessed += $this->processAlternateNameBatch($batch, null);
                    }
                } else {
                    if ($isDelete) {
                        $totalProcessed += $this->processDeleteBatch($batch);
                    } else {
                        $totalProcessed += $this->processHybridBatch($batch, $allowedCountries);
                    }
                }
                $this->updateImportLog($importLog, $totalProcessed);
            }
            $this->completeImportLog($importLog, $totalProcessed);
            if (file_exists($filePath)) unlink($filePath);
        } catch (\Throwable $e) {
            $this->failImportLog($importLog, $e->getMessage());
        }
    }

    private function processHybridBatch(array $batch, ?array $allowedCountries): int
    {
        $toProcess = [];
        $ids = [];

        foreach ($batch as $row) {
            if (count($row) < 19) continue;
            
            $countryCode = strtoupper($row[8] ?? '');
            if ($allowedCountries !== null && !empty($allowedCountries) && !in_array($countryCode, $allowedCountries, true)) {
                continue;
            }

            $data = $this->mapRowToData($row);
            $toProcess[$data['id']] = $data;
            $ids[] = $data['id'];
        }

        if (empty($ids)) return 0;

        $existingIds = $this->findExistingIds($this->geonameEntityClass, $ids);
        
        $toUpdate = [];
        $toInsert = [];

        foreach ($toProcess as $id => $data) {
            if (in_array($id, $existingIds)) {
                $toUpdate[] = $data;
            } else {
                $toInsert[] = $data;
            }
        }

        $count = 0;
        if (!empty($toInsert)) $count += $this->bulkInsert($this->geonameEntityClass, $toInsert);
        if (!empty($toUpdate)) $count += $this->bulkUpdate($this->geonameEntityClass, $toUpdate, 'id');

        $this->syncAdminTablesFromBatch($toProcess);

        return $count;
    }

    private function syncAdminTablesFromBatch(array $batch): void
    {
        if (empty($this->adminTableNames)) return;

        $conn = $this->em->getConnection();
        $adminData = [
            'ADM1' => [],
            'ADM2' => [],
            'ADM3' => [],
            'ADM4' => [],
            'ADM5' => [],
        ];

        foreach ($batch as $data) {
            $fCode = $data['featureCode'];
            if (!isset($adminData[$fCode])) continue;

            $code = $data['countryCode'];
            if ($fCode === 'ADM1') {
                $code .= '.' . $data['admin1Code'];
            } elseif ($fCode === 'ADM2') {
                $code .= '.' . $data['admin1Code'] . '.' . $data['admin2Code'];
            } elseif ($fCode === 'ADM3') {
                $code .= '.' . $data['admin1Code'] . '.' . $data['admin2Code'] . '.' . $data['admin3Code'];
            } elseif ($fCode === 'ADM4') {
                $code .= '.' . $data['admin1Code'] . '.' . $data['admin2Code'] . '.' . $data['admin3Code'] . '.' . $data['admin4Code'];
            } elseif ($fCode === 'ADM5') {
                // For ADM5, use admin5_code if available, otherwise fallback (Admin5 codes are less standardized)
                $code .= '.' . $data['admin1Code'] . '.' . $data['admin2Code'] . '.' . $data['admin3Code'] . '.' . $data['admin4Code'] . '.' . ($data['admin5Code'] ?? $data['id']);
            }

            $adminData[$fCode][] = [
                'code' => $code,
                'name' => $data['name'],
                'asciiname' => $data['asciiname'],
                'geonameid' => $data['id']
            ];
        }

        foreach ($adminData as $level => $rows) {
            $tableName = $this->adminTableNames[strtolower($level)] ?? null;
            if (!$tableName || empty($rows)) continue;

            $values = [];
            $placeholders = [];
            foreach ($rows as $row) {
                $placeholders[] = '(?, ?, ?, ?)';
                $values[] = $row['code'];
                $values[] = $row['name'];
                $values[] = $row['asciiname'];
                $values[] = $row['geonameid'];
            }

            $sql = sprintf(
                "INSERT INTO `%s` (code, name, asciiname, geonameid) VALUES %s 
                 ON DUPLICATE KEY UPDATE name = VALUES(name), asciiname = VALUES(asciiname), geonameid = VALUES(geonameid)",
                $tableName,
                implode(', ', $placeholders)
            );
            $conn->executeStatement($sql, $values);
        }
    }

    private function processDeleteBatch(array $batch): int
    {
        $ids = [];
        foreach ($batch as $row) {
            if (count($row) < 1) continue;
            $ids[] = (int)$row[0];
        }

        if (empty($ids)) return 0;

        $deleteData = array_map(fn($id) => ['id' => $id, 'isDeleted' => true], $ids);
        return $this->bulkUpdate($this->geonameEntityClass, $deleteData, 'id');
    }

    private function processAlternateDeleteBatch(array $batch): int
    {
        $ids = [];
        foreach ($batch as $row) {
            if (count($row) < 1) continue;
            $ids[] = (int)$row[0];
        }

        if (empty($ids)) return 0;

        $conn = $this->em->getConnection();
        $tableName = $this->alternateNameTableName;
        
        $sql = sprintf("DELETE FROM `%s` WHERE alternatenameid IN (?)", $tableName);
        return $conn->executeStatement($sql, [$ids], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
    }

    private function mapRowToData(array $row): array
    {
        $modificationDate = null;
        if ($row[18] !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $row[18]);
            $modificationDate = $date ?: null;
        }

        return [
            'id' => (int)$row[0],
            'name' => substr($row[1], 0, 200),
            'asciiname' => substr($row[2], 0, 200),
            'alternatenames' => substr($row[3], 0, 10000),
            'latitude' => (float)$row[4],
            'longitude' => (float)$row[5],
            'featureClass' => $row[6] !== '' ? $row[6] : null,
            'featureCode' => $row[7] !== '' ? substr($row[7], 0, 10) : null,
            'countryCode' => $row[8] !== '' ? $row[8] : null,
            'admin1Code' => substr($row[10], 0, 20),
            'admin2Code' => substr($row[11], 0, 80),
            'admin3Code' => substr($row[12], 0, 20),
            'admin4Code' => substr($row[13], 0, 20),
            'population' => $row[14] !== '' ? $row[14] : null,
            'elevation' => $row[15] !== '' ? (int)$row[15] : null,
            'timezone' => substr($row[17], 0, 40),
            'modificationDate' => $modificationDate,
            'isDeleted' => false
        ];
    }

    private function createImportLog(string $type, string $url): AbstractGeoImport
    {
        $log = new $this->importEntityClass();
        $log->setType($type);
        $log->setStatus(AbstractGeoImport::STATUS_RUNNING);
        $log->setDetails("Source: $url");
        $this->em->persist($log);
        $this->em->flush();
        return $log;
    }

    private function updateImportLog(AbstractGeoImport $log, int $count): void
    {
        $this->em->getConnection()->executeStatement(
            sprintf("UPDATE `%s` SET records_processed = ? WHERE id = ?", $this->importTableName),
            [$count, $log->getId()]
        );
    }

    private function completeImportLog(AbstractGeoImport $log, int $count): void
    {
        $this->em->getConnection()->executeStatement(
            sprintf("UPDATE `%s` SET status = ?, records_processed = ?, ended_at = ? WHERE id = ?", $this->importTableName),
            [AbstractGeoImport::STATUS_COMPLETED, $count, (new \DateTime())->format('Y-m-d H:i:s'), $log->getId()]
        );
    }

    private function failImportLog(AbstractGeoImport $log, string $error): void
    {
        if (!$this->em->isOpen()) return;
        $this->em->getConnection()->executeStatement(
            sprintf("UPDATE `%s` SET status = ?, error_message = ?, ended_at = ? WHERE id = ?", $this->importTableName),
            [AbstractGeoImport::STATUS_FAILED, $error, (new \DateTime())->format('Y-m-d H:i:s'), $log->getId()]
        );
    }

    private function disableLogging(): void
    {
        $config = $this->em->getConnection()->getConfiguration();
        if (method_exists($config, 'setSQLLogger')) {
            $config->setSQLLogger(null);
        }
        if (method_exists($config, 'setMiddlewares')) {
            $config->setMiddlewares([]);
        }
    }

    private function downloadFile(string $url): string
    {
        if (!is_dir($this->tmpDir)) mkdir($this->tmpDir, 0777, true);
        $tempFile = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('geoname_', true);
        
        $response = $this->httpClient->request('GET', $url);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("Failed to download: " . $response->getStatusCode());
        }
        $fileHandler = fopen($tempFile, 'w');
        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);
        return $tempFile;
    }

    private function unzip(string $zipPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $extractDir = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('extract_', true);
            mkdir($extractDir);
            $zip->extractTo($extractDir);
            $zip->close();
            unlink($zipPath);
            $files = glob($extractDir . DIRECTORY_SEPARATOR . '*.txt');
            return $files[0];
        }
        throw new \RuntimeException('Failed to unzip file');
    }

    private function bulkInsert(string $entityClass, array $rows, int $chunkSize = 1000): int
    {
        if (empty($rows)) return 0;

        $conn = $this->em->getConnection();
        $metadata = $this->em->getClassMetadata($entityClass);
        $tableName = $metadata->getTableName();
        
        $columnMap = $this->getColumnMap($metadata);
        $columns = array_values($columnMap);
        
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

    private function bulkUpdate(string $entityClass, array $rows, string $pkField = 'id', int $chunkSize = 1000): int
    {
        if (empty($rows)) return 0;

        $conn = $this->em->getConnection();
        $metadata = $this->em->getClassMetadata($entityClass);
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

    private function getColumnMap(ClassMetadata $metadata): array
    {
        $map = [];
        foreach ($metadata->getFieldNames() as $fieldName) {
            $map[$fieldName] = $metadata->getColumnName($fieldName);
        }
        return $map;
    }

    private function findExistingIds(string $entityClass, array $ids): array
    {
        $conn = $this->em->getConnection();
        $metadata = $this->em->getClassMetadata($entityClass);
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
