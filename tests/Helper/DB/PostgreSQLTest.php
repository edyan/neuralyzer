<?php

namespace Edyan\Neuralyzer\Tests\Utils;

use Edyan\Neuralyzer\Helper\DB\PostgreSQL;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class PostgreSQLTest extends AbstractConfigurationDB
{
    public function testDriverOptions()
    {
        $options = PostgreSQL::getDriverOptions();
        $this->assertInternalType('array', $options);
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

        $this->assertContains("FROM 'monfichier'", $sql);
        $this->assertContains('COPY matable', $sql);
        $this->assertContains('(field1, field2)', $sql);
    }
}
