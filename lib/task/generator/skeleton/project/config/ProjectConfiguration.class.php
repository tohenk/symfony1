<?php

require_once ##SYMFONY_CORE_AUTOLOAD##;
sfCoreAutoload::register();

$symfonyDir = sfCoreAutoload::getInstance()->getBaseDir();
$autoloads = [
    __DIR__.'/../vendor/autoload.php',           // project
    $symfonyDir.'/../vendor/autoload.php',       // symfony
    $symfonyDir.'/../../../vendor/autoload.php', // composer
];
foreach ($autoloads as $autoload) {
    if (is_readable($autoload)) {
        /** @var $composer \Composer\Autoload\ClassLoader */
        $composer = require_once $autoload;

        break;
    }
}

class ProjectConfiguration extends sfProjectConfiguration
{
    public function setup()
    {
        // enable plugins here
    }
}
