<?php

namespace Inet\Neuralyzer\Tests;

use Doctrine\DBAL\Schema\Schema;

class ConfigurationDB extends \PHPUnit\Framework\TestCase
{
    use \PHPUnit\DbUnit\TestCaseTrait;

    /**
     * Raw PDO Connection
     */
    static protected $pdo = null;

    protected $tableName = 'guestbook';

    protected $doctrine;

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

        ###### FOR US
        $this->doctrine = \Doctrine\DBAL\DriverManager::getConnection(
            $this->getDbParams(),
            new \Doctrine\DBAL\Configuration
        );

        ###### FOR PHPUNIT
        $driver = getenv('DB_DRIVER');
        if (substr($driver, 0, 4) === 'pdo_') {
            $driver = substr($driver, 4);
        }

        // From : https://phpunit.de/manual/current/en/database.html#database.implementing-getdataset
        // Conn does not contain the defaultDbConnection
        if ($this->conn === null) {
            // Pdo has never been initialized
            if (self::$pdo == null) {
                self::$pdo = new \PDO(
                    $driver . ':dbname=' . $this->dbName . ';host=' . getenv('DB_HOST'),
                    getenv('DB_USER'),
                    getenv('DB_PASSWORD')
                );
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
        $schemaManager = $this->doctrine->getSchemaManager();

        $fromSchema = $schemaManager->createSchema();
        $toSchema = clone $fromSchema;
        $table = $toSchema->getTable($this->tableName);
        $table->setPrimaryKey(['id']);

        $this->doctrineMigrate($fromSchema, $toSchema);
    }


    public function dropTable()
    {
        $schemaManager = $this->doctrine->getSchemaManager();

        $fromSchema = $schemaManager->createSchema();
        $toSchema = clone $fromSchema;
        $toSchema->dropTable($this->tableName);

        $this->doctrineMigrate($fromSchema, $toSchema);
    }


    public function truncateTable()
    {
        $queryBuilder = $this->doctrine->createQueryBuilder();
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

        $queries = $schema->toSql($this->doctrine->getDatabasePlatform());
        if (empty($queries)) {
            return;
        }

        foreach ($queries as $query) {
            $this->doctrine->executeQuery($query);
        }
    }


    private function doctrineMigrate(Schema $fromSchema, Schema $toSchema)
    {
        $queries = $fromSchema->getMigrateToSql($toSchema, $this->doctrine->getDatabasePlatform());
        if (empty($queries)) {
            return;
        }

        foreach ($queries as $query) {
            $this->doctrine->executeQuery($query);
        }
    }
}
