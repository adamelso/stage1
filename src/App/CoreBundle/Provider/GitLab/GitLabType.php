<?php

namespace App\CoreBundle\Provider\GitLab;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * App\CoreBundle\Provider\GitLab\GitLabType
 */
class GitLabType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('base_url', 'text', ['label' => 'Base GitLab URL'])
            ->add('username', 'text', ['label' => 'Your GitLab username'])
            ->add('password', 'password', ['label' => 'Your GitLab password']);
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'intention' => 'provider_gitlab'
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'provider_gitlab';
    }
}
