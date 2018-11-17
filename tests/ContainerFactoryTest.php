<?php

namespace Edyan\Neuralyzer\Tests;

use Edyan\Neuralyzer\ContainerFactory;

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

    /**
     * @expectedException Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @expectedExceptionMessage You have requested a non-existent service "Edyan\Neuralyzer\Configuration\Reader"
     */
    public function testCreateContainerNoConfiguration()
    {
        ContainerFactory::createContainer()->get('Edyan\Neuralyzer\Configuration\Reader');
    }

    /**
     * @expectedException Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @expectedExceptionMessage You have requested a non-existent service "Edyan\Neuralyzer\Anonymizer\DB"
     */
    public function testCreateContainerNoAnonymizer()
    {
        ContainerFactory::createContainer()->get('Edyan\Neuralyzer\Anonymizer\DB');
    }
}
