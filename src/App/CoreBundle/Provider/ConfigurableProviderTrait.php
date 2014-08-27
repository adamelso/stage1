<?php

namespace App\CoreBundle\Provider;

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * App\CoreBundle\Provider\ConfigurableProviderTrait
 */
trait ConfigurableProviderTrait
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @return string
     */
    abstract public function getConfigFormType();

    /**
     * @param Request   $request
     * @param Form      $form
     * 
     * @return array|boolean
     */
    public function handleConfigForm(Request $request, Form $form)
    {
        if ($request->isMethod('post')) {
            $form->handleRequest($request);

            if ($form->isValid()) {
                return $form->getData();
            }
        }

        return false;
    }

    /**
     * @param array $config
     * 
     * @return ProviderInterface
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}