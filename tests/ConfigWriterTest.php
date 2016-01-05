<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Configuration\Writer;
use Inet\Neuralyzer\Configuration\Reader;
use Inet\Neuralyzer\Guesser;

class ConfigWriterTest extends ConfigurationDB
{
    private $protectedCols = array('.*\..*');
    private $ignoredTables = array('guestbook');

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |Not able to work with guestbook, it has no primary key.|
     */
    public function testGenerateConfNoPrimary()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $writer = new Writer;
        $writer->generateConfFromDB($pdo, new Guesser);
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |No tables to read in that database|
     */
    public function testGenerateConfNoTable()
    {
        $this->dropTable();
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $writer = new Writer;
        $writer->generateConfFromDB($pdo, new Guesser);
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |All tables or fields have been ignored|
     */
    public function testGenerateConfIgnoreAllFields()
    {
        $this->createPrimary();
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $writer = new Writer;
        $writer->setProtectedCols($this->protectedCols);
        $writer->generateConfFromDB($pdo, new Guesser);
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |No tables to read in that database|
     */
    public function testGenerateConfIgnoreAllTables()
    {
        $this->createPrimary();
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $writer = new Writer;
        $writer->setIgnoredTables($this->ignoredTables);
        $writer->generateConfFromDB($pdo, new Guesser);
    }

    public function testGenerateConfDontIgnore()
    {
        $this->createPrimary();
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $writer = new Writer;
        $writer->setProtectedCols(array('.*\..*'));
        $writer->protectCols(false);
        $entities = $writer->generateConfFromDB($pdo, new Guesser);
        $this->assertInternalType('array', $entities);
        $this->assertArrayHasKey('entities', $entities);
        $this->assertArrayHasKey('guestbook', $entities['entities']);
        $this->assertArrayHasKey('cols', $entities['entities']['guestbook']);
        $this->assertArrayHasKey('id', $entities['entities']['guestbook']['cols']);

        $tablesInConf = $writer->getTablesInConf();
        $this->assertInternalType('array', $tablesInConf);
        $this->assertContains('guestbook', $tablesInConf);
    }

    public function testGenerateConfWriteable()
    {
        $this->createPrimary();
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $writer = new Writer;
        $writer->setProtectedCols(array('.*\.user'));
        $writer->protectCols(true);
        $entities = $writer->generateConfFromDB($pdo, new Guesser);
        $this->assertInternalType('array', $entities);
        $this->assertArrayHasKey('entities', $entities);
        $this->assertArrayHasKey('guestbook', $entities['entities']);
        $this->assertArrayHasKey('cols', $entities['entities']['guestbook']);
        $this->assertArrayNotHasKey('id', $entities['entities']['guestbook']['cols']);
        $this->assertArrayNotHasKey('user', $entities['entities']['guestbook']['cols']);
        $this->assertArrayHasKey('content', $entities['entities']['guestbook']['cols']);

        // check the configuration for the date
        $created = $entities['entities']['guestbook']['cols']['created'];
        $this->assertInternalType('array', $created);
        $this->assertArrayHasKey('method', $created);
        $this->assertArrayHasKey('params', $created);
        $this->assertEquals('date', $created['method']);
        $this->assertEquals('Y-m-d H:i:s', $created['params'][0]);

        // save it
        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $writer->save($entities, $temp);
        $this->assertFileExists($temp);

        // try to read the file with the reader
        $reader = new Reader($temp);
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);

        // delete it
        unlink($temp);
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |/doesntexist is not writeable.|
     */
    public function testGenerateConfNotWriteable()
    {
        $this->createPrimary();
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $writer = new Writer;
        $writer->setProtectedCols(array('.*\.user'));
        $writer->protectCols(true);
        $entities = $writer->generateConfFromDB($pdo, new Guesser);
        // save it
        $writer->save($entities, '/doesntexist/toto');
    }
}
