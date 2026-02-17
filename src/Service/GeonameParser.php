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
                // 1. High-performance encoding check
                if (!mb_check_encoding($line, 'UTF-8')) {
                    // If not valid UTF-8, try to preserve common European characters by converting from Latin-1
                    $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
                }

                // 2. Final sanitization: ensure the string is strictly valid UTF-8 for the database
                // mb_convert_encoding(..., 'UTF-8', 'UTF-8') removes any remaining invalid byte sequences
                $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');

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
