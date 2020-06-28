<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Exception\NeuralyzerConfigurationException;
use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigGenerateCommandTest extends AbstractConfigurationDB
{
    public function testExecuteNoDB()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|Database name is required \(--db\)|');

        $command = $this->getApplication()->find('config:generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => 'toto',
        ]);
    }

    public function testExecuteWrongPass()
    {
        $except = \Doctrine\DBAL\Exception\ConnectionException::class;
        $exceptMsg = '|An exception occurred in driver: SQLSTATE.*|';
        if (strpos(getenv('DB_DRIVER'), 'sqlsrv')) {
            $except = \Doctrine\DBAL\Driver\PDOException::class;
            $exceptMsg = "|.*Login failed for user 'sa'.*|";
        }
        $this->expectException($except);
        $this->expectExceptionMessageMatches($exceptMsg);

        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:generate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--driver' => getenv('DB_DRIVER'),
            '--db' => getenv('DB_NAME'),
            '--user' => getenv('DB_USER'),
            '--host' => getenv('DB_HOST'),
            '--password' => time(),
        ]);
    }

    public function testExecuteWorking()
    {
        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:generate');

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
        $this->assertIsArray($values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('cols', $values['entities']['guestbook']);
        $this->assertArrayHasKey('username', $values['entities']['guestbook']['cols']);
        $this->assertArrayNotHasKey('id', $values['entities']['guestbook']['cols']);

        // remove the file
        unlink($temp);
    }

    public function testExecuteProtectTables()
    {
        $this->expectException(NeuralyzerConfigurationException::class);
        $this->expectExceptionMessageMatches('|No tables to read in that database|');

        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:generate');

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
            '--ignore-table' => ['guestbook', 'people'],
        ]);
    }


    public function testExecuteProtectField()
    {
        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:generate');

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
        $this->assertIsArray($values);
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
        $this->createPrimaries();
        // We mock the DialogHelper
        $command = $this->getApplication()->find('config:generate');

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
        $this->assertIsArray($values);
        $this->assertArrayHasKey('entities', $values);
        $this->assertArrayHasKey('guestbook', $values['entities']);
        $this->assertArrayHasKey('cols', $values['entities']['guestbook']);
        $this->assertArrayHasKey('username', $values['entities']['guestbook']['cols']);
        $this->assertArrayNotHasKey('id', $values['entities']['guestbook']['cols']);

        // remove the file
        unlink($temp);
    }
}
