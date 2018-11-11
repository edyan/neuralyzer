<?php
/**
 * Created by IntelliJ IDEA.
 * User: Emmanuel
 * Date: 11/11/18
 * Time: 20:27
 */

namespace Edyan\Neuralyzer\Tests\Doctrine\Type;

use Doctrine\DBAL\Platforms\MySQL57Platform;
use Edyan\Neuralyzer\Doctrine\Type\Enum;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        // already registered
        if (\Doctrine\DBAL\Types\Type::hasType('neuralyzer_enum')) {
            return;
        }

        // Else register
        // Manage specific types such as enum
        \Doctrine\DBAL\Types\Type::addType(
            'neuralyzer_enum',
            'Edyan\Neuralyzer\Doctrine\Type\Enum'
        );
    }

    public function testGetName()
    {
        $type = \Doctrine\DBAL\Types\Type::getType('neuralyzer_enum');
        $this->assertSame('enum', $type->getName());
    }

    public function testGetSQLDeclaration()
    {
        $type = \Doctrine\DBAL\Types\Type::getType('neuralyzer_enum');
        $this->assertSame('ENUM()', $type->getSQLDeclaration([], new MySQL57Platform()));
    }

    public function testRequiresSQLCommentHint()
    {
        $type = \Doctrine\DBAL\Types\Type::getType('neuralyzer_enum');
        $this->assertTrue($type->requiresSQLCommentHint(new MySQL57Platform()));
    }
}
