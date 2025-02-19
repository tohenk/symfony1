<?php

require_once ##SYMFONY_CORE_AUTOLOAD##;
sfCoreAutoload::register();

$symfonyDir = sfCoreAutoload::getInstance()->getBaseDir();
foreach ([
    __DIR__.'/../vendor/autoload.php',           // project
    $symfonyDir.'/../vendor/autoload.php',       // symfony
    $symfonyDir.'/../../../vendor/autoload.php', // composer
] as $autoload) {
    if (is_readable($autoload)) {
        /** @var \Composer\Autoload\ClassLoader $composer */
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
