<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) 2004-2006 Sean Kerr <sean@code-box.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Yaml\Yaml;

/**
 * sfBasicSecurityFilter checks security by calling the getCredential() method
 * of the action. Once the credential has been acquired, sfBasicSecurityFilter
 * verifies the user has the same credential by calling the hasCredential()
 * method of SecurityUser.
 *
 * @author     Sean Kerr <sean@code-box.org>
 */
class sfBasicSecurityFilter extends sfFilter
{
    protected function getUnsecuredActions()
    {
        return array_map(fn ($a) => implode('/', array_map(fn ($b) => sfConfig::get($b), $a)), [
            ['sf_login_module', 'sf_login_action'],
            ['sf_secure_module', 'sf_secure_action'],
            ['sf_error_404_module', 'sf_error_404_action'],
        ]);
    }

    /**
     * Executes this filter.
     *
     * @param sfFilterChain $filterChain A sfFilterChain instance
     */
    public function execute($filterChain)
    {
        // disable security on login, secure, and 404 actions
        if (in_array(implode('/', [$this->context->getModuleName(), $this->context->getActionName()]), $this->getUnsecuredActions())) {
            $filterChain->execute();

            return;
        }

        // NOTE: the nice thing about the Action class is that getCredential()
        //       is vague enough to describe any level of security and can be
        //       used to retrieve such data and should never have to be altered
        if (!$this->context->getUser()->isAuthenticated()) {
            if (sfConfig::get('sf_logging_enabled')) {
                $this->context->getEventDispatcher()->notify(new sfEvent($this, 'application.log', [sprintf('Action "%s/%s" requires authentication, forwarding to "%s/%s"', $this->context->getModuleName(), $this->context->getActionName(), sfConfig::get('sf_login_module'), sfConfig::get('sf_login_action'))]));
            }

            // the user is not authenticated
            $this->forwardToLoginAction();
        }

        // the user is authenticated
        $credential = $this->getUserCredential();
        if (null !== $credential && !$this->context->getUser()->hasCredential($credential)) {
            if (sfConfig::get('sf_logging_enabled')) {
                $this->context->getEventDispatcher()->notify(new sfEvent($this, 'application.log', [sprintf('Action "%s/%s" requires credentials "%s", forwarding to "%s/%s"', $this->context->getModuleName(), $this->context->getActionName(), Yaml::dump($credential, 0), sfConfig::get('sf_secure_module'), sfConfig::get('sf_secure_action'))]));
            }

            // the user doesn't have access
            $this->forwardToSecureAction();
        }

        // the user has access, continue
        $filterChain->execute();
    }

    /**
     * Forwards the current request to the secure action.
     *
     * @throws sfStopException
     */
    protected function forwardToSecureAction()
    {
        $this->context->getController()->forward(sfConfig::get('sf_secure_module'), sfConfig::get('sf_secure_action'));

        throw new sfStopException();
    }

    /**
     * Forwards the current request to the login action.
     *
     * @throws sfStopException
     */
    protected function forwardToLoginAction()
    {
        $this->context->getController()->forward(sfConfig::get('sf_login_module'), sfConfig::get('sf_login_action'));

        throw new sfStopException();
    }

    /**
     * Returns the credential required for this action.
     *
     * @return mixed The credential required for this action
     */
    protected function getUserCredential()
    {
        return $this->context->getController()->getActionStack()->getLastEntry()->getActionInstance()->getCredential();
    }
}
