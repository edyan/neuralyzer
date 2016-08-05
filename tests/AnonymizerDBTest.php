<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Anonymizer\DB;
use Inet\Neuralyzer\Configuration\Reader;

class AnonymizerDBTest extends ConfigurationDB
{
    public $i = 0;

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonException
     * @expectedExceptionMessageRegExp |Can't find a primary key for 'guestbook'|
     */
    public function testWithoutPrimary()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $reader = new Reader('_files/config.right.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $db->processEntity('guestbook');
    }

    /**
     * @expectedException PDOException
     * @expectedExceptionMessageRegExp |Query Error : SQLSTATE.*|
     */
    public function testWrongTableName()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $reader = new Reader('_files/config.right.badtablename.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $db->processEntity('guestook');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |No entities found. Have you loaded a configuration file ?|
     */
    public function testWithPrimaryNoConf()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $db = new Db($pdo);
        $db->processEntity('guestbook');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |No configuration for that entity.*|
     */
    public function testWithPrimaryConfWrongTable()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $reader = new Reader('_files/config.right.notable.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $db->processEntity('guestbook');
    }

    /**
     * @expectedException \PDOException
     * @expectedExceptionMessageRegExp |Query Error : SQLSTATE\[42S22\]: Column not found.*|
     */
    public function testWithPrimaryConfWrongWhere()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deletebadwhere.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $db->processEntity('guestbook', null, false);
    }

    public function testWithPrimaryConfRightTableUpdatePretendPlusResult()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, true, true);
        // Check I have the queries returned
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);
        // check no data changed
        $queryTable = $conn->createDataSet(array('guestbook'));
        $this->assertDataSetsEqual($this->getDataSet(), $queryTable);
    }

    public function testWithPrimaryConfRightTableDeletePretendPlusResult()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteone.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, true, true);
        // Check I have the queries returned
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('DELETE FROM guestbook WHERE', $queries[0]);
        // check no data changed
        $queryTable = $conn->createDataSet(array('guestbook'));
        $this->assertDataSetsEqual($this->getDataSet(), $queryTable);
    }

    public function testWithPrimaryConfRightTableWithCallback()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        // check the callback works
        $me = $this;
        $db->processEntity('guestbook', function ($line) use ($me) {
            $me->assertGreaterThan($me->i, $line);
            $me->i = $line;
        }, true, true);

        $this->assertEquals($me->i, 2);

    }

    public function testWithPrimaryConfRightTableUpdate()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);

        // check no data changed
        $result = $pdo->query("SELECT * FROM `guestbook` LIMIT 1");
        $data = $result->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey(0, $data);
        $data = $data[0];
        $this->assertEquals(19, strlen($data['created']));
        $this->assertNotEquals('joe', $data['user']);
        $this->assertNotEquals('Hello buddy!', $data['content']);
    }

    public function testWithPrimaryConfRightTableDeleteOne()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteone.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('DELETE FROM guestbook WHERE', $queries[0]);

        // check that I have only one record remaining
        $result = $pdo->query("SELECT * FROM `guestbook`");
        $data = $result->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $data);
        $this->assertEquals(1, count($data));
        $this->assertArrayHasKey(0, $data);

        // Make sure joe has disappeared
        // And that nancy is not nancy anymore
        $data = $data[0];
        $this->assertEquals(19, strlen($data['created']));
        $this->assertNotEquals('joe', $data['user']);
        $this->assertNotEquals('nancy', $data['user']);
        $this->assertNotEquals('Hello buddy!', $data['content']);
        $this->assertNotEquals('I like it!', $data['content']);
    }


    public function testWithPrimaryConfRightTableDeleteAll()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteall.yaml', array(dirname(__FILE__)));

        $db = new Db($pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertEquals('DELETE FROM guestbook', $queries[0]);

        // check that I have only one record remaining
        $result = $pdo->query("SELECT * FROM `guestbook`");
        $data = $result->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $data);
        $this->assertEmpty($data);
    }
}
