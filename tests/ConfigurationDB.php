<?php

namespace Inet\Neuralyzer\Tests;

class ConfigurationDB extends \PHPUnit_Extensions_Database_TestCase
{
    static private $pdo = null;
    private $conn = null;
    private $i = 0;

    final public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new \PDO(
                    'mysql:dbname=' . getenv('DB_NAME') . ';host=' . getenv('DB_HOST'),
                    getenv('DB_USER'),
                    getenv('DB_PASSWORD')
                );
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo);
        }

        return $this->conn;
    }

    public function setUp()
    {
        $db = getenv('DB_NAME');
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $create = <<<QUERY
            DROP DATABASE $db;
            CREATE DATABASE $db;
            USE $db;
            CREATE TABLE `guestbook` (
              `id` int(10) UNSIGNED NOT NULL,
              `content` text NULL,
              `user` varchar(200) NULL,
              `created` datetime NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
QUERY;
        $pdo->exec($create);

        parent::setUp();
    }


    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        return $this->createFlatXmlDataSet(dirname(__FILE__).'/_files/dataset.xml');
    }

    public function tearDown()
    {
        $this->dropTable();

        parent::tearDown();
    }

    public function createPrimary()
    {
        $conn = $this->getConnection();
        $conn->getConnection()->exec("ALTER TABLE `guestbook` ADD PRIMARY KEY (`id`);");
    }

    public function dropTable()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $pdo->exec("DROP TABLE IF EXISTS `guestbook`;");
    }

    public function truncateTable()
    {
        $conn = $this->getConnection();
        $pdo = $conn->getConnection();
        $pdo->exec("TRUNCATE TABLE `guestbook`;");
    }
}
