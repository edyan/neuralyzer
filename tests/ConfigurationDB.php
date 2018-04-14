<?php

namespace Edyan\Neuralyzer\Tests;

use Doctrine\DBAL\Schema\Schema;

class ConfigurationDB extends \PHPUnit\Framework\TestCase
{
    use \PHPUnit\DbUnit\TestCaseTrait;

    /**
     * Raw PDO Connection
     */
    static protected $pdo = null;

    static protected $doctrine;

    protected $tableName = 'guestbook';

    /**
     * PDO Default DB Connection for PHPUnit
     */
    private $conn;


    private $dbName;

    /**
     * For PHPUnit to manage DataSets
     */
    final public function getConnection()
    {
        $this->dbName = getenv('DB_NAME');

        $driver = getenv('DB_DRIVER');
        if (substr($driver, 0, 4) === 'pdo_') {
            $driver = substr($driver, 4);
        }

        $conString = $driver . ':dbname=' . $this->dbName . ';host=' . getenv('DB_HOST');
        if ($driver === 'sqlsrv') {
            $conString = $driver . ':Database=' . $this->dbName . ';Server=' . getenv('DB_HOST');
        }

        // From : https://phpunit.de/manual/current/en/database.html#database.implementing-getdataset
        // Conn does not contain the defaultDbConnection
        if ($this->conn === null) {
            // Pdo has never been initialized
            if (self::$pdo == null) {
                self::$pdo = new \PDO($conString, getenv('DB_USER'), getenv('DB_PASSWORD'));
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo);
        }

        return $this->conn;
    }


    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        $this->createTable();

        return $this->createFlatXmlDataSet(__DIR__ . '/_files/dataset.xml');
    }


    public function createPrimary()
    {
        $schemaManager = $this->getDoctrine()->getSchemaManager();

        $fromSchema = $schemaManager->createSchema();
        $toSchema = clone $fromSchema;
        $table = $toSchema->getTable($this->tableName);
        $table->setPrimaryKey(['id']);
        $table->changeColumn('id', ['autoincrement' => true]);

        $this->doctrineMigrate($fromSchema, $toSchema);
    }


    public function dropTable()
    {
        $schemaManager = $this->getDoctrine()->getSchemaManager();

        $fromSchema = $schemaManager->createSchema();
        $toSchema = clone $fromSchema;
        $toSchema->dropTable($this->tableName);

        $this->doctrineMigrate($fromSchema, $toSchema);
    }


    public function truncateTable()
    {
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $queryBuilder->delete($this->tableName)->execute();
    }


    public function getDbParams()
    {
        return [
            'driver' => getenv('DB_DRIVER'),
            'host' => getenv('DB_HOST'),
            'dbname' => getenv('DB_NAME'),
            'user' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
        ];
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


    protected function createTable()
    {
        try {
            $this->dropTable();
        } catch (\Exception $e) {
            // Pass
        }

        $schema = new Schema();
        $myTable = $schema->createTable($this->tableName);
        $myTable->addColumn('id', 'integer', ['unsigned' => true]);
        $myTable->addColumn('content', 'text');
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
        if (empty($queries)) {
            return;
        }

        foreach ($queries as $query) {
            $this->getDoctrine()->executeQuery($query);
        }
    }


    private function doctrineMigrate(Schema $fromSchema, Schema $toSchema)
    {
        $queries = $fromSchema->getMigrateToSql($toSchema, $this->getDoctrine()->getDatabasePlatform());
        if (empty($queries)) {
            return;
        }

        foreach ($queries as $query) {
            $this->getDoctrine()->executeQuery($query);
        }
    }
}
