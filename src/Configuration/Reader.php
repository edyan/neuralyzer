<?php
/**
 * neuralyzer : Data Anonymization Library and CLI Tool
 *
 * PHP Version 7.1
 *
 * @author    Emmanuel Dyan
 * @author    Rémi Sauvat
 * @copyright 2018 Emmanuel Dyan
 *
 * @package   edyan/neuralyzer
 *
 * @license   GNU General Public License v2.0
 *
 * @link      https://github.com/edyan/neuralyzer
 */

namespace Edyan\Neuralyzer\Configuration;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration Reader
 */
class Reader
{
    /**
     * Stores the config values
     *
     * @var array
     */
    protected $configValues = [];

    /**
     * Stores the depreciation messages
     *
     * @var array
     */
    private $depreciationMessages = [];


    /**
     * Set a few properties, open the config file and parse it
     *
     * @param string $configFileName
     * @param array  $configDirectories
     */
    public function __construct(string $configFileName, array $configDirectories = ['.'])
    {
        $config = Yaml::parse(file_get_contents(
            (new FileLocator($configDirectories))->locate($configFileName)
        ));
        $this->parseAndValidateConfig($config);
        $this->registerDepreciationMessages($config);
    }


    /**
     * Getter
     *
     * @return array Config Values
     */
    public function getConfigValues(): array
    {
        return $this->configValues;
    }


    /**
     * Get config values for an entity
     *
     * @param  string $entity
     *
     * @throws \InvalidArgumentException
     * @return array            Config Values
     */
    public function getEntityConfig(string $entity): array
    {
        if (!array_key_exists($entity, $this->configValues['entities'])) {
            throw new \InvalidArgumentException("$entity is not set in config");
        }

        return $this->configValues['entities'][$entity];
    }

    /**
     * Return the list of pre actions
     *
     * @return array
     */
    public function getPreActions(): array
    {
        return $this->configValues['pre_actions'];
    }

    /**
     * Return the list of entites
     *
     * @return array
     */
    public function getEntities(): array
    {
        return array_keys($this->configValues['entities']);
    }

    /**
     * Return the list of post actions
     *
     * @return array
     */
    public function getPostActions(): array
    {
        return $this->configValues['post_actions'];
    }

    /**
     * Get a list of depreciation messages
     *
     * @return array
     */
    public function getDepreciationMessages(): array
    {
        return $this->depreciationMessages;
    }

    /**
     * @param array|null $config
     */
    protected function parseAndValidateConfig(?array $config): void
    {
        $configDefinition = new ConfigDefinition();
        $this->configValues = (new Processor)->processConfiguration($configDefinition, [$config]);
    }

    /**
     * @param array $config
     */
    private function registerDepreciationMessages(array $config): void
    {
        foreach ($config['entities'] as $entity) {
            if (empty($entity)) {
                return;
            }

            if (array_key_exists('delete', $entity) || array_key_exists('delete_where', $entity)) {
                $this->depreciationMessages[] = '"delete" and "delete_where" have been deprecated in favor of pre and post_actions';
                break;
            }
        }
    }
}
