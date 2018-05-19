<?php

namespace Edyan\Neuralyzer\Tests\Utils;

use Edyan\Neuralyzer\Helper\DB\MySQL;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class MySQLTest extends AbstractConfigurationDB
{
    public function testDriverOptions()
    {
        $options = MySQL::getDriverOptions();
        $this->assertInternalType('array', $options);
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

        $this->assertContains("LOCAL INFILE 'monfichier'", $sql);
        $this->assertContains('INTO TABLE matable', $sql);
        $this->assertContains('(`field1`, `field2`)', $sql);
    }
}
