<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Finder\Finder;

$_test_dir = realpath(__DIR__.'/..');

// configuration
require_once __DIR__.'/../../config/ProjectConfiguration.class.php';
$configuration = ProjectConfiguration::hasActive() ? ProjectConfiguration::getActive() : new ProjectConfiguration(realpath($_test_dir.'/..'));

// autoloader
$autoload = sfSimpleAutoload::getInstance(sfConfig::get('sf_cache_dir').'/project_autoload.cache');
$autoload->loadConfiguration([...Finder::create()->files()->name('autoload.yml')->in([
    sfConfig::get('sf_symfony_lib_dir').'/config/config',
    sfConfig::get('sf_config_dir'),
])]);
$autoload->register();

// lime
include $configuration->getSymfonyLibDir().'/vendor/lime/lime.php';
