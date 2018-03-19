<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Console\Commands;

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
    /**
     * Store the DB Object
     *
     * @var \Edyan\Neuralyzer\Anonymizer\DB
     */
    private $db;

    /**
     * Set the command shortcut to be used in configuration
     *
     * @var string
     */
    private $command = 'run';

    /**
     * Symfony's Input Class for parameters and options
     *
     * @var InputInterface
     */
    private $input;

    /**
     * Symfony's Output Class to display info
     *
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
                'driver',
                null,
                InputOption::VALUE_REQUIRED,
                'Driver (check Doctrine documentation to have the list)',
                'pdo_mysql'
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
     * @param InputInterface  $input   Symfony's Input Class for parameters and options
     * @param OutputInterface $output  Symfony's Output Class to display infos
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Throw an exception immediately if we dont have the required DB parameter
        if (empty($input->getOption('db'))) {
            throw new \InvalidArgumentException('Database name is required (--db)');
        }

        $password = $input->getOption('password');
        if (is_null($password)) {
            $question = new Question('Password: ');
            $question->setHidden(true)->setHiddenFallback(false);

            $password = $this->getHelper('question')->ask($input, $output, $question);
        }

        $this->input = $input;
        $this->output = $output;

        // Anon READER
        $reader = new \Edyan\Neuralyzer\Configuration\Reader($input->getOption('config'));

        // Now work on the DB
        $this->db = new \Edyan\Neuralyzer\Anonymizer\DB([
            'driver' => $input->getOption('driver'),
            'host' => $input->getOption('host'),
            'dbname' => $input->getOption('db'),
            'user' => $input->getOption('user'),
            'password' => $password,
        ]);
        $this->db->setConfiguration($reader);

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
            $queries = $this->db->processEntity($table, function () use ($bar) {
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
            $stmt = $this->db->getConn()->prepare("SELECT COUNT(1) AS total FROM $table");
            $stmt->execute();
        } catch (\Exception $e) {
            $msg = "Could not count records in '$table' from your config : " . $e->getMessage();
            throw new \InvalidArgumentException($msg);
        }

        $data = $stmt->fetchAll();

        return (int)$data[0]['total'];
    }
}
