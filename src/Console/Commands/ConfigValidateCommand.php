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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to validate a config file
 */
class ConfigValidateCommand extends Command
{
    /**
     * Set the command shortcut to be used in configuration
     *
     * @var string
     */
    protected $command = 'config:validate';


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
                'Validate a configuration for the Anonymizer'
            )->setHelp(
                'This command will validate that the configuration provided is valid' . PHP_EOL .
                "Usage: neuralyzer <info>{$this->command}</info> -f neuralyzer.yml"
            )->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'File',
                'neuralyzer.yml'
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
        new \Edyan\Neuralyzer\Configuration\Reader($input->getOption('file'));

        $output->writeLn("<info>Your config is valid !</info>");
    }
}
