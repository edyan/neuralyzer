<?php

namespace Edyan\Neuralyzer\Tests;

use Doctrine\DBAL\Schema\Schema;
use Edyan\Neuralyzer\ContainerFactory;
use Edyan\Neuralyzer\Anonymizer\DB;
use Edyan\Neuralyzer\Console\Application;
use Edyan\Neuralyzer\Utils\DBUtils;

abstract class AbstractConfigurationDB extends \PHPUnit\Framework\TestCase
{
    static protected $pdo = null;

    static protected $doctrine;

    protected $tableName = 'guestbook';

    private $dbName;

    public function setUp(): void
    {
        $this->dbName = getenv('DB_NAME');

        $this->connectAndCreateDB();
        $this->createTables();
        $this->createFixtures();
    }


    protected function createFixtures()
    {
        foreach ($this->getDataSet() as $row) {
            $qb = $this->getDoctrine()->createQueryBuilder();
            $qb = $qb->insert($this->tableName);
            foreach ($row as $field => $value) {
                $qb = $qb->setValue($field, ":$field");
                $qb = $qb->setParameter(":$field", $value);
            }
            $qb->execute();
        }
    }


    protected function getActualDataInTable()
    {
        $qb = $this->getDoctrine()->createQueryBuilder();
        $res = $qb->select('*')->from($this->tableName)->execute();
        return $res->fetchAll();
    }


    protected function createPrimaries()
    {
        $sm = $this->getDoctrine()->getSchemaManager();

        // Specific case of SQL Server
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->getDoctrine()->query("ALTER TABLE {$this->tableName} DROP COLUMN id");
            $this->getDoctrine()->query(
                "ALTER TABLE {$this->tableName} ADD id INT IDENTITY CONSTRAINT id_pk_g PRIMARY KEY CLUSTERED"
            );
            $this->getDoctrine()->query("ALTER TABLE people DROP COLUMN id");
            $this->getDoctrine()->query(
                "ALTER TABLE people ADD id INT IDENTITY CONSTRAINT id_pk_p PRIMARY KEY CLUSTERED"
            );
            return;
        }

