<?php

namespace App\CoreBundle\Provider;

use App\Model\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * App\CoreBundle\Provider\OAuthProviderInterface
 */
interface OAuthProviderInterface
{
    /**
     * Returns a CSRF provider
     *
     * @return \Symfony\Component\Form\CsrfProviderInterface
     */
    public function getCsrfProvider();

   /**
     * Returns the provider's OAuth client id
     *
     * @return null|string
     */
    public function getOAuthClientId();

    /**
     * Returns the provider's OAuth Client Secret
     * @return null|string
     */
    public function getOAuthClientSecret();

    /**
     * Returns the provider's OAuth authorize URL
     *
     * @return null|string
     */
    public function getAuthorizeUrl();

    /**
     * Handles OAuth callback for this provider
     *
     * @param Request $request
     * @param User    $user
     *
     * @todo passing $user should not be allowed
     */
    public function handleOAuthCallback(Request $request, User $user = null);
}
