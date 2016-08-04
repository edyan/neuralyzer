<?php

namespace Inet\Neuralyzer\Tests;

use Inet\Neuralyzer\Console\Application;
use Inet\Neuralyzer\Console\Commands\AnonRunCommand as Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnonRunCommandTest extends ConfigurationDB
{

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |Database name is required \(--db\)|
     */
    public function testExecuteNoDB()
    {
        $application = new Application();
        $application->add(new Command());

        $command = $application->find('run');
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
        $command = $application->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
            '--config' => __DIR__ . '/_files/config.right.yaml'
        ));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Could not count records in table 'accounts' defined in your config
     */
    public function testExecuteWrongTablePasswordOnCLI()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => getenv('DB_PASSWORD'),
            '--config' => __DIR__ . '/_files/config.right.notable.yaml'
        ));
    }

    public function testExecuteRightTablePassPrompted()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', array('ask'));
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--config' => __DIR__ . '/_files/config.right.yaml'
        ));
        $this->assertRegExp('|Anonymizing guestbook.*|', $commandTester->getDisplay());
        $this->assertNotRegExp('|.*UPDATE guestbook.*|', $commandTester->getDisplay());
    }


    public function testExecuteEmptyTable()
    {
        $this->createPrimary();
        $this->truncateTable();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', array('ask'));
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--config' => __DIR__ . '/_files/config.right.yaml'
        ));

        $this->assertRegExp('|.*guestbook is empty.*|', $commandTester->getDisplay());
    }


    public function testExecuteWithSQL()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => getenv('DB_PASSWORD'),
            '--config' => __DIR__ . '/_files/config.right.yaml',
            '--sql' => null
        ));
        $this->assertRegExp('|Anonymizing guestbook.*|', $commandTester->getDisplay());
        $this->assertRegExp('|.*UPDATE guestbook SET.*|', $commandTester->getDisplay());
    }
}
