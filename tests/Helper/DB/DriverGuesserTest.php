<?php

namespace Edyan\Neuralyzer\Tests\Helper\DB;

use Edyan\Neuralyzer\Helper\DB\DriverGuesser;
use PHPUnit\Framework\TestCase;

class DriverGuesserTest extends TestCase
{
    public function testWrongDriver()
    {
        $this->expectException(\InvalidArgumentException::class);

        DriverGuesser::getDBHelper('toto');
    }

    public function testRightDriverMySQL()
    {
        $driver = 'Edyan\Neuralyzer\Helper\DB\MySQL';
        $this->assertEquals($driver, DriverGuesser::getDBHelper('mysql'));
        $this->assertEquals($driver, DriverGuesser::getDBHelper('mysql2'));
        $this->assertEquals($driver, DriverGuesser::getDBHelper('pdo_mysql'));
    }

    public function testRightDriverPostgreSQL()
    {
        $driver = 'Edyan\Neuralyzer\Helper\DB\PostgreSQL';
        $this->assertEquals($driver, DriverGuesser::getDBHelper('pdo_pgsql'));
        $this->assertEquals($driver, DriverGuesser::getDBHelper('pgsql'));
        $this->assertEquals($driver, DriverGuesser::getDBHelper('postgres'));
        $this->assertEquals($driver, DriverGuesser::getDBHelper('postgresql'));
    }

    public function testRightDriverMsSQL()
    {
        $driver = 'Edyan\Neuralyzer\Helper\DB\SQLServer';
        $this->assertEquals($driver, DriverGuesser::getDBHelper('pdo_sqlsrv'));
        $this->assertEquals($driver, DriverGuesser::getDBHelper('mssql'));
    }
}
