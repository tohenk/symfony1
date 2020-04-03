<?php

/**
 * Model generator helper.
 *
 * @package    symfony
 * @subpackage generator
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
abstract class sfModelGeneratorHelper
{
  abstract public function getUrlForAction($action);

  public function formatLabel($params = array(), $catalogue = 'sf_admin')
  {
    return __($params['label'], array(), $catalogue);
  }

  public function generateActionClass($action)
  {
    return sprintf('sf_admin_action_%s', $action);
  }

  public function linkToNew($params, $attributes = array())
  {
    return sprintf('<li class="%s">', $this->generateActionClass('new')).link_to($this->formatLabel($params), '@'.$this->getUrlForAction('new'), $attributes).'</li>';
  }

  public function linkToEdit($object, $params, $attributes = array())
  {
    return sprintf('<li class="%s">', $this->generateActionClass('edit')).link_to($this->formatLabel($params), $this->getUrlForAction('edit'), $object, $attributes).'</li>';
  }

  /**
   * @param mixed $object
   * @param array $params
   * @return string
   */
  public function linkToDelete($object, $params, $attributes = array())
  {
    if ($object->isNew())
    {
      return '';
    }

    return sprintf('<li class="%s">', $this->generateActionClass('delete')).link_to($this->formatLabel($params), $this->getUrlForAction('delete'), $object, array_merge(array('method' => 'delete', 'confirm' => !empty($params['confirm']) ? __($params['confirm'], array(), 'sf_admin') : $params['confirm']), $attributes)).'</li>';
  }

  public function linkToList($params, $attributes = array())
  {
    return sprintf('<li class="%s">', $this->generateActionClass('list')).link_to($this->formatLabel($params), '@'.$this->getUrlForAction('list'), $attributes).'</li>';
  }

  public function linkToSave($object, $params, $attributes = array())
  {
    return sprintf('<li class="%s">', $this->generateActionClass('save')).content_tag('button', $this->formatLabel($params), array_merge(array('type' => 'submit'), $attributes)).'</li>';
  }

  /**
   * @param mixed $object
   * @param array $params
   * @return string
   */
  public function linkToSaveAndAdd($object, $params, $attributes = array())
  {
    if (!$object->isNew())
    {
      return '';
    }

    return sprintf('<li class="%s">', $this->generateActionClass('save_and_add')).content_tag('button', $this->formatLabel($params), array_merge(array('type' => 'submit'), $attributes)).'</li>';
  }
}
