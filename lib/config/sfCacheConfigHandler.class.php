<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use NTLAB\Object\PHP as PHPObj;

/**
 * sfCacheConfigHandler allows you to configure cache.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class sfCacheConfigHandler extends sfYamlConfigHandler
{
    protected $cacheConfig = [];

    /**
     * Executes this configuration handler.
     *
     * @param array $configFiles An array of absolute filesystem path to a configuration file
     *
     * @return string Data to be written to a cache file
     *
     * @throws sfConfigurationException  If a requested configuration file does not exist or is not readable
     * @throws sfParseException          If a requested configuration file is improperly formatted
     * @throws sfInitializationException If a cache.yml key check fails
     */
    public function execute($configFiles)
    {
        // parse the yaml
        $this->yamlConfig = static::getConfiguration($configFiles);

        // iterate through all action names
        $data = [];
        foreach ($this->yamlConfig as $actionName => $values) {
            if ('all' == $actionName) {
                continue;
            }

            $data[] = $this->addCache($actionName);
        }

        // general cache configuration
        $data[] = $this->addCache('DEFAULT');

        // compile data
        $retval = sprintf(
            "<?php\n".
            "// auto-generated by sfCacheConfigHandler\n".
            "// date: %s\n%s\n",
            date('Y/m/d H:i:s'),
            implode('', $data)
        );

        return $retval;
    }

    /**
     * @see sfConfigHandler
     */
    public static function getConfiguration(array $configFiles)
    {
        return static::flattenConfiguration(static::parseYamls($configFiles));
    }

    /**
     * Returns a single addCache statement.
     *
     * @param string $actionName The action name
     *
     * @return string PHP code for the addCache statement
     */
    protected function addCache($actionName = '')
    {
        $data = [];

        // enabled?
        $enabled = $this->getConfigValue('enabled', $actionName);

        // cache with or without loayout
        $withLayout = $this->getConfigValue('with_layout', $actionName) ? true : false;

        // lifetime
        $lifeTime = !$enabled ? 0 : $this->getConfigValue('lifetime', $actionName, 0);

        // client_lifetime
        $clientLifetime = !$enabled ? 0 : $this->getConfigValue('client_lifetime', $actionName, $lifeTime);

        // contextual
        $contextual = $this->getConfigValue('contextual', $actionName) ? true : false;

        // vary
        $vary = $this->getConfigValue('vary', $actionName, []);
        if (!is_array($vary)) {
            $vary = [$vary];
        }

        // add cache information to cache manager
        $parameters = ['withLayout' => $withLayout, 'lifeTime' => $lifeTime, 'clientLifeTime' => $clientLifetime, 'contextual' => $contextual, 'vary' => $vary];
        $data[] = sprintf(
            "\$this->addCache(\$moduleName, '%s', %s);\n",
            $actionName,
            PHPObj::inline($parameters)
        );

        return implode("\n", $data);
    }
}
