<?php

namespace Edyan\Neuralyzer\Tests\Utils;

use Edyan\Neuralyzer\Utils\Expression;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;

class ExpressionTest extends AbstractConfigurationDB
{
    public function testGetServices()
    {
        $expression = new Expression($this->createContainer());
        $services = $expression->getServices();
        $this->assertArrayHasKey('db', $services);
        $this->assertInstanceOf('Edyan\Neuralyzer\Service\Database', $services['db']);
    }

    public function testEvaluateSimpleExpression()
    {
        $expression = new Expression($this->createContainer());
        $res = $expression->evaluateExpressions(['2+2', '2*4']);
        $this->assertIsArray($res);
        $this->assertArrayHasKey(0, $res);
        $this->assertArrayHasKey(1, $res);
        $this->assertCount(2, $res);
        $this->assertSame(4, $res[0]);
        $this->assertSame(8, $res[1]);
    }

    public function testEvaluateDBQuery()
    {
        $queries = [
            'mysql' => "SELECT '".getenv('DB_NAME')."' AS db_name",
            'pdo_mysql' => "SELECT '".getenv('DB_NAME')."' AS db_name",
            'pdo_pgsql' => 'SELECT datname FROM pg_database',
            'pdo_sqlsrv' => 'SELECT name FROM master.sys.databases',
        ];
        $query = $queries[getenv('DB_DRIVER')];

        $indexes = [
            'mysql' => 'db_name',
            'pdo_mysql' => 'db_name',
            'pdo_pgsql' => 'datname',
            'pdo_sqlsrv' => 'name',
        ];
        $index = $indexes[getenv('DB_DRIVER')];

        $expression = new Expression($this->createContainer());
        $rows = $expression->evaluateExpression("db.query(\"{$query}\")");
        $this->assertIsArray($rows);

        $worked = false;
        foreach ($rows as $row) {
            if ($row[$index] === getenv('DB_NAME')) {
                $worked = true;
            }
        }
        $this->assertTrue($worked);
    }
}
