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
        if (getenv('DB_DRIVER') === 'pdo_sqlsrv') {
            $this->markTestSkipped(
                "Can't compare dataset with SQLServer as the fields are in a random order"
            );
        }
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
        $expectedDataSet = $this->createFlatXmlDataSet(__DIR__ . '/_files/dataset.xml');
        $queryTable = $this->getConnection()->createDataSet([$this->tableName]);

        $this->assertDataSetsEqual($expectedDataSet, $queryTable);
    }


    public function testWithPrimaryConfRightTableDeletePretendPlusResult()
    {
        if (getenv('DB_DRIVER') === 'pdo_sqlsrv') {
            $this->markTestSkipped(
                "Can't compare dataset with SQLServer as the fields are in a random order"
            );
        }
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


    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessage Col usernamez does not exist
     */
    public function testWithPrimaryConfWrongField()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.wrongfield.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName, null, false, true);
    }


    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessage You must use faker methods that generate strings: 'datetime' forbidden
     */
    public function testWithPrimaryConfBadFakerType()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.datetime-forbidden.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName, null, false, true);
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


    public function testWithPrimaryConfRightTableUpdate1500records()
    {
        $this->createPrimary();
        $this->truncateTable();

        // Insert 2003 records
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        for ($i = 1; $i <= 2003; $i++) {
            $queryBuilder
                ->insert($this->tableName)
                ->setValue('username', '?')->setParameter(0, 'TestPHPUnit')
                ->setValue('content', '?')->setParameter(1, 'TestPHPUnit')
                ->setValue('created', '?')->setParameter(2, '2010-01-01')
                ->setValue('a_bigint', '?')->setParameter(3, 99999999)
                ->setValue('a_datetime', '?')->setParameter(4, '2011-01-01 00:00:00')
                ->setValue('a_time', '?')->setParameter(5, '00:00:00')
                ->setValue('a_decimal', '?')->setParameter(6, 3.45)
                ->setValue('an_integer', '?')->setParameter(7, 3)
                ->setValue('a_smallint', '?')->setParameter(8, 3)
                ->setValue('a_float', '?')->setParameter(9, 3.56)
                ->setValue('id', '?')->setParameter(10, $i)
                ->execute();
        }

        $reader = new Reader('_files/config.right.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $total = $db->countResults($this->tableName);

        // Make sure my insert was ok
        $this->assertSame(2003, $total);
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->setMaxResults(2)->execute()->fetchAll();
        $this->assertInternalType('array', $data);
        $this->assertNotEmpty($data);
        // First line is correct
        $this->assertArrayHasKey(0, $data);
        $this->assertEquals('TestPHPUnit', $data[0]['username']);
        $this->assertEquals('TestPHPUnit', $data[0]['content']);
        // Second line is correct
        $this->assertArrayHasKey(1, $data);
        $this->assertEquals('TestPHPUnit', $data[1]['username']);
        $this->assertEquals('TestPHPUnit', $data[1]['content']);

        // Process and check I have the right number of queries
        $queries = $db->processEntity($this->tableName, null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertCount(2003, $queries);

        // check all data changed, one by one
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $rows = $queryBuilder->select('id', 'username', 'content')->from($this->tableName)->execute();
        foreach ($rows as $row) {
            $this->assertInternalType('array', $row);
            $this->assertArrayHasKey('username', $row);
            $this->assertNotEquals('TestPHPUnit', $row['username'], "ID {$row['id']} is not correct");
            $this->assertNotEmpty($row['username']);
            $this->assertArrayHasKey('content', $row);
            $this->assertNotEquals('TestPHPUnit', $row['content'], "ID {$row['id']} is not correct");
            $this->assertNotEmpty($row['content']);
        }
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


    public function testWithPrimaryConfRightTableInsert()
    {
        if (getenv('DB_DRIVER') === 'pdo_sqlsrv') {
            $this->markTestSkipped(
                "Can't manage autoincrement field with SQLServer"
            );
        }

        $this->createPrimary();
        $this->truncateTable();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__]);

        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertEmpty($data);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setLimit(20);
        $queries = $db->processEntity($this->tableName, null, false, true);
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('INSERT INTO guestbook', $queries[0]);

        // check no data changed
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertInternalType('array', $data);
        $this->assertNotEmpty($data);
        $this->assertCount(20, $data);
        $this->assertArrayHasKey(0, $data);
        $data = $data[0];
        $this->assertEquals(strlen('XXXX-XX-XX'), strlen($data['created']));
        $this->assertEquals(strlen('XX:XX:XX'), strlen($data['a_time']));
        $this->assertNotEmpty($data['username']);
        $this->assertNotEmpty($data['content']);
    }


    public function testWithPrimaryConfRightTableInsertDelete()
    {
        if (getenv('DB_DRIVER') === 'pdo_sqlsrv') {
            $this->markTestSkipped(
                "Can't manage autoincrement field with SQLServer"
            );
        }

        $this->createPrimary();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__]);

        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertCount(2, $data);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setLimit(20);

        $queries = $db->processEntity($this->tableName, null, false, false);
        $this->assertInternalType('array', $queries);
        $this->assertEmpty($queries);

        // check no data changed
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertInternalType('array', $data);
        $this->assertNotEmpty($data);
        $this->assertCount(22, $data);
    }


    public function testWithPrimaryConfRightTableInsertWithCallback()
    {
        if (getenv('DB_DRIVER') === 'pdo_sqlsrv') {
            $this->markTestSkipped(
                "Can't manage autoincrement field with SQLServer"
            );
        }

        $this->createPrimary();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__]);

        $db = new Db($this->getDbParams());
        $db->setLimit(2);
        $db->setConfiguration($reader);
        // check the callback works
        $db->processEntity($this->tableName, function ($line) {
            $this->assertGreaterThan($this->i, $line);
            $this->i = $line;
        }, true, true);

        $this->assertEquals($this->i, 2);
    }
}
