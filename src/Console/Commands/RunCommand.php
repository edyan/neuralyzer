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

use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Utils\DBUtils;
use Edyan\Neuralyzer\Utils\FileLoader;
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
     * Neuralyzer reader
     *
     * @var Reader
     */
    private $reader;


    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        // First command : Test the DB Connexion
        $this->setName($this->command)
            ->setDescription('Run Anonymizer')
            ->setHelp(
                'This command will connect to a DB and run the anonymizer from a yaml config' . PHP_EOL .
                "Usage: <info>./bin/neuralyzer {$this->command} -u app -p app -f neuralyzer.yml</info>"
            )->addOption(
                'driver',
                'D',
                InputOption::VALUE_REQUIRED,
                'Driver (check Doctrine documentation to have the list)',
                'pdo_mysql'
            )->addOption(
                'host',
                'H',
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
                'neuralyzer.yml'
            )->addOption(
                'table',
                't',
                InputOption::VALUE_REQUIRED,
                'Do a single table'
            )->addOption(
                'pretend',
                null,
                InputOption::VALUE_NONE,
                "Don't run the queries"
            )->addOption(
                'sql',
                's',
                InputOption::VALUE_NONE,
                'Display the SQL'
            )->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit the number of written records (update or insert). 100 by default for insert'
            )->addOption(
                'mode',
                'm',
                InputOption::VALUE_REQUIRED,
                'Set the mode : batch or queries',
                'batch'
            )->addOption(
                'bootstrap',
                'b',
                InputOption::VALUE_REQUIRED,
                'Provide a bootstrap file to load a custom setup before executing the command. Format /path/to/bootstrap.php'
            )
        ;
    }

    /**
     * Execute the command
     *
     * @param InputInterface  $input   Symfony's Input Class for parameters and options
     * @param OutputInterface $output  Symfony's Output Class to display infos
     *
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        if (!empty($input->getOption('bootstrap'))) {
            FileLoader::checkAndLoad($input->getOption('bootstrap'));
        }

        // Throw an exception immediately if we dont have the required DB parameter
        if (empty($input->getOption('db'))) {
            throw new \InvalidArgumentException('Database name is required (--db)');
        }

        // Throw an exception immediately if we dont have the right mode
        if (!in_array($input->getOption('mode'), ['queries', 'batch'])) {
            throw new \InvalidArgumentException('--mode could be only "queries" or "batch"');
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
        $this->reader = new Reader($input->getOption('config'));

        // Now work on the DB
        $this->db = new \Edyan\Neuralyzer\Anonymizer\DB([
            'driver' => $input->getOption('driver'),
            'host' => $input->getOption('host'),
            'dbname' => $input->getOption('db'),
            'user' => $input->getOption('user'),
            'password' => $password
        ]);
        $this->db->setConfiguration($this->reader);
        $this->db->setMode($this->input->getOption('mode'));
        $this->db->setPretend($this->input->getOption('pretend'));
        $this->db->setReturnRes($this->input->getOption('sql'));

        $stopwatch = new Stopwatch();
        $stopwatch->start('Neuralyzer');
        // Get tables
        $table = $input->getOption('table');
        $tables = empty($table) ? $this->reader->getEntities() : [$table];
        $hasErrors = false;
        foreach ($tables as $table) {
            if (!$this->anonymizeTable($table)) {
                $hasErrors = true;
            }
        }

        // Get memory and execution time information
        $event = $stopwatch->stop('Neuralyzer');
        $memory = round($event->getMemory() / 1024 / 1024, 2);
        $time = round($event->getDuration() / 1000, 2);
        $time = ($time > 180 ? round($time / 60, 2) . 'mins' : "$time sec");

        $output->writeln("<info>Done in $time using $memory Mb of memory</info>");

        return $hasErrors ? 1 : 0;
    }

    /**
     * Anonmyze a specific table and display info about the job
     *
     * @param  string $table
     */
    private function anonymizeTable(string $table): bool
    {
        $total = $this->getTotal($table);
        if ($total === 0) {
            $this->output->writeln("<info>$table is empty</info>");

            return false;
        }

        $bar = new ProgressBar($this->output, $total);
        $bar->setRedrawFrequency($total > 100 ? 100 : 0);

        $this->output->writeln("<info>Anonymizing $table</info>");

        try {
            $queries = $this->db->setLimit($total)->processEntity($table, function () use ($bar) {
                $bar->advance();
            });
        // @codeCoverageIgnoreStart
        } catch (\Exception $e) {
            $msg = "<error>Error anonymizing $table. Message was : " . $e->getMessage() . "</error>";
            $this->output->writeln(PHP_EOL . $msg . PHP_EOL);

            return false;
        }
        // @codeCoverageIgnoreEnd

        $this->output->writeln(PHP_EOL);

        if ($this->input->getOption('sql')) {
            $this->output->writeln('<comment>Queries:</comment>');
            $this->output->writeln(implode(PHP_EOL, $queries));
            $this->output->writeln(PHP_EOL);
        }

        return true;
    }


    /**
     * Define the total number of records to process for progress bar
     *
     * @param  string $table
     * @return int
     */
    private function getTotal(string $table): int
    {
        $limit = (int)$this->input->getOption('limit');
        $config = $this->reader->getEntityConfig($table);
        if ($config['action'] === 'insert') {
            return empty($limit) ? 100 : $limit;
        }

        $rows = (new DBUtils($this->db->getConn()))->countResults($table);
        if (empty($limit)) {
            return $rows;
        }

        if (!empty($limit) && $limit > $rows) {
            return $rows;
        }

        return $limit;
    }
}
