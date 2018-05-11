<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Console\Application;
use Edyan\Neuralyzer\Console\Commands\RunCommand as Command;
use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigValidateCommandTest extends AbstractConfigurationDB
{
    public function testExecuteWorking()
    {
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--file'  => __DIR__ . '/../../_files/config.right.yaml'
        ]);

        $this->assertRegExp('|Your config is valid|', $commandTester->getDisplay());
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |The child node "entities" at path "config" must be configured|
     */
    public function testExecuteNotWorking()
    {
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--file'  => __DIR__ . '/../../_files/config.wrong.yaml'
        ]);

        $this->assertRegExp('|Your config is valid|', $commandTester->getDisplay());
    }


    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerException
     * @expectedExceptionMessageRegExp |The file ".*config.doesnotexist.yaml" does not exist.|
     */
    public function testExecuteFileDoesNotExist()
    {
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--file'  => __DIR__ . '/../../_files/config.doesnotexist.yaml'
        ]);

        $this->assertRegExp('|Your config is valid|', $commandTester->getDisplay());
    }
}
