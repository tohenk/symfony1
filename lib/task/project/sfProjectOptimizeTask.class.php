<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Finder\Finder;

/**
 * Optimizes a project for better performance.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class sfProjectOptimizeTask extends sfBaseTask
{
    /**
     * @see sfTask
     */
    protected function configure()
    {
        $this->addArguments([
            new sfCommandArgument('application', sfCommandArgument::REQUIRED, 'The application name'),
            new sfCommandArgument('env', sfCommandArgument::OPTIONAL, 'The environment name', 'prod'),
        ]);

        $this->namespace = 'project';
        $this->name = 'optimize';
        $this->briefDescription = 'Optimizes a project for better performance';

        $this->detailedDescription = <<<'EOF'
The [project:optimize|INFO] optimizes a project for better performance:

  [./symfony project:optimize frontend prod|INFO]

This task should only be used on a production server. Don't forget to re-run
the task each time the project changes.
EOF;
    }

    /**
     * @see sfTask
     */
    protected function execute($arguments = [], $options = [])
    {
        $data = [];
        $modules = $this->findModules();
        $target = sfConfig::get('sf_cache_dir').'/'.$arguments['application'].'/'.$arguments['env'].'/config/configuration.php';

        $current_umask = umask();
        umask(0000);

        // remove existing optimization file
        if (file_exists($target)) {
            $this->getFilesystem()->remove($target);
        }

        // recreate configuration without the cache
        $this->setConfiguration($this->createConfiguration($arguments['application'], $arguments['env']));

        // initialize the context
        sfContext::createInstance($this->configuration);

        // force cache generation for generated modules
        foreach ($modules as $module) {
            $this->logSection('module', $module);

            try {
                $this->configuration->getConfigCache()->checkConfig('modules/'.$module.'/config/generator.yml', true);
            } catch (Exception $e) {
                $this->dispatcher->notifyUntil(new sfEvent($e, 'application.throw_exception'));

                $this->logSection($module, $e->getMessage(), null, 'ERROR');
            }
        }

        $templates = $this->findTemplates($modules);

        $data['getTemplateDir'] = $this->optimizeGetTemplateDir($modules, $templates);
        $data['getControllerDirs'] = $this->optimizeGetControllerDirs($modules);
        $data['getPluginPaths'] = $this->configuration->getPluginPaths();
        $data['loadHelpers'] = $this->optimizeLoadHelpers($modules);

        if (!file_exists($directory = dirname($target))) {
            $this->getFilesystem()->mkdirs($directory);
        }

        $this->logSection('file+', $target);
        file_put_contents($target, '<?php return '.var_export($data, true).';');

        umask($current_umask);

        return 0;
    }

    protected function optimizeGetControllerDirs($modules)
    {
        $data = [];
        foreach ($modules as $module) {
            $data[$module] = $this->configuration->getControllerDirs($module);
        }

        return $data;
    }

    protected function optimizeGetTemplateDir($modules, $templates)
    {
        $data = [];
        foreach ($modules as $module) {
            $data[$module] = [];
            foreach ($templates[$module] as $template) {
                if (null !== $dir = $this->configuration->getTemplateDir($module, $template)) {
                    $data[$module][$template] = $dir;
                }
            }
        }

        return $data;
    }

    protected function optimizeLoadHelpers($modules)
    {
        $data = [];

        $finder = Finder::create()->files()->name('*Helper.php');

        // module helpers
        foreach ($modules as $module) {
            $helpers = [];

            $dirs = $this->configuration->getHelperDirs($module);
            foreach ($finder->in($dirs[0]) as $file) {
                $helpers[basename($file, 'Helper.php')] = $file;
            }

            if (count($helpers)) {
                $data[$module] = $helpers;
            }
        }

        // all other helpers
        foreach ($this->configuration->getHelperDirs() as $dir) {
            foreach ($finder->in($dir) as $file) {
                $helper = basename($file, 'Helper.php');
                if (!isset($data[''][$helper])) {
                    $data[''][$helper] = $file;
                }
            }
        }

        return $data;
    }

    protected function findTemplates($modules)
    {
        $files = [];

        foreach ($modules as $module) {
            $files[$module] = array_map(fn ($f) => $f->getRelativePathname(),
                [...Finder::create()->files()->followLinks()->in($this->configuration->getTemplateDirs($module))]);
        }

        return $files;
    }

    protected function findModules()
    {
        // application
        $dirs = [sfConfig::get('sf_app_module_dir')];

        // plugins
        $pluginSubPaths = $this->configuration->getPluginSubPaths(DIRECTORY_SEPARATOR.'modules');
        $modules = [];
        foreach (Finder::create()->directories()->depth(0)->followLinks()->in($pluginSubPaths) as $module) {
            if (in_array($module->getRelativePathname(), sfConfig::get('sf_enabled_modules'))) {
                $modules[] = $module->getRelativePathname();
            }
        }

        // core modules
        $dirs[] = sfConfig::get('sf_symfony_lib_dir').'/controller';

        return array_unique(array_merge(array_map(fn ($f) => $f->getRelativePathname(),
            [...Finder::create()->directories()->depth(0)->followLinks()->in($dirs)]), $modules));
    }
}
