<?php

namespace Edyan\Neuralyzer\Tests;

use Edyan\Neuralyzer\Console\Application;
use Edyan\Neuralyzer\Console\Commands\RunCommand as Command;
use Edyan\Neuralyzer\Configuration\Reader;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigExampleCommand extends ConfigurationDB
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
