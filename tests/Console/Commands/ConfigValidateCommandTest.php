<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigValidateCommandTest extends AbstractConfigurationDB
{
    public function testExecuteWorking()
    {
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--file'  => __DIR__ . '/../../_files/config.right.yaml'
        ]);

        $this->assertRegExp('|Your config is valid|', $commandTester->getDisplay());
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralyzerConfigurationException
     * @expectedExceptionMessageRegExp |The child node "entities" at path "config" must be configured|
     */
    public function testExecuteNotWorking()
    {
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--file'  => __DIR__ . '/../../_files/config.wrong.yaml'
        ]);

        $this->assertRegExp('|Your config is valid|', $commandTester->getDisplay());
    }


    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralyzerException
     * @expectedExceptionMessageRegExp |The file ".*config.doesnotexist.yaml" does not exist.|
     */
    public function testExecuteFileDoesNotExist()
    {
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--file'  => __DIR__ . '/../../_files/config.doesnotexist.yaml'
        ]);

        $this->assertRegExp('|Your config is valid|', $commandTester->getDisplay());
    }
}
