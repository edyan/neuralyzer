<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigExampleCommandTest extends AbstractConfigurationDB
{
    public function testExecuteWorking()
    {
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:example');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertRegExp('|action:\s+update|', $commandTester->getDisplay());
        $this->assertRegExp('|language:\s+en_US|', $commandTester->getDisplay());
    }
}
