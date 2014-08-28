<?php

namespace App\CoreBundle\Provider\BitBucket;

use App\Model\Build;
use App\Model\Project;
use App\Model\User;
use App\CoreBundle\Provider\AbstractProvider;
use App\CoreBundle\Provider\OAuthProviderTrait;
use App\CoreBundle\Provider\OAuthProviderInterface;
use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;
use Psr\Log\LoggerInterface;
use League\OAuth1\Client\Server\Bitbucket as OAuthClient;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Provider extends AbstractProvider
{
    /**
     * @var string
     */
    protected $baseUrl = 'https://bitbucket.org';

    /**
     * @var string
     */
    protected $baseApiUrl = 'https://bitbucket.org/api/2.0';

    /**
     * @var string
     */
    protected $accessTokenUrl = '';

    /**
     * @var string
     */
    private $oauthKey;

    /**
     * @var string
     */
    private $oauthSecret;

    /**
     * @var OAuthClient
     */
    private $oauthClient;

    /**
     * @var Client
     */
    private $client;

    /**
     * @param LoggerInterface       $logger
     * @param UrlGeneratorInterface $router
     * @param CsrfProviderInterface $csrfProvider
     * @param Client                $client
     */
    public function __construct(LoggerInterface $logger, UrlGeneratorInterface $router, SessionInterface $session, Client $client, $oauthKey, $oauthSecret)
    {
        $this->oauthKey = $oauthKey;
        $this->oauthSecret = $oauthSecret;
        $this->session = $session;
        $this->client = $client;

        $this->oauthClient = new OAuthClient([
            'identifier' => $oauthKey,
            'secret' => $oauthSecret,
            'callback_uri' => $router->generate('app_core_oauth_callback', ['providerName' => $this->getName()], true),
        ]);

        parent::__construct($logger, $router);
    }

    /**
     * @param Request $request
     * @param User $user
     * 
     * @return array
     */
    public function handleOAuthCallback(Request $request, User $user = null)
    {
        $oauthVerifier = $request->get('oauth_verifier');
        $oauthToken = $request->get('oauth_token');

        $temporaryCredentials = $this->session->get('provider/'.$this->getName().'/temporary_credentials');

        $tokenCredentials = $this->oauthClient->getTokenCredentials($temporaryCredentials, $oauthToken, $oauthVerifier);
        $userDetails = $this->oauthClient->getUserDetails($tokenCredentials);

        $accessToken = [
            'identifier' => $tokenCredentials->getIdentifier(),
            'secret' => $tokenCredentials->getSecret(),
            'username' => $userDetails->nickname,
        ];

        if (null !== $user) {
            $user->setProviderAccessToken($this->getName(), $accessToken);
        }

        return [
            'access_token' => $accessToken,
            'scope' => null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function translateScope($scope)
    {
        return $scope;
    }

    /**
     * {@inheritDoc}
     */
    public function reverseTranslateScope($scope)
    {
        return $scope;
    }

    /**
     * {@inheritDoc}
     */
    public function hasScope(User $user, $scope)
    {
        return $user->hasProviderAccessToken($this->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function getImporter()
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'bitbucket';
    }

    /**
     * {@inheritDoc}
     */
    public function getDisplayName()
    {
        return 'BitBucket';
    }

    /**
     * {@inheritDoc}
     */
    public function getUserData($accessToken)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderUserId($accessToken)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function createUser($accessToken)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function refreshScopes(User $user)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function requireScope($scope = null)
    {
        $temporaryCredentials = $this->oauthClient->getTemporaryCredentials();
        $authorizeUrl = $this->oauthClient->getAuthorizationUrl($temporaryCredentials);

        $this->session->set('provider/'.$this->getName().'/temporary_credentials', $temporaryCredentials);

        return new RedirectResponse($authorizeUrl);
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexedRepositories(User $user)
    {
        return $this->getRepositories($user);
    }

    /**
     * {@inheritDoc}
     */
    public function getRepositories(User $user)
    {
        $client = $this->configureClientForUser($user);

        $accessToken = $this->getAccessToken($user);

        var_dump((string) htmlentities($client->get()->send()->getBody()));
        die('lol');
    }

    /**
     * {@inheritDoc}
     */
    public function createPayloadFromRequest(Request $request)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function createPullRequestFromPayload(Project $project, $payload)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function getPullRequestHead(Build $build, $separator = ':')
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function configureClientForAccessToken($accessToken)
    {
        $client = clone $this->client;
        $client->addSubscriber(new OauthPlugin([
            'consumer_key' => $this->oauthKey,
            'consumer_secret' => $this->oauthSecret,
            'token' => $accessToken['identifier'],
            'token_secret' => $accessToken['secret'],
        ]));

        return $client;
    }

    /**
     * {@inheritDoc}
     */
    public function getCollaborators(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function setCommitStatus(Project $project, Build $build, $status)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function sendPullRequestComment(Project $project, Build $build)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function getBranches(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function pruneDeployKeys(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function installDeployKey(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function clearHooks(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function installHooks(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function triggerWebHook(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function getHashFromRef(Project $project, $ref)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function countDeployKeys(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function countPushHooks(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }

    /**
     * {@inheritDoc}
     */
    public function countPullRequestHooks(Project $project)
    {
        throw new \Exception(sprintf('Not implemented: %s::%s', __CLASS__, __FUNCTION__));
    }
}