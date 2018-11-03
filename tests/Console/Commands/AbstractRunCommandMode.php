<?php

namespace Edyan\Neuralyzer\Tests\Console\Commands;

use Edyan\Neuralyzer\Tests\AbstractConfigurationDB;
use Symfony\Component\Console\Tester\CommandTester;

abstract class AbstractRunCommandMode extends AbstractConfigurationDB
{
    protected $mode = null;
    protected $exceptedSQLOutput = [];

    public function testExecuteRightTablePassPrompted()
    {
        $this->createPrimary();

        // We mock the DialogHelper
        $command = $this->getApplication()->find('run');

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
            '--config' => __DIR__ . '/../../_files/config.right.yaml',
            '--mode' => $this->mode
        ]);
        $this->assertRegExp('|Anonymizing guestbook.*|', $commandTester->getDisplay());
        $this->assertNotRegExp(
            $this->exceptedSQLOutput[getenv('DB_DRIVER')],
            $commandTester->getDisplay()
        );
    }


    public function testExecuteEmptyTable()
    {
        $this->createPrimary();
        $this->truncateTable();

        // We mock the DialogHelper
        $command = $this->getApplication()->find('run');

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
            '--config' => __DIR__ . '/../../_files/config.right.yaml',
            '--mode' => $this->mode
        ]);

        $this->assertRegExp('|.*guestbook is empty.*|', $commandTester->getDisplay());
    }


    public function testExecuteWithSQL()
    {
        $this->createPrimary();
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $oldData = $queryBuilder
            ->select('*')->from($this->tableName)
            ->orderBy('id')
            ->execute()->fetchAll();
        $this->assertInternalType('array', $oldData);
        $this->assertNotEmpty($oldData);
        $this->assertCount(2, $oldData);

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
            '--config' => __DIR__ . '/../../_files/config.right.yaml',
            '--sql' => null,
            '--mode' => $this->mode
        ]);
        $this->assertRegExp('|Anonymizing guestbook.*|', $commandTester->getDisplay());
        $this->assertRegExp(
            $this->exceptedSQLOutput[getenv('DB_DRIVER')],
            $commandTester->getDisplay()
        );

        // Now verify by a query that my changed fields have been changed and
        // other remain untouched
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder
            ->select('*')->from($this->tableName)
            ->orderBy('id')
            ->execute()->fetchAll();
        $this->assertInternalType('array', $data);
        $this->assertNotEmpty($data);
        $this->assertCount(2, $data);

        // Verify Data
        $this->assertEquals($oldData[0]['id'], $data[0]['id']);
        $this->assertEquals($oldData[0]['a_time'], $data[0]['a_time']);
        $this->assertNotEquals($oldData[0]['username'], $data[0]['username']);
        $this->assertNotEquals($oldData[0]['a_datetime'], $data[0]['a_datetime']);

        $this->assertEquals($oldData[1]['id'], $data[1]['id']);
        $this->assertEquals($oldData[1]['a_time'], $data[1]['a_time']);
        $this->assertNotEquals($oldData[1]['username'], $data[1]['username']);
        $this->assertNotEquals($oldData[1]['a_datetime'], $data[1]['a_datetime']);
        $this->assertEquals($oldData[1]['content'], $data[1]['content']); // Content was empty
    }


    public function testExecuteWithLimit()
    {
        $this->createPrimary();
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
            '--config' => __DIR__ . '/../../_files/config.right.yaml',
            '--limit' => 1,
            '--mode' => $this->mode
        ]);
        $this->assertRegExp('|Anonymizing guestbook|', $commandTester->getDisplay());
        $this->assertRegExp('|1\/1 \[============================\] 100%|', $commandTester->getDisplay());
    }


    public function testExecuteWithLimitOverTotal()
    {
        $this->createPrimary();
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
            '--config' => __DIR__ . '/../../_files/config.right.yaml',
            '--limit' => 100000,
            '--mode' => $this->mode
        ]);
        $this->assertRegExp('|Anonymizing guestbook|', $commandTester->getDisplay());
        $this->assertRegExp('|2\/2 \[============================\] 100%|', $commandTester->getDisplay());
    }


    public function executeWithLimitInsert($config)
    {
        $this->createPrimary();
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
            '--config' => __DIR__ . '/../../_files/' . $config,
            '--limit' => 10,
            '--mode' => $this->mode
        ]);

        $output = $commandTester->getDisplay();
        $this->assertRegExp('|Anonymizing guestbook|', $output);
        $this->assertRegExp('|10\/10 \[============================\] 100%|', $output);
        $this->assertNotRegExp('|.*Error.*|', $output);

        // check we have the right number of lines
        $queryBuilder = $this->getDoctrine()->createQueryBuilder();
        $data = $queryBuilder->select('*')->from($this->tableName)->execute()->fetchAll();
        $this->assertInternalType('array', $data);
        $this->assertNotEmpty($data);
        $this->assertCount(12, $data);
    }
}
