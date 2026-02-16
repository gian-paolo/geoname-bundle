<?php

namespace Pallari\GeonameBundle\Service;

class GeonameParser
{
    /**
     * @param string $filePath
     * @param int $batchSize
     * @return \Generator<array<int, array>>
     */
    public function getBatches(string $filePath, int $batchSize = 1000): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException(sprintf('Could not open file: %s', $filePath));
        }

        $batch = [];
        while (($line = fgets($handle)) !== false) {
            $data = explode("\t", rtrim($line, "\r\n"));
            $batch[] = $data;

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

        fclose($handle);
    }
}
