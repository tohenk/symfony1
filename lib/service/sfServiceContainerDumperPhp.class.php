<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use NTLAB\Object\PHP as PHPObj;

/**
 * sfServiceContainerDumperPhp dumps a service container as a PHP class.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class sfServiceContainerDumperPhp extends sfServiceContainerDumper
{
    /**
     * Dumps the service container as a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *
     * @param array $options An array of options
     *
     * @return string A PHP class representing of the service container
     */
    public function dump(array $options = [])
    {
        $options = array_merge([
            'class' => 'ProjectServiceContainer',
            'base_class' => 'sfServiceContainer',
        ], $options);

        return
            $this->startClass($options['class'], $options['base_class']).
            $this->addConstructor().
            $this->addServices().
            $this->addDefaultParametersMethod().
            $this->endClass();
    }

    public function replaceParameter($match)
    {
        return sprintf("'.\$this->getParameter('%s').'", strtolower($match[2]));
    }

    /**
     * @param string              $id
     * @param sfServiceDefinition $definition
     *
     * @return string
     */
    protected function addServiceInclude($id, $definition)
    {
        if (null !== $definition->getFile()) {
            return sprintf("        require_once %s;\n\n", $this->dumpValue(str_replace(DIRECTORY_SEPARATOR, '/', $definition->getFile())));
        }
    }

    /**
     * @param string              $id
     * @param sfServiceDefinition $definition
     *
     * @return string
     */
    protected function addServiceShared($id, $definition)
    {
        if ($definition->isShared()) {
            return <<<EOF
        if (isset(\$this->shared['{$id}'])) {
            return \$this->shared['{$id}'];
        }


EOF;
        }
    }

    /**
     * @param string              $id
     * @param sfServiceDefinition $definition
     *
     * @return string
     */
    protected function addServiceReturn($id, $definition)
    {
        if ($definition->isShared()) {
            return <<<EOF

        return \$this->shared['{$id}'] = \$instance;
    }

EOF;
        }

        return <<<'EOF'

        return $instance;
    }

EOF;
    }

    /**
     * @param string              $id
     * @param sfServiceDefinition $definition
     *
     * @return string
     */
    protected function addServiceInstance($id, $definition)
    {
        $class = $this->dumpValue($definition->getClass());

        $arguments = [];
        foreach ($definition->getArguments() as $value) {
            $arguments[] = $this->dumpValue($value);
        }

        if (null !== $definition->getConstructor()) {
            return sprintf("        \$instance = call_user_func([%s, '%s']%s);\n", $class, $definition->getConstructor(), $arguments ? ', '.implode(', ', $arguments) : '');
        }

        if ($class != "'".$definition->getClass()."'") {
            return sprintf("        \$class = %s;\n        \$instance = new \$class(%s);\n", $class, implode(', ', $arguments));
        }

        return sprintf("        \$instance = new %s(%s);\n", $definition->getClass(), implode(', ', $arguments));
    }

    /**
     * @param string              $id
     * @param sfServiceDefinition $definition
     *
     * @return string
     */
    protected function addServiceMethodCalls($id, $definition)
    {
        $calls = '';
        foreach ($definition->getMethodCalls() as $call) {
            $arguments = [];
            foreach ($call[1] as $value) {
                $arguments[] = $this->dumpValue($value);
            }

            $calls .= sprintf("        \$instance->%s(%s);\n", $call[0], implode(', ', $arguments));
        }

        return $calls;
    }

    /**
     * @param string              $id
     * @param sfServiceDefinition $definition
     *
     * @return string
     */
    protected function addServiceConfigurator($id, $definition)
    {
        if (!$callable = $definition->getConfigurator()) {
            return '';
        }

        if (is_array($callable)) {
            if (is_object($callable[0]) && $callable[0] instanceof sfServiceReference) {
                return sprintf("        %s->%s(\$instance);\n", $this->getServiceCall((string) $callable[0]), $callable[1]);
            }

            return sprintf("        call_user_func([%s, '%s'], \$instance);\n", $this->dumpValue($callable[0]), $callable[1]);
        }

        return sprintf("        %s(\$instance);\n", $callable);
    }

    protected function addService($id, $definition)
    {
        $name = sfServiceContainer::camelize($id);

        $code = <<<EOF

    protected function get{$name}Service()
    {

EOF;

        $code .=
            $this->addServiceInclude($id, $definition).
            $this->addServiceShared($id, $definition).
            $this->addServiceInstance($id, $definition).
            $this->addServiceMethodCalls($id, $definition).
            $this->addServiceConfigurator($id, $definition).
            $this->addServiceReturn($id, $definition);

        return $code;
    }

    protected function addServiceAlias($alias, $id)
    {
        $name = sfServiceContainer::camelize($alias);

        return <<<EOF

    protected function get{$name}Service()
    {
        return {$this->getServiceCall($id)};
    }

EOF;
    }

    protected function addServices()
    {
        $code = '';
        foreach ($this->container->getServiceDefinitions() as $id => $definition) {
            $code .= $this->addService($id, $definition);
        }

        foreach ($this->container->getAliases() as $alias => $id) {
            $code .= $this->addServiceAlias($alias, $id);
        }

        return $code;
    }

    protected function startClass($class, $baseClass)
    {
        return <<<EOF
class {$class} extends {$baseClass}
{
    protected \$shared = [];

EOF;
    }

    protected function addConstructor()
    {
        if (!$this->container->getParameters()) {
            return '';
        }

        return <<<'EOF'

    public function __construct()
    {
        parent::__construct($this->getDefaultParameters());
    }

EOF;
    }

    protected function addDefaultParametersMethod()
    {
        if (!$this->container->getParameters()) {
            return '';
        }

        $parameters = ltrim($this->exportParameters($this->container->getParameters()));

        return <<<EOF

    protected function getDefaultParameters()
    {
        return {$parameters};
    }

EOF;
    }

    protected function exportParameters($parameters, $indent = 2, $sz = 4)
    {
        return PHPObj::create($parameters, [
            'indentation' => str_repeat(' ', $sz),
            'level' => $indent,
            'trailing_delimiter' => true,
            'callback' => function ($value) {
                if ($value instanceof sfServiceReference) {
                    return sprintf("new sfServiceReference('%s')", $value);
                }
                if ($value instanceof sfServiceParameter) {
                    return sprintf("\$this->getParameter('%s')", $value);
                }
            },
        ]);
    }

    protected function endClass()
    {
        return <<<'EOF'
}

EOF;
    }

    protected function dumpValue($value)
    {
        $callback = [$this, 'replaceParameter'];

        return PHPObj::create($value, [
            'inline' => true,
            'callback' => function ($value) {
                if ($value instanceof sfServiceReference) {
                    return $this->getServiceCall((string) $value);
                }
                if ($value instanceof sfServiceParameter) {
                    return sprintf("\$this->getParameter('%s')", strtolower($value));
                }
            },
            'post.process' => function ($value) use ($callback) {
                if (preg_match('/%([^%]+)%/', $value, $match)) {
                    $code = str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $callback, $value));

                    // optimize string
                    return preg_replace(["/''\\./", "/\\.''/", "/\\.''\\./"], ['', '', '.'], $code);
                }
            },
        ]);
    }

    protected function getServiceCall($id)
    {
        if ('service_container' == $id) {
            return '$this';
        }

        return sprintf('$this->getService(\'%s\')', $id);
    }
}
