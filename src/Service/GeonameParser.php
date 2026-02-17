<?php

namespace Pallari\GeonameBundle\Service;

class GeonameParser
{
    /**
     * @param string $filePath
     * @return \Generator<int, array>
     */
    public function getRows(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException(sprintf('Could not open file: %s', $filePath));
        }

        try {
            while (($line = fgets($handle)) !== false) {
                // 1. Quick check for UTF-8 validity
                if (!mb_check_encoding($line, 'UTF-8')) {
                    // 2. If invalid, convert from Latin-1 to preserve common European symbols (like 'à')
                    $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
                }

                // Note: Final byte-level safety is now handled by mb_substr in Importer
                yield explode("\t", rtrim($line, "\r\n"));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param string $filePath
     * @param int $batchSize
     * @return \Generator<int, array<int, array>>
     */
    public function getBatches(string $filePath, int $batchSize = 1000): \Generator
    {
        $batch = [];
        foreach ($this->getRows($filePath) as $row) {
            $batch[] = $row;

            if (count($batch) >= $batchSize) {
                yield $batch;
                $batch = [];
                // Suggest GC after yielding a batch
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }

        if (!empty($batch)) {
            yield $batch;
        }
    }
}
