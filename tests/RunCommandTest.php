<?php

namespace Edyan\Neuralyzer\Tests;

use Edyan\Neuralyzer\Console\Application;
use Edyan\Neuralyzer\Console\Commands\RunCommand as Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommandTest extends ConfigurationDB
{
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |Database name is required \(--db\)|
     */
    public function testExecuteNoDB()
    {
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
            '--config' => __DIR__ . '/_files/config.right.yaml'
        ]);
    }

    /**
    * @expectedException InvalidArgumentException
    * @expectedExceptionMessageRegExp |Could not count records in 'guestbook' from your config : An exception occurred in driver.*|
    */
    public function testExecuteWrongPass()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
            '--config' => __DIR__ . '/_files/config.right.yaml'
        ]);
    }

    /**
    * @expectedException Doctrine\DBAL\DBALException
    * @expectedExceptionMessageRegExp |The given 'driver' wrong_driver is unknown.*|
    */
    public function testExecuteWrongDriver()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => 'wrong_driver',
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
            '--config' => __DIR__ . '/_files/config.right.yaml'
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |Could not count records in 'accounts' from your config : An exception occurred while .*|
     */
    public function testExecuteWrongTablePasswordOnCLI()
    {
        $this->createPrimary();
        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => getenv('DB_PASSWORD'),
            '--config' => __DIR__ . '/_files/config.right.notable.yaml'
        ]);
    }


    public function testExecuteRightTablePassPrompted()
    {
        $this->createPrimary();

        $application = new Application();
        $application->add(new Command());

        // We mock the DialogHelper
        $command = $application->find('run');

        $helper = $this->createMock('\Symfony\Component\Console\Helper\QuestionHelper');
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--config' => __DIR__ . '/_files/config.right.yaml'
        ]);

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

        $helper = $this->createMock('\Symfony\Component\Console\Helper\QuestionHelper');
        $helper->expects($this->any())
               ->method('ask')
               ->willReturn(getenv('DB_PASSWORD'));

        $command->getHelperSet()->set($helper, 'question');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--config' => __DIR__ . '/_files/config.right.yaml'
        ]);

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
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => getenv('DB_PASSWORD'),
            '--config' => __DIR__ . '/_files/config.right.yaml',
            '--sql' => null
        ]);
        $this->assertRegExp('|Anonymizing guestbook.*|', $commandTester->getDisplay());
        $this->assertRegExp('|.*UPDATE guestbook SET.*|', $commandTester->getDisplay());
    }
}
