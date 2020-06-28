<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Exception\NeuralyzerException;
use Edyan\Neuralyzer\Exception\NeuralyzerConfigurationException;
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

    public function testExecuteNotWorking()
    {
        $this->expectException(NeuralyzerConfigurationException::class);
        $this->expectExceptionMessageMatches('|The child node "entities" at path "config" must be configured|');

        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--file'  => __DIR__ . '/../../_files/config.wrong.yaml'
        ]);

        $this->assertRegExp('|Your config is valid|', $commandTester->getDisplay());
    }


    public function testExecuteFileDoesNotExist()
    {
        $this->expectException(NeuralyzerException::class);
        $this->expectExceptionMessageMatches('|The file ".*config.doesnotexist.yaml" does not exist.|');

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
