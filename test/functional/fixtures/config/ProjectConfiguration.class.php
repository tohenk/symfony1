<?php

require_once __DIR__.'/../../../../lib/autoload/sfCoreAutoload.class.php';
sfCoreAutoload::register();

$symfonyDir = sfCoreAutoload::getInstance()->getBaseDir();
$autoloads = [
    $symfonyDir.'/../vendor/autoload.php',       // symfony
    $symfonyDir.'/../../../vendor/autoload.php', // composer
];
foreach ($autoloads as $autoload) {
    if (is_readable($autoload)) {
        /** @var Composer\Autoload\ClassLoader $composer */
        $composer = require_once $autoload;

        break;
    }
}

class ProjectConfiguration extends sfProjectConfiguration
{
    public function setup()
    {
        $this->enablePlugins('sfAutoloadPlugin', 'sfConfigPlugin');
    }
}
