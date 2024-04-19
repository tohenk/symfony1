<?php

/**
 * Model generator helper.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class sfModelGeneratorHelper
{
    protected $actionTemplate = '<li class="%s">%s</li>';

    abstract public function getUrlForAction($action);

    public function formatLabel($params = [], $catalogue = 'sf_admin')
    {
        return __($params['label'], [], $catalogue);
    }

    public function generateActionClass($action)
    {
        return sprintf('sf_admin_action_%s', $action);
    }

    public function generateAction($name, $params, $attributes = [])
    {
        $class = $this->generateActionClass($name);
        $link = link_to($this->formatLabel($params), '@'.$this->getUrlForAction($name), $attributes);

        return sprintf($this->actionTemplate, $class, $link);
    }

    public function generateObjectAction($name, $object, $params, $attributes = [])
    {
        $class = $this->generateActionClass($name);
        $link = link_to($this->formatLabel($params), $this->getUrlForAction($name), $object, $attributes);

        return sprintf($this->actionTemplate, $class, $link);
    }

    public function generateObjectButtonAction($name, $object, $params, $attributes = [])
    {
        $class = $this->generateActionClass($name);
        $button = content_tag('button', $this->formatLabel($params), array_merge(['type' => 'submit'], $attributes));

        return sprintf($this->actionTemplate, $class, $button);
    }

    public function linkToNew($params, $attributes = [])
    {
        return $this->generateAction('new', $params, $attributes);
    }

    public function linkToEdit($object, $params, $attributes = [])
    {
        return $this->generateObjectAction('edit', $object, $params, $attributes);
    }

    /**
     * @param mixed|Persistent $object
     * @param array            $params
     * @param array            $attributes
     *
     * @return string
     */
    public function linkToDelete($object, $params, $attributes = [])
    {
        if ($object->isNew()) {
            return '';
        }
        $attributes = array_merge(['method' => 'delete', 'confirm' => !empty($params['confirm']) ? __($params['confirm'], [], 'sf_admin') : $params['confirm']], $attributes);
        unset($params['confirm']);

        return $this->generateObjectAction('delete', $object, $params, $attributes);
    }

    public function linkToList($params, $attributes = [])
    {
        return $this->generateAction('list', $params, $attributes);
    }

    public function linkToSave($object, $params, $attributes = [])
    {
        return $this->generateObjectButtonAction('save', $object, $params, $attributes);
    }

    /**
     * @param mixed|Persistent $object
     * @param array            $params
     * @param array            $attributes
     *
     * @return string
     */
    public function linkToSaveAndAdd($object, $params, $attributes = [])
    {
        if (!$object->isNew()) {
            return '';
        }

        return $this->generateObjectButtonAction('save_and_add', $object, $params, $attributes);
    }
}
