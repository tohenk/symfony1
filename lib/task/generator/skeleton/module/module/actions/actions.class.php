<?php

/**
 * ##MODULE_NAME## actions.
 *
 * @author ##AUTHOR_NAME##
 */
class ##MODULE_NAME##Actions extends sfActions
{
    /**
     * Executes index action
     *
     * @param sfRequest $request A request object
     */
    public function executeIndex(sfWebRequest $request)
    {
        $this->forward('default', 'module');
    }
}
