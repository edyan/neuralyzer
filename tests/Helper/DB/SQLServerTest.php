<?php

namespace Edyan\Neuralyzer\Tests\Utils;

use Edyan\Neuralyzer\Helper\DB\SQLServer;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class SQLServerTest extends AbstractConfigurationDB
{
    public function testDriverOptions()
    {
        $options = SQLServer::getDriverOptions();
        $this->assertInternalType('array', $options);
        $this->assertEmpty($options);
    }

    public function testGetEnclosure()
    {
        $db = new SQLServer($this->getDoctrine());
        $this->assertEquals(chr(0), $db->getEnclosureForCSV());
    }

    public function testLoadDataPretend()
    {
        $params = $this->getDbParams();
        $params['host'] = '127.0.0.1';
        $conn = \Doctrine\DBAL\DriverManager::getConnection(
            $params,
            new \Doctrine\DBAL\Configuration
        );
        $db = new SQLServer($conn);
        $db->setPretend(true);
        $sql = $db->loadData('matable', 'monfichier', ['field1', 'field2'], 'update');

        $this->assertContains("FROM 'monfichier'", $sql);
        $this->assertContains('BULK INSERT matable', $sql);
        $this->assertNotContains('field2', $sql);
    }

    /**
     * @expectedException \Edyan\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessage SQL Server must be on the same host than PHP
     */
    public function testLoadDataOtherHost()
    {
        $db = new SQLServer($this->getDoctrine());
        $db->setPretend(true);
        $sql = $db->loadData('matable', 'monfichier', ['field1', 'field2'], 'update');
    }
}
