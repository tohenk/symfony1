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
 * sfFactoryConfigHandler allows you to specify which factory implementation the
 * system will use.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Sean Kerr <sean@code-box.org>
 */
class sfFactoryConfigHandler extends sfYamlConfigHandler
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
        $includes = [];
        $instances = [];

        // available list of factories
        $factories = ['view_cache_manager', 'logger', 'i18n', 'controller', 'request', 'response', 'routing', 'storage', 'user', 'view_cache', 'mailer', 'service_container'];

        // let's do our fancy work
        foreach ($factories as $factory) {
            // see if the factory exists for this controller
            $keys = $config[$factory];

            if (!isset($keys['class'])) {
                // missing class key
                throw new sfParseException(sprintf('Configuration file "%s" specifies category "%s" with missing class key.', $configFiles[0], $factory));
            }

            $class = $keys['class'];

            if (isset($keys['file'])) {
                // we have a file to include
                if (!is_readable($keys['file'])) {
                    // factory file doesn't exist
                    throw new sfParseException(sprintf('Configuration file "%s" specifies class "%s" with nonexistent or unreadable file "%s".', $configFiles[0], $class, $keys['file']));
                }

                // append our data
                $includes[] = sprintf("require_once('%s');", $keys['file']);
            }

            // parse parameters
            $parameters = [];
            if (isset($keys['param'])) {
                if (!is_array($keys['param'])) {
                    throw new InvalidArgumentException(sprintf('The "param" key for the "%s" factory must be an array (in %s).', $class, $configFiles[0]));
                }

                $parameters = $keys['param'];
            }

            // append new data
            switch ($factory) {
                case 'controller':
                    $instances[] = sprintf(
                        "\$class = sfConfig::get('sf_factory_controller', '%s');\n".
                        "\$this->factories['controller'] = new \$class(\$this);\n",
                        $class
                    );

                    break;

                case 'request':
                    $parameters['no_script_name'] = sfConfig::get('sf_no_script_name');
                    $instances[] = sprintf(
                        "\$class = sfConfig::get('sf_factory_request', '%s');\n".
                        "\$this->factories['request'] = new \$class(\$this->dispatcher, [], [], sfConfig::get('sf_factory_request_parameters', %s), sfConfig::get('sf_factory_request_attributes', []));\n",
                        $class,
                        PHPObj::inline($parameters)
                    );

                    break;

                case 'response':
                    // TODO: this is a bit ugly, as it only works for sfWebRequest & sfWebResponse combination. see #3397
                    $instances[] = sprintf(
                        "\$class = sfConfig::get('sf_factory_response', '%s');\n".
                        "\$this->factories['response'] = new \$class(\$this->dispatcher, sfConfig::get('sf_factory_response_parameters', array_merge(['http_protocol' => isset(\$_SERVER['SERVER_PROTOCOL']) ? \$_SERVER['SERVER_PROTOCOL'] : null], %s)));\n".
                        "if (\$this->factories['request'] instanceof sfWebRequest\n".
                        "    && \$this->factories['response'] instanceof sfWebResponse\n".
                        "    && 'HEAD' === \$this->factories['request']->getMethod()) {\n".
                        "    \$this->factories['response']->setHeaderOnly(true);\n".
                        "}\n",
                        $class,
                        PHPObj::inline($parameters)
                    );

                    break;

                case 'storage':
                    $session_name = $parameters['session_name'];
                    unset($parameters['session_name']);

                    $defaultParameters = [];
                    $defaultParameters[] = "'auto_shutdown' => false";
                    $defaultParameters[] = "'session_id' => \$this->getRequest()->getParameter(\$session_name)";
                    $defaultParameters[] = "'session_name' => \$session_name";
                    if (is_subclass_of($class, 'sfDatabaseSessionStorage')) {
                        $defaultParameters[] = sprintf("'database' => \$this->getDatabaseManager()->getDatabase('%s')", isset($parameters['database']) ? $parameters['database'] : 'default');
                        unset($parameters['database']);
                    }

                    if (isset($config['user']['param']['timeout'])) {
                        $defaultParameters[] = sprintf("'gc_maxlifetime' => %d", $config['user']['param']['timeout']);
                    }

                    $instances[] = sprintf(
                        "if (self::\$storage) {\n".
                        "    \$this->factories['storage'] = self::\$storage;\n".
                        "} else {\n".
                        "    \$session_name = sfConfig::get('sf_factory_session_name', '%s');\n".
                        "    \$class = sfConfig::get('sf_factory_storage', '%s');\n".
                        "    \$this->factories['storage'] = new \$class(array_merge(\n".
                        "        [\n".
                        "            %s\n".
                        "        ],\n".
                        "        sfConfig::get('sf_factory_storage_parameters', %s)\n".
                        "     ));\n".
                        "     self::\$storage = \$this->factories['storage'];\n".
                        "}\n",
                        $session_name,
                        $class,
                        implode(",\n            ", $defaultParameters),
                        PHPObj::inline($parameters)
                    );

                    break;

                case 'user':
                    $instances[] = sprintf(
                        "\$class = sfConfig::get('sf_factory_user', '%s');\n".
                        "\$this->factories['user'] = new \$class(\$this->dispatcher, \$this->factories['storage'], array_merge(['auto_shutdown' => false, 'culture' => \$this->factories['request']->getParameter('sf_culture')], sfConfig::get('sf_factory_user_parameters', %s)));\n",
                        $class,
                        PHPObj::inline($parameters)
                    );

                    break;

                case 'view_cache':
                    $instances[] = sprintf(
                        "if (sfConfig::get('sf_cache')) {\n".
                        "    \$class = sfConfig::get('sf_factory_view_cache', '%s');\n".
                        "    \$cache = new \$class(sfConfig::get('sf_factory_view_cache_parameters', %s));\n".
                        "    \$this->factories['viewCacheManager'] = new %s(\$this, \$cache, %s);\n".
                        "} else {\n".
                        "    \$this->factories['viewCacheManager'] = null;\n".
                        "}\n",
                        $class,
                        PHPObj::inline($parameters),
                        $config['view_cache_manager']['class'],
                        PHPObj::inline($config['view_cache_manager']['param'])
                    );

                    break;

                case 'i18n':
                    if (isset($parameters['cache'])) {
                        $cache = sprintf(
                            "\$cache = new %s(%s);\n",
                            $parameters['cache']['class'],
                            PHPObj::inline($parameters['cache']['param'])
                        );
                        unset($parameters['cache']);
                    } else {
                        $cache = "\$cache = null;\n";
                    }

                    $instances[] = sprintf(
                        "if (sfConfig::get('sf_i18n')) {\n".
                        "    \$class = sfConfig::get('sf_factory_i18n', '%s');\n".
                        '    %s'.
                        "    \$this->factories['i18n'] = new \$class(\$this->configuration, \$cache, %s);\n".
                        "    sfWidgetFormSchemaFormatter::setTranslationCallable([\$this->factories['i18n'], '__']);\n".
                        "}\n",
                        $class,
                        $cache,
                        PHPObj::inline($parameters)
                    );

                    break;

                case 'routing':
                    if (isset($parameters['cache'])) {
                        $cache = sprintf(
                            "\$cache = new %s(%s);\n",
                            $parameters['cache']['class'],
                            PHPObj::inline($parameters['cache']['param'])
                        );
                        unset($parameters['cache']);
                    } else {
                        $cache = "\$cache = null;\n";
                    }

                    $instances[] = sprintf(
                        "\$class = sfConfig::get('sf_factory_routing', '%s');\n".
                        "%s\n".
                        "\$this->factories['routing'] = new \$class(\$this->dispatcher, \$cache, array_merge(['auto_shutdown' => false, 'context' => \$this->factories['request']->getRequestContext()], sfConfig::get('sf_factory_routing_parameters', %s)));\n".
                        "if (\$parameters = \$this->factories['routing']->parse(\$this->factories['request']->getPathInfo())) {\n".
                        "    \$this->factories['request']->addRequestParameters(\$parameters);\n".
                        "}\n",
                        $class,
                        $cache,
                        PHPObj::inline($parameters)
                    );

                    break;

                case 'logger':
                    $loggers = '';
                    if (isset($parameters['loggers'])) {
                        foreach ($parameters['loggers'] as $name => $keys) {
                            if (isset($keys['enabled']) && !$keys['enabled']) {
                                continue;
                            }

                            if (!isset($keys['class'])) {
                                // missing class key
                                throw new sfParseException(sprintf('Configuration file "%s" specifies logger "%s" with missing class key.', $configFiles[0], $name));
                            }

                            $condition = true;
                            if (isset($keys['param']['condition'])) {
                                $condition = $keys['param']['condition'];
                                unset($keys['param']['condition']);
                            }

                            if ($condition) {
                                // create logger instance
                                $loggers .= sprintf(
                                    "\$logger = new %s(\$this->dispatcher, array_merge(['auto_shutdown' => false], %s));\n".
                                    "\$this->factories['logger']->addLogger(\$logger);\n",
                                    $keys['class'],
                                    PHPObj::inline(isset($keys['param']) ? $keys['param'] : [])
                                );
                            }
                        }

                        unset($parameters['loggers']);
                    }

                    $instances[] = sprintf(
                        "\$class = sfConfig::get('sf_factory_logger', '%s');\n".
                        "\$this->factories['logger'] = new \$class(\$this->dispatcher, array_merge(['auto_shutdown' => false], sfConfig::get('sf_factory_logger_parameters', %s)));\n".
                        "%s\n",
                        $class,
                        PHPObj::inline($parameters),
                        $loggers
                    );

                    break;

                case 'mailer':
                    $instances[] = sprintf(
                        "if (!class_exists('Swift')) {\n".
                        "    \$swift_dir = sfConfig::get('sf_swiftmailer_dir', sfConfig::get('sf_symfony_lib_dir').'/vendor/swiftmailer/lib');\n".
                        "    require_once \$swift_dir.'/swift_required.php';\n".
                        "}\n".
                        "\$this->setMailerConfiguration(array_merge(['class' => sfConfig::get('sf_factory_mailer', '%s')], sfConfig::get('sf_factory_mailer_parameters', %s)));\n",
                        $class,
                        PHPObj::inline($parameters)
                    );

                    break;

                case 'service_container':
                    $instances[] =
                        "\$class = require \$this->configuration->getConfigCache()->checkConfig('config/services.yml', true);\n".
                        "\$this->setServiceContainerConfiguration(['class' => \$class]);\n";

                    break;
            }
        }

        // compile data
        $retval = sprintf(
            "<?php\n".
            "// auto-generated by sfFactoryConfigHandler\n".
            "// date: %s\n%s\n%s\n",
            date('Y/m/d H:i:s'),
            implode("\n", $includes),
            implode("\n", $instances)
        );

        return $retval;
    }

    /**
     * @see sfConfigHandler
     */
    public static function getConfiguration(array $configFiles)
    {
        $config = static::replaceConstants(static::flattenConfigurationWithEnvironment(static::parseYamls($configFiles)));

        foreach ($config as $factory => $values) {
            if (isset($values['file'])) {
                $config[$factory]['file'] = static::replacePath($values['file']);
            }
        }

        return $config;
    }
}
