<?php

namespace App\CoreBundle\Provider;

use App\Model\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * App\CoreBundle\Provider\OAuthProviderTrait
 */
trait OAuthProviderTrait
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $authorizeUrl;

    /**
     * @var string
     */
    private $oauthClientId;

    /**
     * @var string
     */
    private $oauthClientSecret;

    /**
     * @var \Symfony\Component\Form\CsrfProvicerInterface
     */
    private $csrfProvider;

    /**
     * @return \Symfony\Component\Form\CsrfProviderInterface
     */
    public function getCsrfProvider()
    {
        return $this->csrfProvider;
    }

    /**
     * @return string
     */
    public function getOAuthClientId()
    {
        return $this->oauthClientId;
    }

    /**
     * @return string
     */
    public function getOAuthClientSecret()
    {
        return $this->oauthClientSecret;
    }

    /**
     * @return string
     */
    public function getAuthorizeUrl()
    {
        return $this->baseUrl.$this->authorizeUrl;
    }

    /**
     * @param Request $request
     * @param User    $user
     * 
     * @todo passing $user should not be allowed
     */
    public function handleOAuthCallback(Request $request, User $user = null)
    {
        $code = $request->get('code');
        $token = $request->get('state');

        if (!$this->csrfProvider->isCsrfTokenValid($this->getName(), $token)) {
            throw new ProviderException('CSRF Mismatch');
        }

        $payload = [
            'client_id' => $this->getOAuthClientId(),
            'client_secret' => $this->getOAuthClientSecret(),
            'code' => $code,
        ];

        $client = clone $this->client;
        $client->setDefaultOption('headers/Accept', 'application/json');

        $request = $client->post($this->getAccessTokenUrl());
        $request->setBody(http_build_query($payload));

        $response = $request->send();
        $data = $response->json();

        if (array_key_exists('error', $data)) {
            $this->logger->error('An error occurred during authentication', ['data' => $data]);

            throw new ProviderException(sprintf('%s: %s', $data['error'], $data['error_description']));
        }

        if (null !== $user) {
            $user->setProviderAccessToken($this->getName(), $data['access_token']);
            $user->setProviderScopes($this->getName(), explode(',', $data['scope']));            
        }

        return [
            'access_token' => $data['access_token'],
            'scope' => $data['scope'],
        ];
    }
}