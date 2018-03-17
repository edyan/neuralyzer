<?php

namespace Inet\Neuralyzer\Tests;

class ConfigurationDB extends \PHPUnit\Framework\TestCase
{
    use \PHPUnit\DbUnit\TestCaseTrait;

    static protected $pdo = null;
    private $conn = null;
    private $dbName;
    private $tableName = 'guestbook';

    final public function getConnection()
    {
        $this->dbName = getenv('DB_NAME');
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new \PDO(
                    getenv('DB_DRIVER') . ':dbname=' . $this->dbName . ';host=' . getenv('DB_HOST'),
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
        self::$pdo->exec($this->getCreateSQL());

        return $this->createFlatXmlDataSet(__DIR__ . '/_files/dataset.xml');
    }

    public function createPrimary()
    {
        self::$pdo->exec("ALTER TABLE `{$this->tableName}` ADD PRIMARY KEY (`id`);");
    }

    public function dropTable()
    {
        self::$pdo->exec("DROP TABLE IF EXISTS `{$this->tableName}`;");
    }

    public function truncateTable()
    {
        self::$pdo->exec("TRUNCATE TABLE `{$this->tableName}`;");
    }

    protected function getCreateSQL()
    {
        if (getenv('DB_DRIVER') === 'mysql') {
            return <<<QUERY
            DROP TABLE IF EXISTS `{$this->tableName}`;
            CREATE TABLE `{$this->tableName}` (
                `id` int(10) UNSIGNED NOT NULL,
                `content` text NULL,
                `user` varchar(200) NULL,
                `created` datetime NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
QUERY;
        }

        if (getenv('DB_DRIVER') === 'pgsql') {
            return <<<QUERY
            DROP TABLE IF EXISTS {$this->tableName};
            CREATE TABLE {$this->tableName} (
                id int PRIMARY KEY,
                content text NULL,
                "user" varchar (200) NULL,
                created timestamp NULL
            );
QUERY;
        }

        throw new \InvalidArgumentException("No create SQL for driver " . getenv('DB_DRIVER'));


    }
}
