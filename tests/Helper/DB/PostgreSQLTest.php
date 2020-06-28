<?php

namespace Edyan\Neuralyzer\Tests\Helper\DB;

use Edyan\Neuralyzer\Helper\DB\PostgreSQL;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class PostgreSQLTest extends AbstractConfigurationDB
{
    public function testDriverOptions()
    {
        $options = PostgreSQL::getDriverOptions();
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    public function testGetEnclosure()
    {
        $db = new PostgreSQL($this->getDoctrine());
        $this->assertEquals(chr(0), $db->getEnclosureForCSV());
    }

    public function testLoadDataPretend()
    {
        $db = new PostgreSQL($this->getDoctrine());
        $db->setPretend(true);
        $sql = $db->loadData('matable', 'monfichier', ['field1', 'field2'], 'update');

        $this->assertStringContainsString("FROM 'monfichier'", $sql);
        $this->assertStringContainsString('COPY matable', $sql);
        $this->assertStringContainsString('(field1, field2)', $sql);
    }
}
