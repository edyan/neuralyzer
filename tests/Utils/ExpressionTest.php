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
        $this->assertInternalType('array', $res);
        $this->assertArrayHasKey(0, $res);
        $this->assertArrayHasKey(1, $res);
        $this->assertCount(2, $res);
        $this->assertSame(4, $res[0]);
        $this->assertSame(8, $res[1]);
    }

    public function testEvaluateDBQuery()
    {
        $expression = new Expression($this->createContainer());
        $databases = $expression->evaluateExpression('db.query("SHOW DATABASES")');
        $this->assertInternalType('array', $databases);

        $hasInformationSchema = false;
        foreach ($databases as $database) {
            if ($database['Database'] === 'information_schema') {
                $hasInformationSchema = true;
            }
        }
        $this->assertTrue($hasInformationSchema);
    }
}
