<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Console\Application;
use Inet\Neuralyzer\Console\Commands\RunCommand as Command;
use Inet\Neuralyzer\Configuration\Reader;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigGenerateCommandTest extends ConfigurationDB
{
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |Database name is required \(--db\)|
     */
    public function testExecuteNoDB()
    {
        $application = new Application();
        $application->add(new Command());

        $command = $application->find('config:generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
        ]);
    }

    /**
     * @expectedException Doctrine\DBAL\Exception\ConnectionException
     * @expectedExceptionMessageRegExp |An exception occurred in driver: SQLSTATE.*|
     */
    public function testExecuteWrongPass()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
        ]);
    }

    public function testExecuteWorking()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');

        $helper = $this->createMock('\Symfony\Component\Console\Helper\QuestionHelper');
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--host' => getenv('DB_HOST'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--file' => $temp,
        ]);
        $this->assertRegExp('|Configuration written to.*|', $commandTester->getDisplay());
        $this->assertFileExists($temp);

        // try to read the file with the reader
        $reader = new Reader($temp);
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('cols', $values['entities']['guestbook']);
        $this->assertArrayHasKey('username', $values['entities']['guestbook']['cols']);
        $this->assertArrayNotHasKey('id', $values['entities']['guestbook']['cols']);

        // remove the file
        unlink($temp);
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |No tables to read in that database|
     */
    public function testExecuteProtectTable()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');

        $helper = $this->createMock('\Symfony\Component\Console\Helper\QuestionHelper');
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--host' => getenv('DB_HOST'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--file' => $temp,
            '--ignore-table' => ['guestbook'],
        ]);
    }


    public function testExecuteProtectField()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');

        $helper = $this->createMock('\Symfony\Component\Console\Helper\QuestionHelper');
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--host' => getenv('DB_HOST'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--file' => $temp,
            '--ignore-field' => ['.*\.username'],
        ]);
        $this->assertRegExp('|Configuration written to.*|', $commandTester->getDisplay());
        $this->assertFileExists($temp);

        // try to read the file with the reader
        $reader = new Reader($temp);
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('cols', $values['entities']['guestbook']);
        $this->assertArrayNotHasKey('id', $values['entities']['guestbook']['cols']);
        $this->assertArrayNotHasKey('username', $values['entities']['guestbook']['cols']);

        // remove the file
        unlink($temp);
    }

    public function testExecuteProtectId()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');

        $helper = $this->createMock('\Symfony\Component\Console\Helper\QuestionHelper');
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--host' => getenv('DB_HOST'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--file' => $temp,
            '--protect' => null,
        ]);
        $this->assertRegExp('|Configuration written to.*|', $commandTester->getDisplay());
        $this->assertFileExists($temp);

        // try to read the file with the reader
        $reader = new Reader($temp);
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('cols', $values['entities']['guestbook']);
        $this->assertArrayHasKey('username', $values['entities']['guestbook']['cols']);
        $this->assertArrayNotHasKey('id', $values['entities']['guestbook']['cols']);

        // remove the file
        unlink($temp);
    }
}
