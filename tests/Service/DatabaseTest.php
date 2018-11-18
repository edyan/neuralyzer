<?php

namespace Edyan\Neuralyzer\Tests\Service;

use Edyan\Neuralyzer\Service\Database;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class DatabaseTest extends AbstractConfigurationDB
{
    public function testGetName()
    {
        $database = new Database($this->getDBUtils());
        $this->assertSame('db', $database->getName());
    }

    public function testQuery()
    {
        $database = new Database($this->getDBUtils());
        $res = $database->query('SELECT 4 AS somme');
        $this->assertEquals(4, $res[0]['somme']);
    }

    public function testBadQuery()
    {
        $this->expectException("\Edyan\Neuralyzer\Exception\NeuralyzerException");
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->expectExceptionMessageRegExp("|.*Incorrect syntax near the keyword 'AS'.*|");
        } else if (strpos(getenv('DB_DRIVER'), 'pgsql')) {
            $this->expectExceptionMessageRegExp('| syntax error at or near "SELET"|');
        } else {
            $this->expectExceptionMessageRegExp("|.*You have an error in your SQL syntax.*|");
        }

        $database = new Database($this->getDBUtils());
        $database->query('SELET 4 AS somme');
    }
}
