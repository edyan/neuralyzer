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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Command to generate a config file from a DB
 */
class ConfigGenerateCommand extends Command
{
    use DBTrait;

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

        $ignoreFields = $input->getOption('ignore-field');

        $writer = new \Inet\Neuralyzer\Configuration\Writer;
        $writer->protectCols($input->getOption('protect'));

        // Override the protection if fields are defined
        if (!empty($ignoreFields)) {
            $writer->protectCols(true);
            $writer->setProtectedCols($ignoreFields);
        }

        $writer->setIgnoredTables($input->getOption('ignore-table'));
        $data = $writer->generateConfFromDB($this->pdo, new \Inet\Neuralyzer\Guesser);
        $writer->save($data, $input->getOption('file'));

        $output->writeln('<comment>Configuration written to ' . $input->getOption('file') . '</comment>');
    }
}
