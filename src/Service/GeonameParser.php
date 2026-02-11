<?php

namespace Gpp\GeonameBundle\Service;

class GeonameParser
{
    /**
     * @param string $filePath
     * @param int $batchSize
     * @return \Generator<array<int, mixed>>
     */
    public function getBatches(string $filePath, int $batchSize = 1000): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException(sprintf('Could not open file: %s', $filePath));
        }

        $batch = [];
        $count = 0;

        while (($data = fgetcsv($handle, 0, "	")) !== false) {
            $batch[] = $data;
            $count++;

            if ($count >= $batchSize) {
                yield $batch;
                $batch = [];
                $count = 0;
            }
        }

        if (!empty($batch)) {
            yield $batch;
        }

        fclose($handle);
    }

    /**
     * Helper to read a single row at a time if needed
     */
    public function getRows(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException(sprintf('Could not open file: %s', $filePath));
        }

        while (($data = fgetcsv($handle, 0, "	")) !== false) {
            yield $data;
        }

        fclose($handle);
    }
}
