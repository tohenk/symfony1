<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class sfContext
{
    public $configuration;
    public $request;
    public $response;
    public $controller;
    public $routing;
    public $user;
    public $storage;
    public $dispatcher;
    protected static $instance;

    protected $sessionPath = '';

    public function __destruct()
    {
        sfToolkit::clearDirectory($this->sessionPath);
    }

    public static function getInstance($factories = [], $force = false)
    {
        if (!isset(self::$instance) || $force) {
            self::$instance = new sfContext();

            self::$instance->sessionPath = sys_get_temp_dir().'/sessions_'.rand(11111, 99999);
            self::$instance->storage = new sfSessionTestStorage(['session_path' => self::$instance->sessionPath]);

            self::$instance->dispatcher = new sfEventDispatcher();

            foreach ($factories as $type => $class) {
                self::$instance->inject($type, $class);
            }
        }

        return self::$instance;
    }

    public static function hasInstance()
    {
        return true;
    }

    public function getEventDispatcher()
    {
        return self::$instance->dispatcher;
    }

    public function getModuleName()
    {
        return 'module';
    }

    public function getActionName()
    {
        return 'action';
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getRouting()
    {
        return $this->routing;
    }

    public function getStorage()
    {
        return $this->storage;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getController()
    {
        return $this->controller;
    }

    public function inject($type, $class, $parameters = [])
    {
        switch ($type) {
            case 'routing':
                $object = new $class($this->dispatcher, null, $parameters);

                break;

            case 'response':
                $object = new $class($this->dispatcher, $parameters);

                break;

            case 'request':
                $object = new $class($this->dispatcher, $this->routing, $parameters);

                break;

            default:
                $object = new $class($this, $parameters);
        }

        $this->{$type} = $object;
    }
}
