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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Command to generate a config file from a DB
 */
class ConfigGenerateCommand extends Command
{

    /**
     * Set the command shortcut to be used in configuration
     *
     * @var string
     */
    protected $command = 'config:generate';

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
                 'This command will connect to a DB and extract a list of tables / fields to a yaml file' . PHP_EOL .
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
                 "Password (or it'll be prompted)"
             )->addOption(
                 'file',
                 'f',
                 InputOption::VALUE_REQUIRED,
                 'File',
                 'anon.yml'
             )->addOption(
                 'protect',
                 null,
                 InputOption::VALUE_NONE,
                 'Protect IDs and other fields'
             )->addOption(
                 'ignore-table',
                 null,
                 InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                 'Table to ignore. Can be repeated'
             )->addOption(
                 'ignore-field',
                 null,
                 InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                 'Field to ignore. Regexp in the form "table.field". Can be repeated'
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
        if (empty($db)) {
            throw new \InvalidArgumentException('You must define the database name with --db');
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
        } catch (\Exception $e) {
            throw new \RuntimeException("Can't connect to the database. Check your credentials");
        }

        $writer = new \Inet\Neuralyzer\Configuration\Writer;
        $ignoreFields = $input->getOption('ignore-field');
        $writer->protectCols($input->getOption('protect'));
        // Override the protection if fields are defined
        if (!empty($ignoreFields)) {
            $writer->protectCols(true);
            $writer->setProtectedCols($ignoreFields);
        }
        $writer->setIgnoredTables($input->getOption('ignore-table'));
        $data = $writer->generateConfFromDB($pdo, new \Inet\Neuralyzer\Guesser);
        $writer->save($data, $input->getOption('file'));

        $output->writeln('<comment>Configuration written to ' . $input->getOption('file') . '</comment>');
    }
}
