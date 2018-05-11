<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Console\Application;
use Edyan\Neuralyzer\Console\Commands\RunCommand as Command;
use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigExampleCommand extends AbstractConfigurationDB
{
    public function testExecuteWorking()
    {
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:example');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertRegExp('|action:\s+update|', $commandTester->getDisplay());
        $this->assertRegExp('|language:\s+en_US|', $commandTester->getDisplay());
    }
}
