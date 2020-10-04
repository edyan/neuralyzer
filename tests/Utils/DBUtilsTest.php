<?php

namespace Edyan\Neuralyzer\Tests\Utils;

use Edyan\Neuralyzer\Exception\NeuralyzerException;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Edyan\Neuralyzer\Utils\DBUtils;

class DBUtilsTest extends AbstractConfigurationDB
{
    public function testCount()
    {
        $utils = $this->getDBUtils();
        $this->assertSame(2, $utils->countResults('guestbook'));
    }

    public function testGetConnWithoutConfiguring()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Make sure you have called $dbUtils->configure($params) first');

        $utils = new DBUtils;
        $utils->getConn();
    }

    public function testGetPrimaryKeyError()
    {
        $this->expectException(NeuralyzerException::class);
        $this->expectExceptionMessage("Can't find a primary key for 'guestbook'");

        $utils = $this->getDBUtils();
        $this->assertSame('id', $utils->getPrimaryKey('guestbook'));
    }


    public function testGetPrimaryKeyOK()
    {
        $this->createPrimaries();
        $utils = $this->getDBUtils();
        $this->assertSame('id', $utils->getPrimaryKey('guestbook'));
    }


    public function testGetTableCols()
    {
        $this->createPrimaries();
        $utils = $this->getDBUtils();
        $cols = $utils->getTableCols('guestbook');
        $this->assertIsArray($cols);

        $this->assertArrayHasKey('a_float', $cols);
        $this->assertArrayHasKey('length', $cols['a_float']);
        $this->assertEmpty($cols['a_smallint']['length']);
        $this->assertArrayHasKey('type', $cols['a_float']);
        $this->assertInstanceOf('Doctrine\DBAL\Types\FloatType', $cols['a_float']['type']);
        $this->assertArrayHasKey('unsigned', $cols['a_float']);
        $this->assertFalse($cols['a_float']['unsigned']);

        $this->assertArrayHasKey('a_smallint', $cols);
        $this->assertArrayHasKey('length', $cols['a_smallint']);
        $this->assertEmpty($cols['a_smallint']['length']);
        $this->assertArrayHasKey('type', $cols['a_smallint']);
        $this->assertInstanceOf('Doctrine\DBAL\Types\SmallIntType', $cols['a_smallint']['type']);
        $this->assertArrayHasKey('unsigned', $cols['a_smallint']);
        if (strpos(getenv('DB_DRIVER'), 'mysql')) {
            $this->assertTrue($cols['a_smallint']['unsigned']);
        }

        $this->assertArrayHasKey('username', $cols);
        $this->assertArrayHasKey('length', $cols['username']);
        $this->assertSame(32, $cols['username']['length']);
        $this->assertArrayHasKey('type', $cols['username']);
        $this->assertInstanceOf('Doctrine\DBAL\Types\StringType', $cols['username']['type']);
        $this->assertArrayHasKey('unsigned', $cols['username']);
        $this->assertFalse($cols['username']['unsigned']);
    }


    public function testGetRawSQLNoParams()
    {
        $this->createPrimaries();

        $db = $this->getDoctrine();
        $qb = $this->getDoctrine()->createQueryBuilder();
        $qb = $qb->select('*')->from('guestbook');

        $utils = new DBUtils($db);
        $sql = $utils->getRawSQL($qb);
        $this->assertEquals('SELECT * FROM guestbook', $sql);
    }

    public function testGetRawSQLParams()
    {
        $this->createPrimaries();

        $db = $this->getDoctrine();
        $qb = $this->getDoctrine()->createQueryBuilder();
        $qb = $qb->select('*')->from('guestbook')->where('id = :id');
        $qb->setParameter(':id', 1);

        $utils = new DBUtils($db);
        $sql = $utils->getRawSQL($qb);
        $this->assertEquals("SELECT * FROM guestbook WHERE id = '1'", $sql);
    }

    public function testAssertTableExistsKO()
    {
        $this->expectException(NeuralyzerException::class);
        $this->expectExceptionMessage('Table does_not_exists does not exist');

        $utils = $this->getDBUtils();
        $utils->assertTableExists('does_not_exists');
    }


    public function testAssertTableExistsOK()
    {
        $utils = $this->getDBUtils();
        $utils->assertTableExists('guestbook');
        $this->addToAssertionCount(1);
    }


    public function testGetConditionInteger()
    {
        $this->createPrimaries();
        $utils = $this->getDBUtils();
        $cols = $utils->getTableCols('guestbook');

        $condition = $utils->getCondition('a_smallint', $cols['a_smallint']);
        $expected = 'CAST((CASE a_smallint WHEN NULL THEN NULL ELSE :a_smallint END) AS INTEGER)';
        if (strpos(getenv('DB_DRIVER'), 'mysql')) {
            $expected = 'CAST((CASE a_smallint WHEN NULL THEN NULL ELSE :a_smallint END) AS UNSIGNED INTEGER)';
        }
        $this->assertEquals($expected, $condition);
    }


    public function testGetConditionString()
    {
        $this->createPrimaries();
        $utils = $this->getDBUtils();
        $cols = $utils->getTableCols('guestbook');

        $condition = $utils->getCondition('username', $cols['username']);
        $this->assertEquals('(CASE username WHEN NULL THEN NULL ELSE :username END)', $condition);
    }


    public function testGetConditionIntegerSigned()
    {
        $this->createPrimaries();
        $utils = $this->getDBUtils();
        $cols = $utils->getTableCols('guestbook');

        $condition = $utils->getCondition('an_integer', $cols['an_integer']);
        $expected = 'CAST((CASE an_integer WHEN NULL THEN NULL ELSE :an_integer END) AS INTEGER)';
        if (strpos(getenv('DB_DRIVER'), 'mysql')) {
            $expected = 'CAST((CASE an_integer WHEN NULL THEN NULL ELSE :an_integer END) AS SIGNED INTEGER)';
        }
        $this->assertEquals($expected, $condition);
    }
}
