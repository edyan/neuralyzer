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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to generate an example config file
 */
class ConfigExampleCommand extends Command
{
    /**
     * Set the command shortcut to be used in configuration
     *
     * @var string
     */
    protected $command = 'config:example';


    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        // First command : Test the DB Connexion
        $this->setName($this->command)
            ->setDescription(
                'Generate an example configuration for the Anonymizer'
            )->setHelp(
                'This command will take all default values from the config validation' . PHP_EOL .
                "Usage: neuralyzer <info>{$this->command}</info>"
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dumper = new \Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
        $config = $dumper->dump(
            new \Edyan\Neuralyzer\Configuration\ConfigDefinition
        );

        $output->writeLn($config);

        return Command::SUCCESS;
    }
}
