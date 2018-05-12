<?php

namespace Edyan\Neuralyzer\Tests\Anonymizer;

use Edyan\Neuralyzer\Anonymizer\DB;
use Edyan\Neuralyzer\Utils\DBUtils;
use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class DBTest extends AbstractConfigurationDB
{
    private $i;

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |Can't find a primary key for 'guestbook'|
     */
    public function testWithoutPrimary()
    {
        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName);
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessage Mode could be only queries or batch
     */
    public function testWrongMode()
    {
        $db = new Db($this->getDbParams());
        $db->setMode('wrong');
    }

    /**
    * @expectedException Edyan\Neuralyzer\Exception\NeuralizerException
    * @expectedExceptionMessage Table guestook does not exist
     */
    public function testWrongTableName()
    {
        $this->dropTable();

        $reader = new Reader('_files/config.right.badtablename.yaml', [__DIR__ . '/..']);

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

        $reader = new Reader('_files/config.right.notable.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName);
    }


    public function testWithPrimaryConfWrongWhere()
    {
        $this->expectException("Doctrine\DBAL\Exception\InvalidFieldNameException");
        $this->expectExceptionMessageRegExp("|.*DELETE FROM guestbook WHERE youzer = 'joe'.*|");
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->expectException("\Doctrine\DBAL\DBALException");
        }

        $this->createPrimary();

        $reader = new Reader('_files/config.right.deletebadwhere.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->processEntity($this->tableName);
    }

    /*
     * THAT ONE CREATES THE PRIMARY
     */
    public function testWithPrimaryConfRightTableUpdatePretendPlusResult()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->markTestSkipped(
                "Can't compare dataset with SQLServer as the fields are in a random order"
            );
        }
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(true);
        $db->setReturnRes(true);
        $queries = $db->processEntity($this->tableName);
        // Check I have the queries returned
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);
        // check no data changed
        $expectedDataSet = $this->createFlatXmlDataSet(__DIR__ . '/../_files/dataset.xml');
        $queryTable = $this->getConnection()->createDataSet([$this->tableName]);

        $this->assertDataSetsEqual($expectedDataSet, $queryTable);
    }


    public function testWithPrimaryConfRightTableDeletePretendPlusResult()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->markTestSkipped(
                "Can't compare dataset with SQLServer as the fields are in a random order"
            );
        }
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(true);
        $db->setReturnRes(true);
        $queries = $db->processEntity($this->tableName);
        // Check I have the queries returned
        $this->assertInternalType('array', $queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('DELETE FROM guestbook WHERE', $queries[0]);
        // check no data changed
        $baseDataSet = $this->createFlatXmlDataSet(__DIR__ . '/../_files/dataset.xml');
        $queryTable = $this->getConnection()->createDataSet([$this->tableName]);
        $this->assertDataSetsEqual($baseDataSet, $queryTable);
    }

    public function testWithPrimaryConfRightTableWithCallback()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(true);
        $db->setReturnRes(true);
        // check the callback works
        $db->processEntity($this->tableName, function ($line) {
            $this->assertGreaterThan($this->i, $line);
            $this->i = $line;
        });

        $this->assertEquals($this->i, 2);
    }

    public function testWithPrimaryConfRightTableLoadDataCrash()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')
          && substr(gethostbyname(getenv('DB_HOST')), 0, 3) !== '127') {
            $this->markTestSkipped(
                "Can't run a batch query if the file is remote with SQL Server"
            );
        }

        $this->createPrimary();

        $reader = new Reader('_files/config.rightAndDelete.yaml', [__DIR__ . '/..']);

        // Get Old Data
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $oldData = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertInternalType('array', $oldData);
        $this->assertCount(2, $oldData);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setLimit(2);
        $db->setReturnRes(true);
        $db->setMode('batch');
        // Destroy the file in the middle to make sure the transaction
        // hasn't been committed
        $exception = false;
        try {
            $lastFile = '';

            $db->processEntity($this->tableName, function ($line) use ($lastFile) {
                $this->assertGreaterThan($this->i, $line);
                $this->i = $line;

                if ($line === 2) {
                    $finder = new \Symfony\Component\Finder\Finder;
                    $files = $finder
                        ->name('neuralyzer*')->in(sys_get_temp_dir())
                        ->date('since 1 hour ago');
                    foreach ($files as $file) {
                        file_put_contents($file->getRealPath(), '1;2;3');
                        $lastFile = $file->getRealPath();
                    }
                }
            });
        } catch (\Exception $e) {
            $exception = true;
            // everything is fine, make sure data hasn't changed
            $queryBuilder = $this->getDoctrine()->createQueryBuilder();
            $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
            $this->assertSame($oldData, $data);
        }

        $this->assertTrue($exception, "File {$lastFile} hasn't crashed the test");
    }


    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessage Col usernamez does not exist
     */
    public function testWithPrimaryConfWrongField()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.wrongfield.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);
        $db->processEntity($this->tableName);
    }


    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessage You must use faker methods that generate strings: 'datetime' forbidden
     */
    public function testWithPrimaryConfBadFakerType()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.datetime-forbidden.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);
        $db->processEntity($this->tableName);
    }


    public function testWithPrimaryConfRightTableUpdateSimple()
    {
        $this->createPrimary();

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);
        $queries = $db->processEntity($this->tableName);
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
                ->execute();
        }

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);

        $total = (new DBUtils($db->getConn()))->countResults($this->tableName);

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
        $queries = $db->processEntity($this->tableName);
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

        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);

        $queries = $db->processEntity($this->tableName);
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

        $reader = new Reader('_files/config.right.deleteall.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);

        $queries = $db->processEntity($this->tableName);
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
        $this->createPrimary();
        $this->truncateTable();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__ . '/..']);

        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertEmpty($data);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setLimit(20);
        $db->setPretend(false);
        $db->setReturnRes(true);

        $queries = $db->processEntity($this->tableName);
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
        $this->createPrimary();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__ . '/..']);

        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertCount(2, $data);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setLimit(20);
        $db->setPretend(false);
        $db->setReturnRes(false);

        $queries = $db->processEntity($this->tableName);
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
        $this->createPrimary();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__ . '/..']);

        $db = new Db($this->getDbParams());
        $db->setConfiguration($reader);
        $db->setLimit(2);
        $db->setPretend(true);
        $db->setReturnRes(true);

        // check the callback works
        $db->processEntity($this->tableName, function ($line) {
            $this->assertGreaterThan($this->i, $line);
            $this->i = $line;
        });

        $this->assertEquals($this->i, 2);
    }
}
