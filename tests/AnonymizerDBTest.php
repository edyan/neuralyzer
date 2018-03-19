<?php

namespace Edyan\Neuralyzer\Tests;

use Edyan\Neuralyzer\Anonymizer\DB;
use Edyan\Neuralyzer\Configuration\Reader;

class AnonymizerDBTest extends ConfigurationDB
{
    private $i;

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |Can't find a primary key for 'guestbook'|
     */
    public function testWithoutPrimary()
    {
        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName);
    }

    /**
    * @expectedException Edyan\Neuralyzer\Exception\NeuralizerException
    * @expectedExceptionMessage Table guestook does not exist
     */
    public function testWrongTableName()
    {
        $this->dropTable();

        $reader = new Reader('_files/config.right.badtablename.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->processEntity('guestook');
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |No entities found. Have you loaded a configuration file ?|
     */
    public function testWithPrimaryNoConf()
    {
        $this->createPrimary();

        $db = new Db($this->getDbParams());
        $db->processEntity($this->tableName);
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |No configuration for that entity.*|
     */
    public function testWithPrimaryConfWrongTable()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.notable.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName);
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |.*SQLSTATE.*|
     */
    public function testWithPrimaryConfWrongWhere()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deletebadwhere.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName, null, false);
    }

    public function testWithPrimaryConfRightTableUpdatePretendPlusResult()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $queries = $db->processEntity($this->tableName, null, true, true);
        // Check I have the queries returned
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);
        // check no data changed
        $baseDataSet = $this->createFlatXmlDataSet(__DIR__ . '/_files/dataset.xml');
        $queryTable = $this->getConnection()->createDataSet([$this->tableName]);
        $this->assertDataSetsEqual($baseDataSet, $queryTable);
    }

    public function testWithPrimaryConfRightTableDeletePretendPlusResult()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $queries = $db->processEntity($this->tableName, null, true, true);
        // Check I have the queries returned
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('DELETE FROM guestbook WHERE', $queries[0]);
        // check no data changed
        $baseDataSet = $this->createFlatXmlDataSet(__DIR__ . '/_files/dataset.xml');
        $queryTable = $this->getConnection()->createDataSet([$this->tableName]);
        $this->assertDataSetsEqual($baseDataSet, $queryTable);
    }

    public function testWithPrimaryConfRightTableWithCallback()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        // check the callback works
        $db->processEntity($this->tableName, function ($line) {
            $this->assertGreaterThan($this->i, $line);
            $this->i = $line;
        }, true, true);

        $this->assertEquals($this->i, 2);

    }

    public function testWithPrimaryConfRightTableUpdateSimple()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $queries = $db->processEntity($this->tableName, null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);

        // check no data changed
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->setMaxResults(1)->execute()->fetchAll();
        $this->assertInternalType('array', $data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey(0, $data);
        $data = $data[0];
        $this->assertEquals(strlen('XXXX-XX-XX'), strlen($data['created']));
        $this->assertNotEquals('joe', $data['username']);
        $this->assertNotEquals('Hello buddy!', $data['content']);
    }

    public function testWithPrimaryConfRightTableDeleteOne()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $queries = $db->processEntity($this->tableName, null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('DELETE FROM guestbook WHERE', $queries[0]);

        // check that I have only one record remaining
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertInternalType('array', $data);
        $this->assertEquals(1, count($data));
        $this->assertArrayHasKey(0, $data);

        // Make sure joe has disappeared
        // And that nancy is not nancy anymore
        $data = $data[0];
        $this->assertEquals(strlen('XXXX-XX-XX'), strlen($data['created']));
        $this->assertNotEquals('joe', $data['username']);
        $this->assertNotEquals('nancy', $data['username']);
        $this->assertNotEquals('Hello buddy!', $data['content']);
        $this->assertNotEquals('I like it!', $data['content']);
    }


    public function testWithPrimaryConfRightTableDeleteAll()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteall.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $queries = $db->processEntity($this->tableName, null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertEquals('DELETE FROM guestbook', $queries[0]);

        // check that I have only one record remaining
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertInternalType('array', $data);
        $this->assertEmpty($data);
    }
}
