<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Console\Application;
use Edyan\Neuralyzer\Console\Commands\RunCommand as Command;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Symfony\Component\Console\Tester\CommandTester;

class RunCommandTest extends AbstractConfigurationDB
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
            '--config' => __DIR__ . '/../../_files/config.right.yaml'
        ]);
    }


    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp |--mode could be only "queries" or "batch"|
     */
    public function testExecuteBadMode()
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
            '--config' => __DIR__ . '/../../_files/config.right.yaml',
            '--db' => 'test_db',
            '--mode' => 'wrong'
        ]);
    }


    public function testExecuteWrongPass()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->expectException("\Doctrine\DBAL\Driver\PDOException");
            $this->expectExceptionMessageRegExp("|Login failed for user 'sa'|");
        } else if (strpos(getenv('DB_DRIVER'), 'pgsql')) {
            $this->expectException("\Doctrine\DBAL\Exception\ConnectionException");
            $this->expectExceptionMessageRegExp("|password authentication failed for user|");
        } else {
            $this->expectException("\Doctrine\DBAL\Exception\ConnectionException");
            $this->expectExceptionMessageRegExp("|Access denied for user.*|");
        }

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
            '--config' => __DIR__ . '/../../_files/config.right.yaml'
        ]);
    }

    /**
    * @expectedException InvalidArgumentException
    * @expectedExceptionMessageRegExp |wrong_driver unknown.*|
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
            '--config' => __DIR__ . '/../../_files/config.right.yaml'
        ]);
    }

    /**
     * @expectedExceptionMessageRegExp |An exception occurred while executing.*|
     */
    public function testExecuteWrongTablePasswordOnCLI()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->expectException("\Doctrine\DBAL\DBALException");
        } else {
            $this->expectException("\Doctrine\DBAL\Exception\TableNotFoundException");
        }

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
            '--config' => __DIR__ . '/../../_files/config.right.notable.yaml'
        ]);
    }

    public function testExecuteErrorCode()
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
            '--config' => __DIR__ . '/../../_files/config.wrongfieldvalue.yaml',
            '--mode' => 'queries',
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
    }
}
