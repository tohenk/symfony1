<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Yaml\Yaml;

/**
 * sfYamlConfigHandler is a base class for YAML (.yml) configuration handlers. This class
 * provides a central location for parsing YAML files.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class sfYamlConfigHandler extends sfConfigHandler
{
    /** @var array */
    protected $yamlConfig;

    /**
     * Parses an array of YAMLs files and merges them in one configuration array.
     *
     * @param array $configFiles An array of configuration file paths
     *
     * @return array A merged configuration array
     */
    public static function parseYamls($configFiles)
    {
        $config = [];
        foreach ($configFiles as $configFile) {
            // the first level is an environment and its value must be an array
            $values = [];
            foreach (static::parseYaml($configFile) as $env => $value) {
                if (null !== $value) {
                    $values[$env] = $value;
                }
            }

            $config = sfToolkit::arrayDeepMerge($config, $values);
        }

        return $config;
    }

    /**
     * Parses a YAML (.yml) configuration file.
     *
     * @param string $configFile An absolute filesystem path to a configuration file
     *
     * @return array|string A parsed .yml configuration
     *
     * @throws sfConfigurationException If a requested configuration file does not exist or is not readable
     * @throws sfParseException         If a requested configuration file is improperly formatted
     */
    public static function parseYaml($configFile)
    {
        if (!is_readable($configFile)) {
            // can't read the configuration
            throw new sfConfigurationException(sprintf('Configuration file "%s" does not exist or is not readable.', $configFile));
        }

        // pre-process PHP using include
        ob_start();
        include $configFile;
        $content = ob_get_clean();

        // parse our config
        $config = Yaml::parse($content);

        if (false === $config) {
            // configuration couldn't be parsed
            throw new sfParseException(sprintf('Configuration file "%s" could not be parsed', $configFile));
        }

        return null === $config ? [] : $config;
    }

    public static function flattenConfiguration($config)
    {
        $config['all'] = sfToolkit::arrayDeepMerge(
            isset($config['default']) && is_array($config['default']) ? $config['default'] : [],
            isset($config['all']) && is_array($config['all']) ? $config['all'] : []
        );

        unset($config['default']);

        return $config;
    }

    /**
     * Merges default, all and current environment configurations.
     *
     * @param array $config The main configuratino array
     *
     * @return array The merged configuration
     */
    public static function flattenConfigurationWithEnvironment($config)
    {
        return sfToolkit::arrayDeepMerge(
            isset($config['default']) && is_array($config['default']) ? $config['default'] : [],
            isset($config['all']) && is_array($config['all']) ? $config['all'] : [],
            isset($config[sfConfig::get('sf_environment')]) && is_array($config[sfConfig::get('sf_environment')]) ? $config[sfConfig::get('sf_environment')] : []
        );
    }

    /**
     * Merges configuration values for a given key and category.
     *
     * @param string $keyName  The key name
     * @param string $category The category name
     *
     * @return array The value associated with this key name and category
     */
    protected function mergeConfigValue($keyName, $category)
    {
        $values = [];

        if (isset($this->yamlConfig['all'][$keyName]) && is_array($this->yamlConfig['all'][$keyName])) {
            $values = $this->yamlConfig['all'][$keyName];
        }

        if ($category && isset($this->yamlConfig[$category][$keyName]) && is_array($this->yamlConfig[$category][$keyName])) {
            $values = array_merge($values, $this->yamlConfig[$category][$keyName]);
        }

        return $values;
    }

    /**
     * Gets a configuration value for a given key and category.
     *
     * @param string $keyName      The key name
     * @param string $category     The category name
     * @param string $defaultValue The default value
     *
     * @return string The value associated with this key name and category
     */
    protected function getConfigValue($keyName, $category, $defaultValue = null)
    {
        if (isset($this->yamlConfig[$category][$keyName])) {
            return $this->yamlConfig[$category][$keyName];
        }
        if (isset($this->yamlConfig['all'][$keyName])) {
            return $this->yamlConfig['all'][$keyName];
        }

        return $defaultValue;
    }
}
