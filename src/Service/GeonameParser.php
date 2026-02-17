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
                // High-performance encoding check
                if (!mb_check_encoding($line, 'UTF-8')) {
                    // If not valid UTF-8, assume it's ISO-8859-1 (Latin-1) and convert to preserve characters like 'à'
                    $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
                }
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
