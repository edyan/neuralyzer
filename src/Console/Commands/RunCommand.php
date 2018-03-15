<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.0
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2017 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Inet\Neuralyzer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Command to launch an anonymization based on a config file
 */
class RunCommand extends Command
{
    use DBTrait;

    /**
     * Set the command shortcut to be used in configuration
     *
     * @var string
     */
    private $command = 'run';

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;


    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        // First command : Test the DB Connexion
        $this->setName($this->command)
            ->setDescription('Generate configuration for the Anonymizer')
            ->setHelp(
                'This command will connect to a DB and run the anonymizer from a yaml config' . PHP_EOL .
                "Usage: ./bin/neuralyzer <info>{$this->command} -u app -p app -f anon.yml</info>"
            )->addOption(
                'host',
                null,
                InputOption::VALUE_REQUIRED,
                'Host',
                '127.0.0.1'
            )->addOption(
                'db',
                'd',
                InputOption::VALUE_REQUIRED,
                'Database Name'
            )->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'User Name',
                get_current_user()
            )->addOption(
                'password',
                'p',
                InputOption::VALUE_REQUIRED,
                'Password (or prompted)'
            )->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Configuration File',
                'anon.yml'
            )->addOption(
                'pretend',
                null,
                InputOption::VALUE_NONE,
                "Don't run the queries"
            )->addOption(
                'sql',
                null,
                InputOption::VALUE_NONE,
                'Display the SQL'
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $password = $input->getOption('password');
        if (is_null($password)) {
            $question = new Question('Password: ');
            $question->setHidden(true)->setHiddenFallback(false);

            $password = $this->getHelper('question')->ask($input, $output, $question);
        }

        $this->connectToDB(
            $input->getOption('host'),
            $input->getOption('db'),
            $input->getOption('user'),
            $password
        );

        $this->input = $input;
        $this->output = $output;

        // Anon READER
        $reader = new \Inet\Neuralyzer\Configuration\Reader($input->getOption('config'));

        // Now work on the DB
        $this->anon = new \Inet\Neuralyzer\Anonymizer\DB($this->pdo);
        $this->anon->setConfiguration($reader);

        $stopwatch = new Stopwatch();
        $stopwatch->start('Neuralyzer');
        // Get tables
        $tables = $reader->getEntities();
        foreach ($tables as $table) {
            $this->anonymizeTable($table, $input, $output);
        }

        // Get memory and execution time information
        $event = $stopwatch->stop('Neuralyzer');
        $memory = round($event->getMemory() / 1024 / 1024, 2);
        $time = round($event->getDuration() / 1000, 2);
        $time = ($time > 180 ? round($time / 60, 2) . 'mins' : "$time sec");

        $output->writeln("<info>Done in $time using $memory Mb of memory</info>");
    }

    /**
     * Anonmyze a specific table and display info about the job
     *
     * @param  string $table
     */
    private function anonymizeTable(string $table)
    {
        $total = $this->countRecords($table);
        if ($total === 0) {
            $this->output->writeln("<info>$table is empty</info>");
            return;
        }

        $bar = new ProgressBar($this->output, $total);
        $bar->setRedrawFrequency($total > 100 ? 100 : 0);

        $this->output->writeln("<info>Anonymizing $table</info>");

        try {
            $queries = $this->anon->processEntity($table, function () use ($bar) {
                $bar->advance();
            }, $this->input->getOption('pretend'), $this->input->getOption('sql'));
        } catch (\Exception $e) {
            $msg = "<error>Error anonymizing $table. Message was : " . $e->getMessage() . "</error>";
            $this->output->writeln(PHP_EOL . $msg . PHP_EOL);
            return;
        }

        $this->output->writeln(PHP_EOL);

        if ($this->input->getOption('sql')) {
            $this->output->writeln('<comment>Queries:</comment>');
            $this->output->writeln(implode(PHP_EOL, $queries));
            $this->output->writeln(PHP_EOL);
        }
    }

    /**
     * Count records on a table
     * @param  string $table
     * @return int
     */
    private function countRecords(string $table): int
    {
        try {
            $result = $this->pdo->query("SELECT COUNT(1) FROM $table");
        } catch (\Exception $e) {
            $msg = "Could not count records in table '$table' defined in your config";
            throw new \InvalidArgumentException($msg);
        }

        $data = $result->fetchAll(\PDO::FETCH_COLUMN);

        return (int)$data[0];
    }
}
