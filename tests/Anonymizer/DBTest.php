<?php

namespace Edyan\Neuralyzer\Tests\Anonymizer;

use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Exception\NeuralyzerException;
use Edyan\Neuralyzer\Exception\NeuralyzerConfigurationException;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class DBTest extends AbstractConfigurationDB
{
    private $num;

    public function testWithoutPrimary()
    {
        $this->expectException(NeuralyzerException::class);
        $this->expectExceptionMessage("Can't find a primary key for 'guestbook'");

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName);
    }

    public function testWrongMode()
    {
        $this->expectException(NeuralyzerException::class);
        $this->expectExceptionMessage("Mode could be only queries or batch");

        $db = $this->getDB();
        $db->setMode('wrong');
    }

    public function testWrongTableName()
    {
        $this->expectException(NeuralyzerException::class);
        $this->expectExceptionMessage("Table guestook does not exist");

        $this->dropTables();

        $reader = new Reader('_files/config.right.badtablename.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->processEntity('guestook');
    }

    public function testWithPrimaryNoConf()
    {
        $this->expectException(NeuralyzerConfigurationException::class);
        $this->expectExceptionMessageMatches("|No entities found. Have you loaded a configuration file ?|");

        $this->createPrimaries();

        $db = $this->getDB();
        $db->processEntity($this->tableName);
    }

    public function testWithPrimaryConfWrongTable()
    {
        $this->expectException(NeuralyzerConfigurationException::class);
        $this->expectExceptionMessageMatches("|No configuration for that entity.*|");

        $this->createPrimaries();

        $reader = new Reader('_files/config.right.notable.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->processEntity($this->tableName);
    }


    public function testWithPrimaryConfWrongWhere()
    {
        $this->expectException("Edyan\Neuralyzer\Exception\NeuralyzerException");
        $this->expectExceptionMessageMatches("|.*DELETE FROM guestbook WHERE badname = 'joe'.*|");

        $this->createPrimaries();

        $reader = new Reader('_files/config.right.deletebadwhere.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
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
        $this->createPrimaries();

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(true);
        $db->setReturnRes(true);
        $queries = $db->processEntity($this->tableName);
        // Check I have the queries returned
        $this->assertIsArray($queries);
        $this->assertNotEmpty($queries);
        $this->assertCount(2, $queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);
        // check no data changed
        $this->assertEquals(
            $this->getDataSet(),
            $this->getActualDataInTable()
        );
    }

/**
 * MAYBE WE COULD GET THAT LATER
 * But for now we have to remove it as the pretend mode won't work with pre and post actions
 * How to guess what has to be ignored or not in pretend as all queries could be different !
    public function testWithPrimaryConfRightTableDeletePretendPlusResult()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->markTestSkipped(
                "Can't compare dataset with SQLServer as the fields are in a random order"
            );
        }
        $this->createPrimary();

        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(true);
        $db->setReturnRes(true);
        $queries = $db->processEntity($this->tableName);
        // Check I have the queries returned
        $this->assertIsArray($queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook SET username', $queries[0]);
        // check no data changed
        $baseDataSet = $this->createFlatXmlDataSet(__DIR__ . '/../_files/dataset.xml');
        $queryTable = $this->getConnection()->createDataSet([$this->tableName]);
        $this->assertDataSetsEqual($baseDataSet, $queryTable);
    }
*/

    public function testWithPrimaryConfRightTableWithCallback()
    {
        $this->createPrimaries();

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(true);
        $db->setReturnRes(true);
        // check the callback works
        $db->processEntity($this->tableName, function ($line) {
            $this->assertGreaterThan($this->num, $line);
            $this->num = $line;
        });

        $this->assertEquals($this->num, 2);
    }

    public function testWithPrimaryConfRightTableLoadDataCrash()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')
          && substr(gethostbyname(getenv('DB_HOST')), 0, 3) !== '127') {
            $this->markTestSkipped(
                "Can't run a batch query if the file is remote with SQL Server"
            );
        }

        $this->createPrimaries();

        $reader = new Reader('_files/config.rightAndDelete.yaml', [__DIR__ . '/..']);

        // Get Old Data
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $oldData = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertIsArray($oldData);
        $this->assertCount(2, $oldData);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setLimit(2);
        $db->setReturnRes(true);
        $db->setMode('batch');
        // Destroy the file in the middle to make sure the transaction
        // hasn't been committed
        $exception = false;
        try {
            $db->processEntity($this->tableName, function ($line) {
                $this->assertGreaterThan($this->num, $line);
                $this->num = $line;

                if ($line === 2) {
                    $finder = new \Symfony\Component\Finder\Finder;
                    $files = $finder
                        ->name('neuralyzer*')->in(sys_get_temp_dir())
                        ->date('since 1 hour ago');
                    foreach ($files as $file) {
                        unlink($file->getRealPath());
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

        $this->assertTrue($exception, "Test hasn't crashed (tmp dir = " . sys_get_temp_dir() . ")");
    }


    public function testWithPrimaryConfWrongField()
    {
        $this->expectException(NeuralyzerConfigurationException::class);
        $this->expectExceptionMessage("Col usernamez does not exist");

        $this->createPrimaries();

        $reader = new Reader('_files/config.wrongfield.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);
        $db->processEntity($this->tableName);
    }


    public function testWithPrimaryConfBadFakerType()
    {
        $this->expectException(NeuralyzerConfigurationException::class);
        $this->expectExceptionMessage("You must use faker methods that generate strings: 'datetime' forbidden");

        $this->createPrimaries();

        $reader = new Reader('_files/config.datetime-forbidden.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);
        $db->processEntity($this->tableName);
    }


    public function testWithPrimaryConfRightTableUpdateSimple()
    {
        $this->createPrimaries();

        $reader = new Reader('_files/config.right.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);
        $queries = $db->processEntity($this->tableName);
        $this->assertIsArray($queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook', $queries[0]);

        // check no data changed
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->setMaxResults(1)->execute()->fetchAll();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey(0, $data);
        $data = $data[0];
        $this->assertEquals(strlen('XXXX-XX-XX'), strlen($data['created']));
        $this->assertNotEquals('joe', $data['username']);
        $this->assertNotEquals('Hello buddy!', $data['content']);
    }


    public function testWithPrimaryConfRightTableUpdate1500records()
    {
        $this->createPrimaries();
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

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);

        $total = $db->getDbUtils()->countResults($this->tableName);

        // Make sure my insert was ok
        $this->assertSame(2003, $total);
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->setMaxResults(2)
            ->execute()->fetchAll();
        $this->assertIsArray($data);
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
        $this->assertIsArray($queries);
        $this->assertCount(2003, $queries);

        // check all data changed, one by one
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $rows = $queryBuilder->select('id', 'username', 'content')->from($this->tableName)->execute();
        foreach ($rows as $row) {
            $this->assertIsArray($row);
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
        $this->createPrimaries();

        $reader = new Reader('_files/config.right.deleteone.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);

        $queries = $db->processEntity($this->tableName);
        $this->assertIsArray($queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('UPDATE guestbook SET username', $queries[0]);

        // check that I have only one record remaining
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertIsArray($data);
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
        $this->createPrimaries();

        $reader = new Reader('_files/config.right.deleteall.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setPretend(false);
        $db->setReturnRes(true);

        $queries = $db->processEntity($this->tableName);
        $this->assertIsArray($queries);
        $this->assertEmpty($queries);

        // check that I have only one record remaining
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }


    public function testWithPrimaryConfRightTableInsert()
    {
        $this->createPrimaries();
        $this->truncateTable();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__ . '/..']);

        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertEmpty($data);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setLimit(20);
        $db->setPretend(false);
        $db->setReturnRes(true);

        $queries = $db->processEntity($this->tableName);
        $this->assertIsArray($queries);
        $this->assertNotEmpty($queries);
        $this->assertStringStartsWith('INSERT INTO guestbook', $queries[0]);

        // check no data changed
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertIsArray($data);
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
        $this->createPrimaries();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__ . '/..']);

        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertCount(2, $data);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setLimit(20);
        $db->setPretend(false);
        $db->setReturnRes(false);

        $queries = $db->processEntity($this->tableName);
        $this->assertIsArray($queries);
        $this->assertEmpty($queries);

        // check no data changed
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertCount(22, $data);
    }


    public function testWithPrimaryConfRightTableInsertWithCallback()
    {
        $this->createPrimaries();

        $reader = new Reader('_files/config-insert.right.yaml', [__DIR__ . '/..']);

        $db = $this->getDB();
        $db->setConfiguration($reader);
        $db->setLimit(2);
        $db->setPretend(true);
        $db->setReturnRes(true);

        // check the callback works
        $db->processEntity($this->tableName, function ($line) {
            $this->assertGreaterThan($this->num, $line);
            $this->num = $line;
        });

        $this->assertEquals($this->num, 2);
    }

    public function testUniqueFakerObject()
    {
        $db = $this->getDB();
        $db->setConfiguration(new Reader('_files/config.right.yaml', [__DIR__ . '/..']));

        $class = new \ReflectionClass('\Edyan\Neuralyzer\Anonymizer\DB');
        $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('getFakerObject');
        $method->setAccessible(true);
        $method->invokeArgs($db, ['Entity1', 'field1', ['unique' => true]]);
        $method->invokeArgs($db, ['Entity1', 'field2', ['unique' => false]]);
        $method->invokeArgs($db, ['Entity1', 'field3', ['unique' => true]]);

        $prop = $class->getProperty('fakers');
        $prop->setAccessible(true);
        $fakers = $prop->getValue($db);

        $this->assertCount(1, $fakers);
        $this->assertCount(3, $fakers['Entity1']);

        $this->assertInstanceOf(\Faker\UniqueGenerator::class, $fakers['Entity1']['field1']);
        $this->assertInstanceOf(\Faker\Generator::class, $fakers['Entity1']['field2']);

        //Unique faker object for each unique field
        $this->assertNotSame($fakers['Entity1']['field1'], $fakers['Entity1']['field2']);
    }
}
