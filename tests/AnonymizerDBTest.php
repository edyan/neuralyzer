<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Anonymizer\DB;
use Inet\Neuralyzer\Configuration\Reader;

class AnonymizerDBTest extends ConfigurationDB
{
    public $i = 0;

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |Can't find a primary key for 'guestbook'|
     */
    public function testWithoutPrimary()
    {
        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $db->processEntity('guestbook');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |Query Error : SQLSTATE.*|
     */
    public function testWrongTableName()
    {
        $reader = new Reader('_files/config.right.badtablename.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $db->processEntity('guestook');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |No entities found. Have you loaded a configuration file ?|
     */
    public function testWithPrimaryNoConf()
    {
        $this->createPrimary();

        $db = new Db(self::$pdo);
        $db->processEntity('guestbook');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |No configuration for that entity.*|
     */
    public function testWithPrimaryConfWrongTable()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.notable.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $db->processEntity('guestbook');
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |Query DELETE Error \(SQLSTATE\[42S22\]: Column not found.*|
     */
    public function testWithPrimaryConfWrongWhere()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deletebadwhere.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $db->processEntity('guestbook', null, false);
    }

    public function testWithPrimaryConfRightTableUpdatePretendPlusResult()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, true, true);
        // Check I have the queries returned
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);
        // check no data changed
        $baseDataSet = $this->createFlatXmlDataSet(__DIR__ . '/_files/dataset.xml');
        $queryTable = $this->getConnection()->createDataSet(['guestbook']);
        $this->assertDataSetsEqual($baseDataSet, $queryTable);
    }

    public function testWithPrimaryConfRightTableDeletePretendPlusResult()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, true, true);
        // Check I have the queries returned
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('DELETE FROM guestbook WHERE', $queries[0]);
        // check no data changed
        $baseDataSet = $this->createFlatXmlDataSet(__DIR__ . '/_files/dataset.xml');
        $queryTable = $this->getConnection()->createDataSet(['guestbook']);
        $this->assertDataSetsEqual($baseDataSet, $queryTable);
    }

    public function testWithPrimaryConfRightTableWithCallback()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        // check the callback works
        $db->processEntity('guestbook', function ($line) {
            $this->assertGreaterThan($this->i, $line);
            $this->i = $line;
        }, true, true);

        $this->assertEquals($this->i, 2);

    }

    public function testWithPrimaryConfRightTableUpdate()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);

        // check no data changed
        $result = self::$pdo->query("SELECT * FROM `guestbook` LIMIT 1");
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
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('DELETE FROM guestbook WHERE', $queries[0]);

        // check that I have only one record remaining
        $result = self::$pdo->query("SELECT * FROM `guestbook`");
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
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteall.yaml', [__DIR__]);

        $db = new Db(self::$pdo);
        $db->setConfiguration($reader);
        $queries = $db->processEntity('guestbook', null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertEquals('DELETE FROM guestbook', $queries[0]);

        // check that I have only one record remaining
        $result = self::$pdo->query("SELECT * FROM `guestbook`");
        $data = $result->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $data);
        $this->assertEmpty($data);
    }
}