        foreach ([$this->tableName, 'people'] as $table) {
            $fromSchema = $sm->createSchema();
            $toSchema = clone $fromSchema;
            $table = $toSchema->getTable($table);
            $table->setPrimaryKey(['id']);
            $table->changeColumn('id', ['autoincrement' => true]);
            $this->doctrineMigrate($fromSchema, $toSchema);
        }
    }


    protected function dropTables()
    {
        $sm = $this->getDoctrine()->getSchemaManager();

        $fromSchema = $sm->createSchema();
        $toSchema = clone $fromSchema;

        // Remove Sequences else I'll be blocked by postgres
        if (strpos(getenv('DB_DRIVER'), 'pgsql')) {
            $sequences = $sm->listSequences();
            foreach ($sequences as $sequence) {
                $toSchema->dropSequence($sequence->getName());
            }
        }

        // Remove Tables
        $toSchema->dropTable($this->tableName);
        $toSchema->dropTable('people');

        // Run
        $this->doctrineMigrate($fromSchema, $toSchema);
    }


    protected function truncateTable()
    {
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $queryBuilder->delete($this->tableName)->execute();
    }


    protected function getDbParams()
    {
        return [
            'driver' => getenv('DB_DRIVER'),
            'host' => getenv('DB_HOST'),
            'dbname' => getenv('DB_NAME'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
        ];
    }


    protected function createContainer()
    {
        $container = ContainerFactory::createContainer();

        // Configure DB Utils, required
        $dbUtils = $container->get('Edyan\Neuralyzer\Utils\DBUtils');
        $dbUtils->configure([
            'driver' => getenv('DB_DRIVER'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
            'host' => getenv('DB_HOST'),
            'dbname' => getenv('DB_NAME'),
        ]);

        return $container;
    }


    protected function getDBUtils(): DBUtils
    {
        return $this->createContainer()->get('Edyan\Neuralyzer\Utils\DBUtils');
    }


    protected function getDB(): Db
    {
        $container = $this->createContainer();
        $expression = $container->get('Edyan\Neuralyzer\Utils\Expression');
        $dbUtils = $container->get('Edyan\Neuralyzer\Utils\DBUtils');

        return new Db($expression, $dbUtils);
    }


    protected function getApplication(): Application
    {
        $container = $this->createContainer();
        $application = new Application;
        foreach ($container->getParameter('console.command.ids') as $command) {
            $application->add($container->get($command));
        }

        return $application;
    }


    protected function getDoctrine()
    {
        if (empty(self::$doctrine)) {
            self::$doctrine = \Doctrine\DBAL\DriverManager::getConnection(
                $this->getDbParams(),
                new \Doctrine\DBAL\Configuration
            );
        }

        return self::$doctrine;
    }


    protected function createTables()
    {
        try {
            $this->dropTables();
        } catch (\Exception $e) {
            // Pass
        }

        $schema = new Schema();
        $myTable = $schema->createTable($this->tableName);
        $myTable->addColumn('id', 'integer', ['unsigned' => true]);
        $myTable->addColumn('content', 'text', ['notnull' => false]);
        $myTable->addColumn('username', 'string', ['length' => 32]);
        $myTable->addColumn('created', 'date');
        $myTable->addColumn('a_bigint', 'bigint');
        $myTable->addColumn('a_datetime', 'datetime');
        $myTable->addColumn('a_time', 'time');
        $myTable->addColumn('a_decimal', 'decimal', ['precision' => 10, 'scale' => 3]);
        $myTable->addColumn('an_integer', 'integer', ['unsigned' => false]);
        $myTable->addColumn('a_smallint', 'smallint', ['unsigned' => true]);
        $myTable->addColumn('a_float', 'float', ['precision' => 10, 'scale' => 3]);

        $queries = $schema->toSql($this->getDoctrine()->getDatabasePlatform());

        // Create a second table to make sure we can have multiple anonymizations
        $schema = new Schema();
        $myTable = $schema->createTable('people');
        $myTable->addColumn('id', 'integer', ['unsigned' => true]);
        $myTable->addColumn('created', 'date');
        $myTable->addColumn('first_name', 'string', ['length' => 200]);
        $myTable->addColumn('last_name', 'string', ['length' => 200]);

        $queries = array_merge($queries, $schema->toSql($this->getDoctrine()->getDatabasePlatform()));

        if (empty($queries)) {
            return;
        }

        foreach ($queries as $query) {
            $this->getDoctrine()->query($query);
        }
    }


    protected function getDataSet(): array
    {
        return [
            [
                'id' => 1,
                'content' => 'Hello buddy!',
                'username' => 'joe',
                'created' => '2010-04-24',
                'a_bigint' => 1111111111111,
                'a_datetime' => '2010-01-09 04:02:03',
                'a_time' => '12:14:02',
                'a_decimal' => '3.454',
                'an_integer' => -123,
                'a_smallint' => 12,
                'a_float' => '3.569',
                'content' => null,
            ], [
                'id' => 2,
                'username' => 'nancy',
                'created' => '2010-04-26',
                'a_bigint' => 2222222222222222222,
                'a_datetime' => '2010-01-07 14:12:57',
                'a_time' => '09:14:04',
                'a_decimal' => '7.551',
                'an_integer' => 23,
                'a_smallint' => 1,
                'a_float' => '12.501',
                'content' => null,
            ]
        ];
    }


    protected function tearDown(): void
    {
        $finder = new \Symfony\Component\Finder\Finder;
        $files = $finder
            ->name('neuralyzer*')->in(sys_get_temp_dir());
        foreach ($files as $file) {
            unlink($file->getRealPath());
        }
    }


    protected function doctrineMigrate(Schema $fromSchema, Schema $toSchema)
    {
        $queries = $fromSchema->getMigrateToSql($toSchema, $this->getDoctrine()->getDatabasePlatform());
        if (empty($queries)) {
            return;
        }

        foreach ($queries as $query) {
            $this->getDoctrine()->query($query);
        }
    }


    /**
     * That method is used to create the DB if it does not exists
     * Useful for SQLServer
     */
    protected function connectAndCreateDB()
    {
        if (empty(self::$pdo)) {
            $driver = getenv('DB_DRIVER');
            if (substr($driver, 0, 4) === 'pdo_') {
                $driver = substr($driver, 4);
            }

            $conString = $driver . ':dbname=' . $this->dbName . ';host=' . getenv('DB_HOST');
            if ($driver === 'sqlsrv') {
                $conString = $driver . ':Server=' . getenv('DB_HOST');
            }
            self::$pdo = new \PDO($conString, getenv('DB_USER'), getenv('DB_PASSWORD'));
        }

        try {
            self::$pdo->query('CREATE DATABASE test_db');
        } catch (\Exception $e) {
            // Nothing, it's to avoid creating the DB if it exists
        }
    }
}
