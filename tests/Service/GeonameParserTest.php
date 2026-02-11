<?php

namespace Gpp\GeonameBundle\Tests\Service;

use Gpp\GeonameBundle\Service\GeonameParser;
use PHPUnit\Framework\TestCase;

class GeonameParserTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'geoname_test');
        $content = "1	Name 1	Ascii 1	Alt 1	45.0	7.0	P	PPL	IT		01	TO			1000	100	100	Europe/Rome	2024-01-01
";
        $content .= "2	Name 2	Ascii 2	Alt 2	46.0	8.0	P	PPL	IT		02	MI			2000	200	200	Europe/Rome	2024-01-01
";
        file_put_contents($this->tempFile, $content);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testGetRows(): void
    {
        $parser = new GeonameParser();
        $rows = iterator_to_array($parser->getRows($this->tempFile));

        $this->assertCount(2, $rows);
        $this->assertEquals('Name 1', $rows[0][1]);
        $this->assertEquals('Name 2', $rows[1][1]);
    }

    public function testGetBatches(): void
    {
        $parser = new GeonameParser();
        // Batch size of 1
        $batches = iterator_to_array($parser->getBatches($this->tempFile, 1));

        $this->assertCount(2, $batches);
        $this->assertCount(1, $batches[0]);
        $this->assertEquals('Name 1', $batches[0][0][1]);
    }
}
