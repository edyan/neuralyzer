<?php

namespace Edyan\Neuralyzer\Tests;

use Edyan\Neuralyzer\ContainerFactory;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class ContainerFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testCreateContainer()
    {
        $container = ContainerFactory::createContainer();
        $this->assertInstanceOf(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            $container
        );

        $this->assertInstanceOf(
            'Edyan\Neuralyzer\Utils\DBUtils',
            $container->get('Edyan\Neuralyzer\Utils\DBUtils')
        );
        $this->assertInstanceOf(
            'Edyan\Neuralyzer\Utils\Expression',
            $container->get('Edyan\Neuralyzer\Utils\Expression')
        );
        $this->assertInstanceOf(
            'Edyan\Neuralyzer\Utils\CSVWriter',
            $container->get('Edyan\Neuralyzer\Utils\CSVWriter')
        );
    }

    public function testCreateContainerNoConfiguration()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('You have requested a non-existent service "Edyan\Neuralyzer\Configuration\Reader"');

        ContainerFactory::createContainer()->get('Edyan\Neuralyzer\Configuration\Reader');
    }

    public function testCreateContainerNoAnonymizer()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('You have requested a non-existent service "Edyan\Neuralyzer\Anonymizer\DB"');

        ContainerFactory::createContainer()->get('Edyan\Neuralyzer\Anonymizer\DB');
    }
}
