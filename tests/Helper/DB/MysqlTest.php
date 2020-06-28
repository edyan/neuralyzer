<?php

namespace Edyan\Neuralyzer\Tests\Helper\DB;

use Edyan\Neuralyzer\Helper\DB\MySQL;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class MySQLTest extends AbstractConfigurationDB
{
    public function testDriverOptions()
    {
        if (defined('\PDO::MYSQL_ATTR_LOCAL_INFILE') === false) {
            $this->markTestSkipped('No MySQL driver installed');
        }
        $options = MySQL::getDriverOptions();
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
    }

    public function testGetEnclosure()
    {
        $db = new MySQL($this->getDoctrine());
        $this->assertEquals('"', $db->getEnclosureForCSV());
    }

    public function testLoadDataPretend()
    {
        $db = new MySQL($this->getDoctrine());
        $db->setPretend(true);
        $sql = $db->loadData('matable', 'monfichier', ['field1', 'field2'], 'update');

        $this->assertStringContainsString("LOCAL INFILE 'monfichier'", $sql);
        $this->assertStringContainsString('INTO TABLE matable', $sql);
        $this->assertStringContainsString('(`field1`, `field2`)', $sql);
    }
}
