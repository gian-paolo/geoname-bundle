<?php

namespace Pallari\GeonameBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Pallari\GeonameBundle\Entity\AbstractGeoImport;
use Pallari\GeonameBundle\Entity\AbstractGeoName;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class GeonameImporter
{
    private string $geonameEntityClass;
    private string $importEntityClass;
    private string $alternateNameEntityClass;
    private string $geonameTableName;
    private string $importTableName;
    private string $hierarchyTableName = 'geohierarchy';
    private string $alternateNameTableName = 'geoalternatename';
    private array $adminTableNames = [];
    private ?SymfonyStyle $io = null;
    private ?object $cachedLogger = null;
    private array $memorySnapshots = [];
    private Filesystem $fs;

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private GeonameParser $parser,
        private string $tmpDir,
        private bool $debug = false
    ) {
        $this->fs = new Filesystem();
    }

    public function setOutput(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    public function setEntityClasses(string $geonameEntityClass, string $importEntityClass, string $alternateNameEntityClass = ''): void
    {
        $this->geonameEntityClass = $geonameEntityClass;
        $this->importEntityClass = $importEntityClass;
        $this->alternateNameEntityClass = $alternateNameEntityClass;

        $this->geonameTableName = $this->em->getClassMetadata($geonameEntityClass)->getTableName();
        $this->importTableName = $this->em->getClassMetadata($importEntityClass)->getTableName();
        if ($alternateNameEntityClass) {
            $this->alternateNameTableName = $this->em->getClassMetadata($alternateNameEntityClass)->getTableName();
        }
    }

    public function setAdminTableNames(array $tables): void
    {
        $this->adminTableNames = $tables;
    }

    public function setTableNames(string $geonameTableName, string $importTableName, string $hierarchyTableName = 'geohierarchy', string $alternateNameTableName = 'geoalternatename'): void
    {
        $this->geonameTableName = $geonameTableName;
        $this->importTableName = $importTableName;
        $this->hierarchyTableName = $hierarchyTableName;
        $this->alternateNameTableName = $alternateNameTableName;
    }

    /**
     * Resets cumulative memory monitoring.
     */
    public function resetMemoryMonitoring(): void
    {
        $this->memorySnapshots = [];
    }

    public function importFull(string $url, ?array $allowedCountries = null): void
    {
        $this->disableLogging();
        $importLog = $this->createImportLog('full_import', $url);
        
        $tempFilesToCleanup = [];
        $tempDirsToCleanup = [];

        try {
            $conn = $this->em->getConnection();
            $platform = $conn->getDatabasePlatform();
            if ($this->io) {
                if (!isset($this->memorySnapshots['START'])) {
                    $this->takeMemorySnapshot('START');
                }
            }

            $filePath = $this->downloadFile($url);
            $tempFilesToCleanup[] = $filePath;

            if (str_ends_with($url, '.zip')) {
                $result = $this->unzip($filePath);
                $filePath = $result['file'];
                $tempDirsToCleanup[] = $result['dir'];
            }

            $totalRead = 0; $totalInserted = 0; $totalUpdated = 0; $totalModified = 0; $totalSkipped = 0;
            $startTime = microtime(true); $iteration = 0;
            
            foreach ($this->parser->getBatches($filePath, 500) as $batch) {
                $iteration++;
                $batchStart = microtime(true);
                $totalRead += count($batch);
                
                $results = $this->processHybridBatch($batch, $allowedCountries);
                $totalInserted += $results['inserted'];
                $totalUpdated += $results['updated'];
                $totalModified += $results['modified'];
                $totalSkipped += $results['skipped'];

                $totalSaved = $totalInserted + $totalUpdated;
                $this->updateImportLog($importLog, $totalSaved);
                
                if ($this->io) {
                    $elapsed = microtime(true) - $startTime;
                    $batchTime = microtime(true) - $batchStart;
                    $memory = memory_get_usage(true) / 1024 / 1024;
                    $this->io->write(sprintf(
                        "\r🚀 [%.2fs] Read: %d | Ins: %d | Upd: %d (%d mod) | Skip: %d | Batch: %.3fs | RAM: %.1fMB",
                        $elapsed, $totalRead, $totalInserted, $totalUpdated, $totalModified, $totalSkipped, $batchTime, $memory
                    ));

                    if ($iteration % 100 === 0) {
                        $this->takeMemorySnapshot("BATCH_$iteration");
                        $this->compareMemorySnapshots('START', "BATCH_$iteration");
                        $this->em->clear();
                        if (function_exists('gc_mem_caches')) gc_mem_caches();
                        gc_collect_cycles();
                    }
                }
                unset($batch, $results);
            }

            $this->completeImportLog($importLog, $totalInserted + $totalUpdated);
        } catch (\Throwable $e) {
            $this->failImportLog($importLog, $e->getMessage());
            throw $e;
        } finally {
            foreach ($tempFilesToCleanup as $f) { if (file_exists($f)) @unlink($f); }
            foreach ($tempDirsToCleanup as $d) { if (is_dir($d)) $this->fs->remove($d); }
        }
    }

    public function importHierarchy(string $url): int
    {
        $this->disableLogging();
        $filePath = $this->downloadFile($url);
        $tempDirsToCleanup = [];
        if (str_ends_with($url, '.zip')) {
            $result = $this->unzip($filePath);
            $filePath = $result['file'];
            $tempDirsToCleanup[] = $result['dir'];
        }

        $total = 0; $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform();
        foreach ($this->parser->getBatches($filePath, 1000) as $batch) {
            $values = []; $placeholders = [];
            foreach ($batch as $row) {
                if (count($row) < 2) continue;
                $placeholders[] = '(?, ?, ?)';
                $values[] = (int)$row[0]; $values[] = (int)$row[1]; $values[] = $row[2] ?? null;
                $total++;
            }
            if (empty($placeholders)) continue;
            $this->executeInternal(sprintf("INSERT IGNORE INTO %s (parentid, childid, type) VALUES %s", $platform->quoteIdentifier($this->hierarchyTableName), implode(', ', $placeholders)), $values);
            unset($batch, $values, $placeholders);
        }
        @unlink($filePath);
        foreach ($tempDirsToCleanup as $d) { $this->fs->remove($d); }
        return $total;
    }

    public function importAlternateNames(string $url, ?array $allowedLanguages = null): void
    {
        $filePath = $this->downloadFile($url);
        $tempDirsToCleanup = [];
        if (str_ends_with($url, '.zip')) {
            $result = $this->unzip($filePath);
            $filePath = $result['file'];
            $tempDirsToCleanup[] = $result['dir'];
        }
        foreach ($this->parser->getBatches($filePath, 1000) as $batch) {
            $this->processAlternateNameBatch($batch, $allowedLanguages);
            unset($batch);
        }
        @unlink($filePath);
        foreach ($tempDirsToCleanup as $d) { $this->fs->remove($d); }
    }

    private function processAlternateNameBatch(array $batch, ?array $allowedLanguages): int
    {
        $ids = []; $dataToProcess = [];
        foreach ($batch as $row) {
            if (count($row) < 4) continue;
            $lang = $row[2] ?? '';
            if ($allowedLanguages && !empty($allowedLanguages) && !in_array($lang, $allowedLanguages)) continue;
            $id = (int)$row[0]; $ids[] = $id;
            $dataToProcess[$id] = [ 'id' => $id, 'geonameId' => (int)$row[1], 'isoLanguage' => mb_substr((string)$lang, 0, 7), 'alternateName' => mb_substr((string)$row[3], 0, 200), 'isPreferredName' => ($row[4] ?? '') === '1', 'isShortName' => ($row[5] ?? '') === '1', 'isColloquial' => ($row[6] ?? '') === '1', 'isHistoric' => ($row[7] ?? '') === '1' ];
        }
        if (empty($ids)) return 0;
        $existingIds = $this->findExistingIds($this->alternateNameEntityClass, $ids);
        $toUpdate = []; $toInsert = [];
        foreach ($dataToProcess as $id => $data) { if (in_array($id, $existingIds)) { $toUpdate[] = $data; } else { $toInsert[] = $data; } }
        $count = 0;
        if (!empty($toInsert)) $count += $this->bulkInsert($this->alternateNameEntityClass, $toInsert);
        if (!empty($toUpdate)) $count += $this->bulkUpdate($this->alternateNameEntityClass, $toUpdate, 'id');
        unset($ids, $dataToProcess, $existingIds, $toUpdate, $toInsert);
        return $count;
    }

    public function importDailyUpdates(\DateTimeInterface $date, array $allowedCountries, bool $withAlternateNames = false): void
    {
        $dateStr = $date->format('Y-m-d');
        $this->importIncremental(sprintf('https://download.geonames.org/export/dump/deletes-%s.txt', $dateStr), 'daily_delete', [], true);
        $this->importIncremental(sprintf('https://download.geonames.org/export/dump/modifications-%s.txt', $dateStr), 'daily_modification', $allowedCountries);
        if ($withAlternateNames && $this->alternateNameEntityClass) {
            $this->importIncremental(sprintf('https://download.geonames.org/export/dump/alternateNamesModifications-%s.txt', $dateStr), 'daily_alternate_modification', [], false, true);
            $this->importIncremental(sprintf('https://download.geonames.org/export/dump/alternateNamesDeletes-%s.txt', $dateStr), 'daily_alternate_delete', [], true, true);
        }
    }

    public function importAdminCodes(string $url, string $entityClass, array $allowedCodes = []): int
    {
        $this->disableLogging(); $filePath = $this->downloadFile($url);
        $total = 0; $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform(); $platformClass = strtolower(get_class($platform));
        $tableName = null; $level = null;
        if (str_contains($url, 'admin1Codes')) { $tableName = $this->adminTableNames['adm1'] ?? null; $level = 1; }
        elseif (str_contains($url, 'admin2Codes')) { $tableName = $this->adminTableNames['adm2'] ?? null; $level = 2; }
        if (!$tableName) { $tableName = $this->em->getClassMetadata($entityClass)->getTableName(); }
        foreach ($this->parser->getBatches($filePath, 500) as $batch) {
            $values = []; $placeholders = [];
            foreach ($batch as $row) {
                if (count($row) < 4) continue;
                $fullCode = $row[0]; if (!empty($allowedCodes) && !in_array($fullCode, $allowedCodes)) continue;
                $codeParts = explode('.', $fullCode);
                if ($level === 1 && count($codeParts) === 2) { $placeholders[] = '(?, ?, ?, ?, ?)'; $values[] = $codeParts[0]; $values[] = $codeParts[1]; $values[] = mb_substr((string)$row[1], 0, 200); $values[] = mb_substr((string)$row[2], 0, 200); $values[] = (int)$row[3]; $total++; }
                elseif ($level === 2 && count($codeParts) === 3) { $placeholders[] = '(?, ?, ?, ?, ?, ?)'; $values[] = $codeParts[0]; $values[] = $codeParts[1]; $values[] = $codeParts[2]; $values[] = mb_substr((string)$row[1], 0, 200); $values[] = mb_substr((string)$row[2], 0, 200); $values[] = (int)$row[3]; $total++; }
            }
            if (!empty($placeholders)) { $this->executeInternal(sprintf("INSERT INTO %s (%s, %s, %s, %s) VALUES %s ON DUPLICATE KEY UPDATE %s = VALUES(%s), %s = VALUES(%s), %s = VALUES(%s)", $platform->quoteIdentifier($tableName), implode(', ', ($level === 1) ? ['country_code', 'admin1_code'] : ['country_code', 'admin1_code', 'admin2_code']), $platform->quoteIdentifier('name'), $platform->quoteIdentifier('ascii_name'), $platform->quoteIdentifier('geonameid'), implode(', ', $placeholders), $platform->quoteIdentifier('name'), $platform->quoteIdentifier('name'), $platform->quoteIdentifier('ascii_name'), $platform->quoteIdentifier('ascii_name'), $platform->quoteIdentifier('geonameid'), $platform->quoteIdentifier('geonameid')), $values); }
            unset($batch, $values, $placeholders);
        }
        @unlink($filePath); return $total;
    }

    public function getUsedAdminCodes(string $level, array $allowedCountries): array
    {
        $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform();
        $codeExpr = match (strtoupper($level)) { 'ADM1' => "CONCAT(country_code, '.', admin1_code)", 'ADM2' => "CONCAT(country_code, '.', admin1_code, '.', admin2_code)", default => null };
        if (!$codeExpr) return [];
        return $this->fetchAllInternal(sprintf("SELECT DISTINCT %s as code FROM %s WHERE country_code IN (?) AND %s != ''", $codeExpr, $platform->quoteIdentifier($this->importTableName), str_contains($level, '1') ? $platform->quoteIdentifier('admin1_code') : $platform->quoteIdentifier('admin2_code')), [$allowedCountries]);
    }

    public function syncAdminTablesFromTable(array $allowedCountries = []): array
    {
        $levels = ['ADM1', 'ADM2', 'ADM3', 'ADM4', 'ADM5']; $stats = []; $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform(); $platformClass = strtolower(get_class($platform));
        foreach ($levels as $level) {
            $targetTable = $this->adminTableNames[strtolower($level)] ?? null; if (!$targetTable) continue;
            $where = ["g.feature_code = " . $conn->quote($level)]; if (!empty($allowedCountries)) { $where[] = "g.country_code IN (" . implode(',', array_map([$conn, 'quote'], $allowedCountries)) . ")"; }
            $whereSql = implode(' AND ', $where);
            $cols = match ($level) { 'ADM1' => ['country_code', 'admin1_code'], 'ADM2' => ['country_code', 'admin1_code', 'admin2_code'], 'ADM3' => ['country_code', 'admin1_code', 'admin2_code', 'admin3_code'], 'ADM4' => ['country_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code'], 'ADM5' => ['country_code', 'admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'admin5_code'] };
            $colsQuoted = array_map(fn($c) => $platform->quoteIdentifier($c), $cols); $colsList = implode(', ', $colsQuoted); $targetTableQuoted = $platform->quoteIdentifier($targetTable); $importTableQuoted = $platform->quoteIdentifier($this->geonameTableName);
            if (str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb')) { $this->executeInternal(sprintf("INSERT IGNORE INTO %s (%s, name, ascii_name, geonameid) SELECT DISTINCT %s, g.name, g.ascii_name, g.geonameid FROM %s g WHERE %s", $targetTableQuoted, $colsList, implode(', ', array_map(fn($c) => "g." . $platform->quoteIdentifier($c), $cols)), $importTableQuoted, $whereSql), []); }
            $joinOn = implode(' AND ', array_map(fn($c) => "t." . $platform->quoteIdentifier($c) . " = g." . $platform->quoteIdentifier($c), $cols));
            $this->executeInternal(sprintf("UPDATE %s t INNER JOIN %s g ON %s SET t.name = g.name, t.ascii_name = g.ascii_name, t.geonameid = g.geonameid WHERE %s", $targetTableQuoted, $importTableQuoted, $joinOn, $whereSql), []);
            $stats[$level] = 0; // Stats are less important than speed here
        }
        return $stats;
    }

    public function importAdmin5(string $url, string $tableName): int
    {
        $this->disableLogging(); $filePath = $this->downloadFile($url);
        $tempDirsToCleanup = [];
        if (str_ends_with($url, '.zip')) { $result = $this->unzip($filePath); $filePath = $result['file']; $tempDirsToCleanup[] = $result['dir']; }
        $total = 0; $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform();
        foreach ($this->parser->getBatches($filePath, 2000) as $batch) {
            $sql = sprintf("UPDATE %s SET admin5_code = CASE geonameid ", $platform->quoteIdentifier($tableName));
            $ids = []; $values = [];
            foreach ($batch as $row) { if (count($row) < 2) continue; $id = (int)$row[0]; $code = $row[1]; $sql .= "WHEN ? THEN ? "; $values[] = $id; $values[] = $code; $ids[] = $id; $total++; }
            if (!empty($ids)) { $this->executeInternal($sql . "END WHERE geonameid IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", array_merge($values, $ids)); }
            unset($batch, $ids, $values);
        }
        @unlink($filePath); foreach ($tempDirsToCleanup as $d) { $this->fs->remove($d); }
        return $total;
    }

    private function importIncremental(string $url, string $type, array $allowedCountries = [], bool $isDelete = false, bool $isAlternate = false): void
    {
        $importLog = $this->createImportLog($type, $url); $tempDirsToCleanup = [];
        try {
            $filePath = $this->downloadFile($url);
            if (str_ends_with($url, '.zip')) { $result = $this->unzip($filePath); $filePath = $result['file']; $tempDirsToCleanup[] = $result['dir']; }
            $totalProcessed = 0;
            foreach ($this->parser->getBatches($filePath, 500) as $batch) {
                if ($isAlternate) { if ($isDelete) { $totalProcessed += $this->processAlternateDeleteBatch($batch); } else { $totalProcessed += $this->processAlternateNameBatch($batch, null); } }
                else { if ($isDelete) { $totalProcessed += $this->processDeleteBatch($batch); } else { $results = $this->processHybridBatch($batch, $allowedCountries); $totalProcessed += ($results['inserted'] + $results['updated']); } }
                $this->updateImportLog($importLog, $totalProcessed); unset($batch);
            }
            $this->completeImportLog($importLog, $totalProcessed); @unlink($filePath);
        } catch (\Throwable $e) { $this->failImportLog($importLog, $e->getMessage()); }
        finally { foreach ($tempDirsToCleanup as $d) { $this->fs->remove($d); } }
    }

    private function processHybridBatch(array $batch, ?array $allowedCountries): array
    {
        $toProcess = []; $ids = []; $skipped = 0;
        foreach ($batch as $row) {
            if (count($row) < 19) { $skipped++; continue; }
            $countryCode = strtoupper(trim($row[8] ?? ''));
            if ($allowedCountries !== null && !empty($allowedCountries) && !in_array($countryCode, $allowedCountries, true)) { $skipped++; continue; }
            $data = $this->mapRowToData($row); $id = (int)$data['id']; $toProcess[$id] = $data; $ids[] = $id;
        }
        if (empty($ids)) return ['inserted' => 0, 'updated' => 0, 'modified' => 0, 'skipped' => $skipped];
        $existingIds = $this->findExistingIds($this->geonameEntityClass, $ids);
        $existingIds = array_map('intval', $existingIds); $toUpdate = []; $toInsert = [];
        foreach ($toProcess as $id => $data) { if (in_array($id, $existingIds, true)) { $toUpdate[] = $data; } else { $toInsert[] = $data; } }
        $insertedCount = count($toInsert); $updatedCount = count($toUpdate); $actualModified = 0;
        if (!empty($toInsert)) $this->bulkInsert($this->geonameEntityClass, $toInsert);
        if (!empty($toUpdate)) $actualModified = $this->bulkUpdate($this->geonameEntityClass, $toUpdate, 'id');
        $this->syncAdminTablesFromBatch($toProcess);
        unset($toProcess, $ids, $existingIds, $toUpdate, $toInsert);
        return ['inserted' => $insertedCount, 'updated' => $updatedCount, 'modified' => $actualModified, 'skipped' => $skipped];
    }

    private function syncAdminTablesFromBatch(array $batch): void
    {
        if (empty($this->adminTableNames)) return;
        $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform(); $platformClass = strtolower(get_class($platform));
        $adminData = ['ADM1' => [], 'ADM2' => [], 'ADM3' => [], 'ADM4' => [], 'ADM5' => []];
        foreach ($batch as $data) {
            $fCode = $data['featureCode']; if (!isset($adminData[$fCode])) continue;
            $code = $data['countryCode'] . '.' . $data['admin1Code'];
            if (in_array($fCode, ['ADM2', 'ADM3', 'ADM4', 'ADM5'])) $code .= '.' . $data['admin2Code'];
            if (in_array($fCode, ['ADM3', 'ADM4', 'ADM5'])) $code .= '.' . $data['admin3Code'];
            if (in_array($fCode, ['ADM4', 'ADM5'])) $code .= '.' . $data['admin4Code'];
            if ($fCode === 'ADM5') $code .= '.' . ($data['admin5Code'] ?? $data['id']);
            $row = ['country_code' => $data['countryCode'], 'admin1_code' => $data['admin1Code'], 'name' => $data['name'], 'ascii_name' => $data['asciiName'], 'geonameid' => $data['id']];
            if (in_array($fCode, ['ADM2', 'ADM3', 'ADM4', 'ADM5'])) $row['admin2_code'] = $data['admin2Code'];
            if (in_array($fCode, ['ADM3', 'ADM4', 'ADM5'])) $row['admin3_code'] = $data['admin3Code'];
            if (in_array($fCode, ['ADM4', 'ADM5'])) $row['admin4_code'] = $data['admin4Code'];
            if ($fCode === 'ADM5') $row['admin5_code'] = $data['admin5Code'] ?? $data['id'];
            $adminData[$fCode][$code] = $row;
        }
        foreach ($adminData as $level => $rows) {
            $tableName = $this->adminTableNames[strtolower($level)] ?? null; if (!$tableName || empty($rows)) continue;
            $firstRow = reset($rows); $cols = array_keys($firstRow); $placeholders = []; $values = []; $qs = '(' . implode(', ', array_fill(0, count($cols), '?')) . ')';
            foreach ($rows as $row) { $placeholders[] = $qs; foreach ($cols as $col) { $values[] = $row[$col]; } }
            $this->executeInternal(sprintf("INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s = VALUES(%s), %s = VALUES(%s), %s = VALUES(%s)", $platform->quoteIdentifier($tableName), implode(', ', array_map(fn($c) => $platform->quoteIdentifier($c), $cols)), implode(', ', $placeholders), $platform->quoteIdentifier('name'), $platform->quoteIdentifier('name'), $platform->quoteIdentifier('ascii_name'), $platform->quoteIdentifier('ascii_name'), $platform->quoteIdentifier('geonameid'), $platform->quoteIdentifier('geonameid')), $values);
            unset($rows, $placeholders, $values);
        }
        unset($adminData);
    }

    private function processDeleteBatch(array $batch): int
    {
        $ids = []; foreach ($batch as $row) { if (count($row) < 1) continue; $ids[] = (int)$row[0]; }
        if (empty($ids)) return 0;
        $count = $this->bulkUpdate($this->geonameEntityClass, array_map(fn($id) => ['id' => $id, 'isDeleted' => true], $ids), 'id');
        unset($ids); return $count;
    }

    private function processAlternateDeleteBatch(array $batch): int
    {
        $ids = []; foreach ($batch as $row) { if (count($row) < 1) continue; $ids[] = (int)$row[0]; }
        if (empty($ids)) return 0;
        $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform();
        $count = $conn->executeStatement(sprintf("DELETE FROM %s WHERE %s IN (?)", $platform->quoteIdentifier($this->alternateNameTableName), $platform->quoteIdentifier('alternatenameid')), [$ids], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
        unset($ids); return $count;
    }

    private function mapRowToData(array $row): array
    {
        $modDate = null; if (isset($row[18]) && $row[18] !== '') { $date = \DateTime::createFromFormat('Y-m-d', $row[18]); $modDate = $date ?: null; }
        $name = mb_substr(trim((string)($row[1] ?? '')), 0, 200); $ascii = $this->toAscii(trim((string)($row[2] ?? '')) ?: $name);
        return [ 'id' => (int)$row[0], 'name' => $name ?: 'Unknown', 'asciiName' => mb_substr($ascii, 0, 200) ?: 'Unknown', 'alternatenames' => mb_substr((string)($row[3] ?? ''), 0, 20000), 'latitude' => (float)($row[4] ?? 0), 'longitude' => (float)($row[5] ?? 0), 'featureClass' => ($row[6] ?? '') !== '' ? mb_substr((string)$row[6], 0, 1) : null, 'featureCode' => ($row[7] ?? '') !== '' ? mb_substr((string)$row[7], 0, 10) : null, 'countryCode' => ($row[8] ?? '') !== '' ? mb_substr(strtoupper((string)$row[8]), 0, 2) : null, 'admin1Code' => (string)($row[10] ?? ''), 'admin2Code' => (string)($row[11] ?? ''), 'admin3Code' => (string)($row[12] ?? ''), 'admin4Code' => (string)($row[13] ?? ''), 'population' => (string)($row[14] ?? ''), 'elevation' => ($row[15] ?? '') !== '' ? (int)$row[15] : null, 'timezone' => mb_substr((string)$row[17] ?? '', 0, 40), 'modificationDate' => $modDate, 'isDeleted' => false ];
    }

    private function toAscii(string $text): string
    {
        if ($text === '') return '';
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return $converted ? (string)preg_replace('/[^\x20-\x7E]/', '', $converted) : '';
    }

    private function createImportLog(string $type, string $url): AbstractGeoImport
    {
        $log = new $this->importEntityClass(); $log->setType($type); $log->setStatus(AbstractGeoImport::STATUS_RUNNING); $log->setDetails("Source: $url");
        $this->em->persist($log); $this->em->flush(); return $log;
    }

    private function updateImportLog(AbstractGeoImport $log, int $count): void
    {
        $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform();
        $conn->executeStatement(sprintf("UPDATE %s SET %s = ? WHERE %s = ?", $platform->quoteIdentifier($this->importTableName), $platform->quoteIdentifier('records_processed'), $platform->quoteIdentifier('id')), [$count, $log->getId()]);
    }

    private function completeImportLog(AbstractGeoImport $log, int $count): void
    {
        $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform();
        $conn->executeStatement(sprintf("UPDATE %s SET %s = ?, %s = ?, %s = ? WHERE %s = ?", $platform->quoteIdentifier($this->importTableName), $platform->quoteIdentifier('status'), $platform->quoteIdentifier('records_processed'), $platform->quoteIdentifier('ended_at'), $platform->quoteIdentifier('id')), [AbstractGeoImport::STATUS_COMPLETED, $count, (new \DateTime())->format('Y-m-d H:i:s'), $log->getId()]);
    }

    private function failImportLog(AbstractGeoImport $log, string $error): void
    {
        if (!$this->em->isOpen()) return;
        $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform();
        $conn->executeStatement(sprintf("UPDATE %s SET %s = ?, %s = ?, %s = ? WHERE %s = ?", $platform->quoteIdentifier($this->importTableName), $platform->quoteIdentifier('status'), $platform->quoteIdentifier('error_message'), $platform->quoteIdentifier('ended_at'), $platform->quoteIdentifier('id')), [AbstractGeoImport::STATUS_FAILED, substr($error, 0, 65535), (new \DateTime())->format('Y-m-d H:i:s'), $log->getId()]);
    }

    private function disableLogging(): void
    {
        $conn = $this->em->getConnection(); $config = $conn->getConfiguration();
        if (method_exists($config, 'setSQLLogger')) { $config->setSQLLogger(null); }
        if (method_exists($config, 'setMiddlewares')) { $config->setMiddlewares([]); }
        $this->cachedLogger = method_exists($config, 'getSQLLogger') ? $config->getSQLLogger() : null;
        if ($this->cachedLogger && property_exists($this->cachedLogger, 'enabled')) { $this->cachedLogger->enabled = false; }
        $this->purgeLogger();
        if (function_exists('gc_mem_caches')) { gc_mem_caches(); }
        gc_collect_cycles(); $this->em->clear();
    }

    private function purgeLogger(): void
    {
        $config = $this->em->getConnection()->getConfiguration(); $logger = method_exists($config, 'getSQLLogger') ? $config->getSQLLogger() : null;
        if ($logger) { if (method_exists($logger, 'clearQueries')) { $logger->clearQueries(); } elseif (property_exists($logger, 'queries')) { $logger->queries = []; } }
    }

    private function downloadFile(string $url): string
    {
        if ($this->io) $this->io->text("⬇️  Downloading: $url");
        if (!is_dir($this->tmpDir)) mkdir($this->tmpDir, 0777, true);
        $tempFile = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('geoname_', true);
        $response = $this->httpClient->request('GET', $url);
        if ($response->getStatusCode() !== 200) { throw new \RuntimeException("Failed to download: " . $response->getStatusCode()); }
        $fileHandler = fopen($tempFile, 'w');
        foreach ($this->httpClient->stream($response) as $chunk) { fwrite($fileHandler, $chunk->getContent()); }
        fclose($fileHandler); return $tempFile;
    }

    private function unzip(string $zipPath): array
    {
        if ($this->io) $this->io->text("📦 Decompressing file...");
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $extractDir = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('extract_', true); mkdir($extractDir);
            $zip->extractTo($extractDir); $zip->close(); unlink($zipPath);
            $files = glob($extractDir . DIRECTORY_SEPARATOR . '*.txt');
            return ['file' => $files[0], 'dir' => $extractDir];
        }
        throw new \RuntimeException('Failed to unzip file');
    }

    private function bulkInsert(string $entityClass, array $rows, int $chunkSize = 1000): int
    {
        if (empty($rows)) return 0;
        $conn = $this->em->getConnection(); $metadata = $this->em->getClassMetadata($entityClass);
        $tableName = $this->geonameTableName; if (str_contains($entityClass, 'AlternateName')) $tableName = $this->alternateNameTableName;
        $columnMap = $this->getColumnMap($metadata); $quotedCols = array_map(fn($c) => $conn->getDatabasePlatform()->quoteIdentifier($c), array_values($columnMap));
        $colsSql = implode(', ', $quotedCols); $isMysql = str_contains(strtolower(get_class($conn->getDatabasePlatform())), 'mysql') || str_contains(strtolower(get_class($conn->getDatabasePlatform())), 'mariadb');
        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $placeholders = []; $params = [];
            foreach ($chunk as $row) {
                $rowPlaceholders = [];
                foreach ($columnMap as $prop => $col) {
                    $val = $row[$prop] ?? null;
                    if ($val instanceof \DateTimeInterface) $val = $val->format('Y-m-d H:i:s');
                    elseif (is_bool($val)) $val = $val ? 1 : 0;
                    $params[] = $val; $rowPlaceholders[] = '?';
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }
            $sql = sprintf('INSERT %s INTO %s (%s) VALUES %s', $isMysql ? 'IGNORE' : '', $conn->getDatabasePlatform()->quoteIdentifier($tableName), $colsSql, implode(', ', $placeholders));
            if (!$isMysql && (str_contains(strtolower(get_class($conn->getDatabasePlatform())), 'postgresql') || str_contains(strtolower(get_class($conn->getDatabasePlatform())), 'sqlite'))) $sql .= ' ON CONFLICT DO NOTHING';
            $total += $this->executeInternal($sql, $params);
            unset($params, $placeholders);
        }
        return $total;
    }

    private function bulkUpdate(string $entityClass, array $rows, string $pkField = 'id', int $chunkSize = 1000): int
    {
        if (empty($rows)) return 0;
        $conn = $this->em->getConnection(); $metadata = $this->em->getClassMetadata($entityClass);
        $tableName = $this->geonameTableName; if (str_contains($entityClass, 'AlternateName')) $tableName = $this->alternateNameTableName;
        $columnMap = $this->getColumnMap($metadata); $pkCol = $conn->getDatabasePlatform()->quoteIdentifier($columnMap[$pkField]);
        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $firstKeys = array_keys(reset($chunk)); $props = [];
            foreach ($firstKeys as $p) if ($p !== $pkField && isset($columnMap[$p])) $props[$p] = $columnMap[$p];
            if (empty($props)) continue;
            $params = []; $clauses = []; $pkVals = [];
            foreach ($props as $p => $c) $clauses[$c] = $conn->getDatabasePlatform()->quoteIdentifier($c) . " = CASE $pkCol ";
            foreach ($chunk as $row) {
                $pkVals[] = $row[$pkField];
                foreach ($props as $p => $c) $clauses[$c] .= "WHEN ? THEN ? ";
            }
            $sqlParams = [];
            foreach ($props as $p => $c) {
                foreach ($chunk as $row) {
                    $sqlParams[] = $row[$pkField];
                    $val = $row[$p] ?? null;
                    if ($val instanceof \DateTimeInterface) $val = $val->format('Y-m-d H:i:s');
                    elseif (is_bool($val)) $val = $val ? 1 : 0;
                    $sqlParams[] = $val;
                }
            }
            $sqlSet = []; foreach ($clauses as $c => $clause) $sqlSet[] = $clause . " END";
            $total += $this->executeInternal(sprintf("UPDATE %s SET %s WHERE %s IN (%s)", $conn->getDatabasePlatform()->quoteIdentifier($tableName), implode(', ', $sqlSet), $pkCol, implode(', ', array_fill(0, count($pkVals), '?'))), array_merge($sqlParams, $pkVals));
            unset($sqlParams, $pkVals, $clauses, $sqlSet);
            gc_collect_cycles();
        }
        return $total;
    }

    private function getColumnMap(ClassMetadata $metadata): array
    {
        $map = []; foreach ($metadata->getFieldNames() as $f) $map[$p] = $metadata->getColumnName($f); return $map;
    }

    private function findExistingIds(string $entityClass, array $ids): array
    {
        $conn = $this->em->getConnection(); $platform = $conn->getDatabasePlatform();
        $tableName = $this->geonameTableName; if (str_contains($entityClass, 'AlternateName')) $tableName = $this->alternateNameTableName;
        $metadata = $this->em->getClassMetadata($entityClass); $pkCol = $platform->quoteIdentifier($metadata->getColumnName($metadata->getIdentifierFieldNames()[0]));
        $found = $this->fetchAllInternal(sprintf("SELECT %s FROM %s WHERE %s IN (%s)", $pkCol, $platform->quoteIdentifier($tableName), $pkCol, implode(',', array_fill(0, count($ids), '?'))), $ids);
        return array_map('intval', $found);
    }

    private function executeInternal(string $sql, array $params): int
    {
        $conn = $this->em->getConnection();
        if ($this->debug) { $pdo = $conn->getNativeConnection(); $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->rowCount(); }
        return $conn->executeStatement($sql, $params);
    }

    private function fetchAllInternal(string $sql, array $params): array
    {
        $conn = $this->em->getConnection();
        if ($this->debug) { $pdo = $conn->getNativeConnection(); $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(\PDO::FETCH_COLUMN); }
        return $conn->executeQuery($sql, $params)->fetchFirstColumn();
    }

    private function takeMemorySnapshot(string $label): void
    {
        $this->memorySnapshots[$label] = ['total' => memory_get_usage(true)];
        if (count($this->memorySnapshots) > 5) array_shift($this->memorySnapshots);
    }

    private function compareMemorySnapshots(string $label1, string $label2): void
    {
        if (!isset($this->memorySnapshots[$label1], $this->memorySnapshots[$label2])) return;
        $diff = ($this->memorySnapshots[$label2]['total'] - $this->memorySnapshots[$label1]['total']) / 1024 / 1024;
        $this->io->writeln(sprintf("\n <comment>Memory [%s vs %s]</comment> Change: <info>%+.2f MB</info>", $label1, $label2, $diff));
    }
}
