<?php

namespace Pallari\GeonameBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Pallari\GeonameBundle\Entity\AbstractDataImport;
use Pallari\GeonameBundle\Entity\AbstractGeoName;
use Pallari\GeonameBundle\Repository\GeonameRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeonameImporter
{
    private string $geonameEntityClass;
    private string $importEntityClass;
    private string $alternateNameEntityClass;
    private string $importTableName;
    /** @var GeonameRepository */
    private $repository;
    /** @var GeonameRepository */
    private $alternateNameRepository;

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
        $this->repository = $this->em->getRepository($this->geonameEntityClass);

        if ($alternateNameEntityClass) {
            $this->alternateNameEntityClass = $alternateNameEntityClass;
            $this->alternateNameRepository = $this->em->getRepository($this->alternateNameEntityClass);
        }
    }

    public function setTableNames(string $importTableName): void
    {
        $this->importTableName = $importTableName;
    }

    public function importFull(string $url, ?array $allowedCountries = null): void
    {
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
        $filePath = $this->downloadFile($url);
        if (str_ends_with($url, '.zip')) {
            $filePath = $this->unzip($filePath);
        }

        $total = 0;
        $conn = $this->em->getConnection();
        // Assuming we have a configuration for this table name
        $tableName = 'gpp_geohierarchy'; // Should be dynamic from config

        foreach ($this->parser->getBatches($filePath, 1000) as $batch) {
            foreach ($batch as $row) {
                if (count($row) < 2) continue;
                
                $sql = sprintf(
                    "INSERT IGNORE INTO `%s` (parentid, childid, type) VALUES (?, ?, ?)",
                    $tableName
                );
                $conn->executeStatement($sql, [(int)$row[0], (int)$row[1], $row[2] ?? null]);
                $total++;
            }
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

        $existingIds = $this->alternateNameRepository->findExistingIds($ids);
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
        if (!empty($toInsert)) $count += $this->alternateNameRepository->bulkInsert($toInsert);
        if (!empty($toUpdate)) $count += $this->alternateNameRepository->bulkUpdate($toUpdate, 'id');

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
        $filePath = $this->downloadFile($url);
        $total = 0;
        $conn = $this->em->getConnection();
        $metadata = $this->em->getClassMetadata($entityClass);
        $tableName = $metadata->getTableName();

        foreach ($this->parser->getBatches($filePath, 500) as $batch) {
            foreach ($batch as $row) {
                if (count($row) < 4) continue;
                
                $data = [
                    'code' => $row[0],
                    'name' => $row[1],
                    'asciiname' => $row[2],
                    'geonameid' => (int)$row[3]
                ];

                $sql = sprintf(
                    "INSERT INTO `%s` (code, name, asciiname, geonameid) VALUES (?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE name = VALUES(name), asciiname = VALUES(asciiname), geonameid = VALUES(geonameid)",
                    $tableName
                );
                $conn->executeStatement($sql, array_values($data));
                $total++;
            }
        }
        
        unlink($filePath);
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

        $existingIds = $this->repository->findExistingIds($ids);
        
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
        if (!empty($toInsert)) $count += $this->repository->bulkInsert($toInsert);
        if (!empty($toUpdate)) $count += $this->repository->bulkUpdate($toUpdate, 'id');

        return $count;
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
        return $this->repository->bulkUpdate($deleteData, 'id');
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
        $metadata = $this->em->getClassMetadata($this->alternateNameEntityClass);
        $tableName = $metadata->getTableName();
        
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

    private function createImportLog(string $type, string $url): AbstractDataImport
    {
        $log = new $this->importEntityClass();
        $log->setType($type);
        $log->setStatus(AbstractDataImport::STATUS_RUNNING);
        $log->setDetails("Source: $url");
        $this->em->persist($log);
        $this->em->flush();
        return $log;
    }

    private function updateImportLog(AbstractDataImport $log, int $count): void
    {
        $this->em->getConnection()->executeStatement(
            sprintf("UPDATE `%s` SET records_processed = ? WHERE id = ?", $this->importTableName),
            [$count, $log->getId()]
        );
    }

    private function completeImportLog(AbstractDataImport $log, int $count): void
    {
        $this->em->getConnection()->executeStatement(
            sprintf("UPDATE `%s` SET status = ?, records_processed = ?, ended_at = ? WHERE id = ?", $this->importTableName),
            [AbstractDataImport::STATUS_COMPLETED, $count, (new \DateTime())->format('Y-m-d H:i:s'), $log->getId()]
        );
    }

    private function failImportLog(AbstractDataImport $log, string $error): void
    {
        if (!$this->em->isOpen()) return;
        $this->em->getConnection()->executeStatement(
            sprintf("UPDATE `%s` SET status = ?, error_message = ?, ended_at = ? WHERE id = ?", $this->importTableName),
            [AbstractDataImport::STATUS_FAILED, $error, (new \DateTime())->format('Y-m-d H:i:s'), $log->getId()]
        );
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
}
