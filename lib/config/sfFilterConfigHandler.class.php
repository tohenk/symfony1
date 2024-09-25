<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) 2004-2006 Sean Kerr <sean@code-box.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use NTLAB\Object\PHP as PHPObj;

/**
 * sfFilterConfigHandler allows you to register filters with the system.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Sean Kerr <sean@code-box.org>
 */
class sfFilterConfigHandler extends sfYamlConfigHandler
{
    /**
     * Executes this configuration handler.
     *
     * @param array $configFiles An array of absolute filesystem path to a configuration file
     *
     * @return string Data to be written to a cache file
     *
     * @throws sfConfigurationException If a requested configuration file does not exist or is not readable
     * @throws sfParseException         If a requested configuration file is improperly formatted
     */
    public function execute($configFiles)
    {
        // parse the yaml
        $config = static::getConfiguration($configFiles);

        // init our data and includes arrays
        $data = [];
        $includes = [];

        $execution = false;
        $rendering = false;

        // let's do our fancy work
        foreach ($config as $category => $keys) {
            if (isset($keys['enabled']) && !$keys['enabled']) {
                continue;
            }

            if (!isset($keys['class'])) {
                // missing class key
                throw new sfParseException(sprintf('Configuration file "%s" specifies category "%s" with missing class key.', $configFiles[0], $category));
            }

            $class = $keys['class'];

            if (isset($keys['file'])) {
                if (!is_readable($keys['file'])) {
                    // filter file doesn't exist
                    throw new sfParseException(sprintf('Configuration file "%s" specifies class "%s" with nonexistent or unreadable file "%s".', $configFiles[0], $class, $keys['file']));
                }

                // append our data
                $includes[] = sprintf("require_once('%s');\n", $keys['file']);
            }

            $condition = true;
            if (isset($keys['param']['condition'])) {
                $condition = $keys['param']['condition'];
                unset($keys['param']['condition']);
            }

            $type = isset($keys['param']['type']) ? $keys['param']['type'] : null;
            unset($keys['param']['type']);

            if ($condition) {
                // parse parameters
                $parameters = PHPObj::inline(isset($keys['param']) ? $keys['param'] : null);

                // append new data
                if ('security' == $type) {
                    $data[] = $this->addSecurityFilter($category, $class, $parameters);
                } else {
                    $data[] = $this->addFilter($category, $class, $parameters);
                }

                if ('rendering' == $type) {
                    $rendering = true;
                }

                if ('execution' == $type) {
                    $execution = true;
                }
            }
        }

        if (!$rendering) {
            throw new sfParseException(sprintf('Configuration file "%s" must register a filter of type "rendering".', $configFiles[0]));
        }

        if (!$execution) {
            throw new sfParseException(sprintf('Configuration file "%s" must register a filter of type "execution".', $configFiles[0]));
        }

        // compile data
        $retval = sprintf(
            "<?php\n".
            "// auto-generated by sfFilterConfigHandler\n".
            "// date: %s\n%s\n%s\n\n",
            date('Y/m/d H:i:s'),
            implode("\n", $includes),
            implode("\n", $data)
        );

        return $retval;
    }

    /**
     * @see sfConfigHandler
     */
    public static function getConfiguration(array $configFiles)
    {
        $config = static::parseYaml($configFiles[0]);
        foreach (array_slice($configFiles, 1) as $i => $configFile) {
            // we get the order of the new file and merge with the previous configurations
            $previous = $config;

            $config = [];
            foreach (static::parseYaml($configFile) as $key => $value) {
                $value = (array) $value;
                $config[$key] = isset($previous[$key]) ? sfToolkit::arrayDeepMerge($previous[$key], $value) : $value;
            }

            // check that every key in previous array is still present (to avoid problem when upgrading)
            foreach (array_keys($previous) as $key) {
                if (!isset($config[$key])) {
                    throw new sfConfigurationException(sprintf('The filter name "%s" is defined in "%s" but not present in "%s" file. To disable a filter, add a "enabled" key with a false value.', $key, $configFiles[$i], $configFile));
                }
            }
        }

        $config = static::replaceConstants($config);

        foreach ($config as $category => $keys) {
            if (isset($keys['file'])) {
                $config[$category]['file'] = static::replacePath($keys['file']);
            }
        }

        return $config;
    }

    /**
     * Adds a filter statement to the data.
     *
     * @param string $category   The category name
     * @param string $class      The filter class name
     * @param array  $parameters Filter default parameters
     *
     * @return string The PHP statement
     */
    protected function addFilter($category, $class, $parameters)
    {
        return sprintf(
            "\nlist(\$class, \$parameters) = (array) sfConfig::get('sf_%s_filter', ['%s', %s]);\n".
            "\$filter = new \$class(sfContext::getInstance(), \$parameters);\n".
            '$this->register($filter);',
            $category,
            $class,
            $parameters
        );
    }

    /**
     * Adds a security filter statement to the data.
     *
     * @param string $category   The category name
     * @param string $class      The filter class name
     * @param array  $parameters Filter default parameters
     *
     * @return string The PHP statement
     */
    protected function addSecurityFilter($category, $class, $parameters)
    {
        return <<<EOF

// does this action require security?
if (\$actionInstance->isSecure()) {
    {$this->addFilter($category, $class, $parameters)}
}
EOF;
    }
}
