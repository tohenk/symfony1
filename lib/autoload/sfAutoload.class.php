<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfAutoload class.
 *
 * This class is a singleton as PHP seems to be unable to register 2 autoloaders that are instances
 * of the same class (why?).
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class sfAutoload
{
    protected static $freshCache = false;
    protected static $instance;

    protected $overriden = [];
    protected $classes = [];
    protected $app;
    protected $caches = [];

    protected function __construct()
    {
    }

    /**
     * Retrieves the singleton instance of this class.
     *
     * @return sfAutoload a sfAutoload implementation instance
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new sfAutoload();
        }

        return self::$instance;
    }

    /**
     * Register sfAutoload in spl autoloader.
     *
     * @throws sfException
     */
    public static function register()
    {
        ini_set('unserialize_callback_func', 'spl_autoload_call');

        if (false === spl_autoload_register([self::getInstance(), 'autoload'])) {
            throw new sfException(sprintf('Unable to register %s::autoload as an autoloading method.', get_class(self::getInstance())));
        }
    }

    /**
     * Unregister sfAutoload from spl autoloader.
     */
    public static function unregister()
    {
        spl_autoload_unregister([self::getInstance(), 'autoload']);
    }

    /**
     * Sets the path for a particular class.
     *
     * @param string $class A PHP class name
     * @param string $path  An absolute path
     */
    public function setClassPath($class, $path)
    {
        $class = strtolower($class);

        $this->overriden[$class] = $path;

        $this->classes[$class] = $path;
    }

    /**
     * Returns the path where a particular class can be found.
     *
     * @param string $class A PHP class name
     *
     * @return string|null An absolute path
     */
    public function getClassPath($class)
    {
        $class = strtolower($class);

        return isset($this->classes[$class]) ? $this->classes[$class] : null;
    }

    /**
     * Reloads the autoloader.
     *
     * @param bool $force Whether to force a reload
     *
     * @return bool True if the reload was successful, otherwise false
     */
    public function reloadClasses($force = false)
    {
        // only (re)load the autoloading cache once per request
        if (self::$freshCache && !$force) {
            return false;
        }

        $configuration = sfProjectConfiguration::getActive();
        if (!$configuration || !$configuration instanceof sfApplicationConfiguration) {
            return false;
        }

        self::$freshCache = true;
        if (is_file($configuration->getConfigCache()->getCacheName('config/autoload.yml'))) {
            self::$freshCache = false;
            if ($force) {
                if (file_exists($configuration->getConfigCache()->getCacheName('config/autoload.yml'))) {
                    unlink($configuration->getConfigCache()->getCacheName('config/autoload.yml'));
                }
            }
        }

        $file = $configuration->getConfigCache()->checkConfig('config/autoload.yml');

        if ($force && defined('HHVM_VERSION')) {
            // workaround for https://github.com/facebook/hhvm/issues/1447
            $this->classes = eval(str_replace('<?php', '', file_get_contents($file)));
        } else {
            $this->classes = include $file;
        }

        foreach ($this->overriden as $class => $path) {
            $this->classes[$class] = $path;
        }

        return true;
    }

    /**
     * Handles autoloading of classes that have been specified in autoload.yml.
     *
     * @param string $class a class name
     *
     * @return bool Returns true if the class has been loaded
     */
    public function autoload($class)
    {
        // this allow to reload classes when the application has been switched
        $force = false;
        $app = sfConfig::get('sf_app');
        $autoload_stamp = sfConfig::get('sf_cache_dir').'/autoload.tmp';
        if (!($reload = !$this->classes)) {
            if ($this->app != $app) {
                if (!isset($this->caches[$app])) {
                    $this->app = $app;
                    $reload = true;
                    $stamps = [];
                    if (file_exists($autoload_stamp)) {
                        $stamps = unserialize(file_get_contents($autoload_stamp));
                        $force = !in_array($app, $stamps);
                    } else {
                        $force = true;
                    }
                    if (!in_array($app, $stamps)) {
                        $stamps[] = $app;
                        file_put_contents($autoload_stamp, serialize($stamps));
                    }
                } else {
                    $this->classes = $this->caches[$app];
                }
            }
        }
        // load the list of autoload classes
        if ($reload) {
            $this->reloadClasses($force);
            $this->caches[$app] = $this->classes;
        }

        return $this->loadClass($class);
    }

    /**
     * Tries to load a class that has been specified in autoload.yml.
     *
     * @param string $class a class name
     *
     * @return bool Returns true if the class has been loaded
     */
    public function loadClass($class)
    {
        $class = strtolower($class);

        // class already exists
        if (class_exists($class, false) || interface_exists($class, false) || (function_exists('trait_exists') && trait_exists($class, false))) {
            return true;
        }

        // we have a class path, let's include it
        if (isset($this->classes[$class])) {
            try {
                require $this->classes[$class];
            } catch (sfException $e) {
                $e->printStackTrace();
            } catch (Exception $e) {
                sfException::createFromException($e)->printStackTrace();
            }

            return true;
        }

        // see if the file exists in the current module lib directory
        if (
            sfContext::hasInstance()
            && ($module = sfContext::getInstance()->getModuleName())
            && isset($this->classes[$module.'/'.$class])
        ) {
            try {
                require $this->classes[$module.'/'.$class];
            } catch (sfException $e) {
                $e->printStackTrace();
            } catch (Exception $e) {
                sfException::createFromException($e)->printStackTrace();
            }

            return true;
        }

        return false;
    }
}
