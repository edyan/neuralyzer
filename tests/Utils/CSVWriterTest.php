<?php

namespace Edyan\Neuralyzer\Tests\Utils;

use Edyan\Neuralyzer\Utils\CSVWriter;
use PHPUnit\Framework\TestCase;

class CSVWriterTest extends TestCase
{
    public function testTempFileName()
    {
        $writer = new CSVWriter;
        $this->assertStringContainsString(sys_get_temp_dir(), $writer->getRealPath());
    }

    public function testWriteLineWithPipeDelimiter()
    {
        $row = ['a', 'b', 'c1,c2|l', '"abc"'];
        $writer = new CSVWriter;
        $writer->setCsvControl($delimiter = '|', chr(0));
        $writer->write($row);

        $handle = fopen($writer->getRealPath(), 'r');
        $this->assertNotFalse($handle);
        $line = 0;
        while (($data = fgetcsv($handle, 1000, $delimiter, chr(0))) !== false) {
            $this->assertCount(count($row), $data, 'Actual result : ' . print_r($data, true));
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $row[0]), $data[0]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $row[1]), $data[1]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $row[2]), $data[2]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $row[3]), $data[3]);
            $line++;
        }
        $this->assertSame(1, $line);
    }

    public function testWriteLineWithCommaDelimiter()
    {
        $row = ['a', 'b', 'c1,c2|l', '"abc"'];
        $writer = new CSVWriter;
        $writer->setCsvControl($delimiter = ',', chr(0));
        $writer->write($row);

        $handle = fopen($writer->getRealPath(), 'r');
        $this->assertNotFalse($handle);
        $line = 0;
        while (($data = fgetcsv($handle, 1000, $delimiter, chr(0))) !== false) {
            $this->assertCount(count($row), $data);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $row[0]), $data[0]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $row[1]), $data[1]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $row[2]), $data[2]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $row[3]), $data[3]);
            $line++;
        }
        $this->assertSame(1, $line);
    }

    public function testWriteLineWithEnclosure()
    {
        $row = ['a', 'b', 'c1,c2|l', '"abc"'];
        $writer = new CSVWriter;
        $writer->setCsvControl($delimiter = ',', $enclosure = '|');
        $writer->write($row);

        $handle = fopen($writer->getRealPath(), 'r');
        $this->assertNotFalse($handle);
        $line = 0;
        while (($data = fgetcsv($handle, 1000, $delimiter, $enclosure)) !== false) {
            $this->assertCount(count($row), $data, 'Actual data : ' . print_r($data, true));
            $this->assertSame($row[0], $data[0]);
            $this->assertSame($row[1], $data[1]);
            $this->assertSame($row[2], $data[2]);
            $this->assertSame($row[3], $data[3]);
            $line++;
        }
        $this->assertSame(1, $line);
    }

    public function testWriteLineWithPipe()
    {
        $rows = [
            ['a', 'b', 'c1|c2', '"abc"'],
            ["'a'", 'b|c', 'c1|"c2', '"abc|'],
            ["'a'", 'b|c' . PHP_EOL . 'd', 'c1|"c2', '"abc|'],
        ];

        $writer = new CSVWriter;
        $writer->setCsvControl($delimiter = '|', chr(0));
        foreach ($rows as $row) {
            $writer->write($row);
        }

        $handle = fopen($writer->getRealPath(), 'r');
        $this->assertNotFalse($handle);
        $line = 0;
        while (($data = fgetcsv($handle, 1000, $delimiter, chr(0))) !== false) {
            $this->assertCount(count($rows[$line]), $data);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $rows[$line][0]), $data[0]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $rows[$line][1]), $data[1]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $rows[$line][2]), $data[2]);
            $this->assertSame(str_replace([$delimiter, PHP_EOL], ['', ''], $rows[$line][3]), $data[3]);
            $line++;
        }
        $this->assertSame(3, $line);
    }
}
