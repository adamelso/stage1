<?php

namespace App\CoreBundle\Provider;

use App\Model\User;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * App\CoreBundle\Provider\ConfigurableProviderInterface
 */
interface ConfigurableProviderInterface
{
    /**
     * Returns an instance of a Form Type for the provider configuration
     * or null if no configuration is needed for this provider
     * 
     * @return null|Symfony\Component\Form\AbstractType
     */
    public function getConfigFormType();

    /**
     * Handles the configuration form submition
     * 
     * Returns an array of configuraiton value, or false if the
     * form needs to be displayed (not submited or errored)
     * 
     * @param Request   $request
     * @param Form      $form
     * 
     * @return array|boolean
     */
    public function handleConfigForm(Request $request, Form $form);

    /**
     * Extracts default configuration from the user
     * 
     * @param User $user
     * 
     * @return array
     */
    public function getDefaultConfig(User $user);

    /**
     * Sets current configuration
     * 
     * @param array $config
     * 
     * @return ProviderInterface
     */
    public function setConfig(array $config);

    /**
     * Returns current configuration
     * 
     * @return array
     */
    public function getConfig();
}