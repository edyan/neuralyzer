<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Console\Application;
use Inet\Neuralyzer\Console\Commands\AnonRunCommand as Command;
use Inet\Neuralyzer\Configuration\Reader;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigGenerateCommandTest extends ConfigurationDB
{
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |You must define the database name with --db|
     */
    public function testExecuteNoDB()
    {
        $application = new Application();
        $application->add(new Command());

        $command = $application->find('config:generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp |Can't connect to the database. Check your credentials|
     */
    public function testExecuteWrongPass()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
        ));
    }

    public function testExecute()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', array('ask'));
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--host' => getenv('DB_HOST'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--file' => $temp,
        ));
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
        $this->assertArrayHasKey('id', $values['entities']['guestbook']['cols']);
        $this->assertArrayHasKey('user', $values['entities']['guestbook']['cols']);

        // remove the file
        unlink($temp);
    }

    /**
     * @expectedException Inet\Neuralyzer\Exception\InetAnonConfigurationException
     * @expectedExceptionMessageRegExp |No tables to read in that database|
     */
    public function testExecuteProtectTable()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', array('ask'));
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--host' => getenv('DB_HOST'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--file' => $temp,
            '--ignore-table' => array('guestbook'),
        ));
    }


    public function testExecuteProtectField()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('config:generate');

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', array('ask'));
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--host' => getenv('DB_HOST'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--file' => $temp,
            '--ignore-field' => array('.*\.user'),
        ));
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
        $this->assertArrayNotHasKey('user', $values['entities']['guestbook']['cols']);

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

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', array('ask'));
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--host' => getenv('DB_HOST'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--file' => $temp,
            '--protect' => null,
        ));
        $this->assertRegExp('|Configuration written to.*|', $commandTester->getDisplay());
        $this->assertFileExists($temp);

        // try to read the file with the reader
        $reader = new Reader($temp);
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('cols', $values['entities']['guestbook']);
        $this->assertArrayHasKey('user', $values['entities']['guestbook']['cols']);
        $this->assertArrayNotHasKey('id', $values['entities']['guestbook']['cols']);

        // remove the file
        unlink($temp);
    }
}
