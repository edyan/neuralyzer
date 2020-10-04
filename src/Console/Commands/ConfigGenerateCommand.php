<?php

declare(strict_types=1);

/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author    Emmanuel Dyan
 * @author    Rémi Sauvat
 *
 * @copyright 2018 Emmanuel Dyan
 *
 * @package edyan/neuralyzer
 *
 * @license GNU General Public License v2.0
 *
 * @link https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Console\Commands;

use Edyan\Neuralyzer\Utils\DBUtils;
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
     * Store the DBUtils Object (autowiring)
     *
     * @var DBUtils
     */
    private $dbUtils;

    public function __construct(DBUtils $dbUtils)
    {
        parent::__construct();

        $this->dbUtils = $dbUtils;
    }

    protected function configure(): void
    {
        // First command : Test the DB Connexion
        $this->setName($this->command)
            ->setDescription(
                'Generate configuration for the Anonymizer'
            )->setHelp(
                'This command will connect to a DB and extract a list of tables / fields to a yaml file'.PHP_EOL.
                "Usage: neuralyzer <info>{$this->command} -u app -p app -f neuralyzer.yml</info>"
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
                "Password (or it'll be prompted)"
            )->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'File',
                'neuralyzer.yml'
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
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Edyan\Neuralyzer\Exception\NeuralyzerConfigurationException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Throw an exception immediately if we don't have the required DB parameter
        if (empty($input->getOption('db'))) {
            throw new \InvalidArgumentException('Database name is required (--db)');
        }

        $password = $input->getOption('password');
        if ($password === null) {
            $question = new Question('Password: ');
            $question->setHidden(true)->setHiddenFallback(false);

            $password = $this->getHelper('question')->ask($input, $output, $question);
        }

        $ignoreFields = $input->getOption('ignore-field');

        $this->dbUtils->configure([
            'driver' => $input->getOption('driver'),
            'host' => $input->getOption('host'),
            'dbname' => $input->getOption('db'),
            'user' => $input->getOption('user'),
            'password' => $password,
        ]);

        $writer = new \Edyan\Neuralyzer\Configuration\Writer();
        $writer->protectCols($input->getOption('protect'));

        // Override the protection if fields are defined
        if (! empty($ignoreFields)) {
            $writer->protectCols(true);
            $writer->setProtectedCols($ignoreFields);
        }

        $writer->setIgnoredTables($input->getOption('ignore-table'));
        $data = $writer->generateConfFromDB($this->dbUtils, new \Edyan\Neuralyzer\Guesser());
        $writer->save($data, $input->getOption('file'));

        $output->writeln('<comment>Configuration written to '.$input->getOption('file').'</comment>');

        return Command::SUCCESS;
    }
}
