<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RunCommandTest extends AbstractConfigurationDB
{
    public function testExecuteNoDB()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Database name is required \(--db\)|');

        // We mock the DialogHelper
        $command = $this->getApplication()->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
            '--config' => __DIR__ . '/../../_files/config.right.yaml'
        ]);
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }


    public function testExecuteBadMode()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|--mode could be only "queries" or "batch"|');

        // We mock the DialogHelper
        $command = $this->getApplication()->find('run');
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
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }


    public function testExecuteWrongPass()
    {
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->expectException("\Doctrine\DBAL\Driver\PDOException");
            $this->expectExceptionMessageMatches("|Login failed for user 'sa'|");
        } else if (strpos(getenv('DB_DRIVER'), 'pgsql')) {
            $this->expectException("\Doctrine\DBAL\Exception\ConnectionException");
            $this->expectExceptionMessageMatches("|password authentication failed for user|");
        } else {
            $this->expectException("\Doctrine\DBAL\Exception\ConnectionException");
            $this->expectExceptionMessageMatches("|Access denied for user.*|");
        }

        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('run');
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
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testExecuteWrongDriver()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|wrong_driver unknown.*|');

        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('run');
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
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testExecuteWrongTablePasswordOnCLI()
    {
        $this->expectExceptionMessageMatches('|An exception occurred while executing.*|');
        
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $this->expectException("\Doctrine\DBAL\DBALException");
        } else {
            $this->expectException("\Doctrine\DBAL\Exception\TableNotFoundException");
        }

        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('run');
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
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testExecuteErrorCode()
    {
        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('run');
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
        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }
}
