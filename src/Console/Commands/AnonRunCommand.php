<?php
/**
 * Inet Data Anonymization
 *
 * PHP Version 5.3 -> 7.0
 *
 * @author Emmanuel Dyan
 * @author Rémi Sauvat
 * @copyright 2005-2015 iNet Process
 *
 * @package inetprocess/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link http://www.inetprocess.com
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
class AnonRunCommand extends Command
{

    /**
     * Set the command shortcut to be used in configuration
     *
     * @var string
     */
    protected $command = 'run';

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        // First command : Test the DB Connexion
        $this->setName($this->command)
             ->setDescription(
                 'Generate configuration for the Anonymizer'
             )->setHelp(
                 'This command will connect to a DB and run the anonymizer from a yaml config' . PHP_EOL .
                 "Usage: ./bin/anon <info>{$this->command} -u app -p app -f anon.yml</info>"
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
        $db = $input->getOption('db');
        // Throw an exception immediately if we dont have the required DB parameter
        if (empty($db)) {
            throw new \InvalidArgumentException('Database name is required (--db)');
        }

        $password = $input->getOption('password');
        if (is_null($password)) {
            $helper = $this->getHelper('question');
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        try {
            $pdo = new \PDO(
                "mysql:dbname=$db;host=" . $input->getOption('host'),
                $input->getOption('user'),
                $password
            );
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            throw new \RuntimeException("Can't connect to the database. Check your credentials");
        }

        // Anon READER
        $reader = new \Inet\Neuralyzer\Configuration\Reader($input->getOption('config'));

        // Now work on the DB
        $anon = new \Inet\Neuralyzer\Anonymizer\DB($pdo);
        $anon->setConfiguration($reader);

        $stopwatch = new Stopwatch();
        $stopwatch->start('Neuralyzer');
        // Get tables
        $tables = $reader->getEntities();
        foreach ($tables as $table) {
            try {
                $result = $pdo->query("SELECT COUNT(1) FROM $table");
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Could not count records in table '$table' defined in your config");
            }

            $data = $result->fetchAll(\PDO::FETCH_COLUMN);
            $total = (int)$data[0];
            if ($total === 0) {
                $output->writeln("<info>$table is empty</info>");
                continue;
            }

            $bar = new ProgressBar($output, $total);
            $bar->setRedrawFrequency($total > 100 ? 100 : 0);
            $output->writeln("<info>Anonymizing $table</info>");
            $queries = $anon->processEntity($table, function () use ($bar) {
                $bar->advance();
            }, $input->getOption('pretend'), $input->getOption('sql'));

            $output->writeln(PHP_EOL);

            if ($input->getOption('sql')) {
                $output->writeln('<comment>Queries:</comment>');
                $output->writeln(implode(PHP_EOL, $queries));
                $output->writeln(PHP_EOL);
            }
        }

        // Get memory and execution time information
        $event = $stopwatch->stop('Neuralyzer');
        $memory = round($event->getMemory() / 1024 / 1024, 2);
        $time = round($event->getDuration() / 1000, 2);
        $time = ($time > 180 ? round($time / 60, 2) . 'mins' : "$time sec");

        $output->writeln("<info>Done in $time using $memory Mb of memory</info>");
    }
}
