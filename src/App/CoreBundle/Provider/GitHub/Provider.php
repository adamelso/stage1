<?php

namespace App\CoreBundle\Provider\GitHub;

use App\CoreBundle\Provider\PayloadInterface;
use App\CoreBundle\Provider\ProviderInterface;
use App\CoreBundle\Provider\OAuthProviderInterface;
use App\CoreBundle\Provider\Exception as ProviderException;
use App\CoreBundle\Provider\Scope;
use App\Model\Build;
use App\Model\Project;
use App\Model\PullRequest;
use App\Model\User;
use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use InvalidArgumentException;

/**
 * App\CoreBundle\Provider\GitHub\Provider
 */
class Provider implements ProviderInterface, OAuthProviderInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CsrfProviderInterface
     */
    private $csrfProvider;

    /**
     * @var string
     */
    private $authorizeUrl = '/login/oauth/authorize';

    /**
     * @var string
     */
    private $accessTokenUrl = '/login/oauth/access_token';

    /**
     * @var string
     */
    private $baseUrl = 'https://github.com';

    /**
     * @var string
     */
    private $baseApiUrl = 'https://api.github.com';

    /**
     * @var string
     */
    private $oauthClientId;

    /**
     * @var string
     */
    private $oauthClientSecret;

    /**
     * @var array
     */
    private $apiCache = [];

    /**
     * @var array
     */
    private $config = [];

    /**
     * @var string[]
     */
    private $scopeMap = [
        Scope::SCOPE_PRIVATE => 'repo',
        Scope::SCOPE_PUBLIC => 'public_repo',
        Scope::SCOPE_ACCESS => null
    ];

    /**
     * @param LoggerInterface       $logger
     * @param Client                $client
     * @param UrlGeneratorInterface $router
     * @param CsrfProviderInterface $csrfProvider
     */
    public function __construct(LoggerInterface $logger, Client $client, Discover $discover, Import $import, UrlGeneratorInterface $router, CsrfProviderInterface $csrfProvider, $oauthClientId, $oauthClientSecret)
    {
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        $this->logger = $logger;
        $this->client = $client;
        $this->discover = $discover;
        $this->import = $import;
        $this->router = $router;
        $this->csrfProvider = $csrfProvider;
        $this->oauthClientId = $oauthClientId;
        $this->oauthClientSecret = $oauthClientSecret;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getDisplayName();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'github';
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return 'GitHub';
    }

    /**
     * @return string
     */
    public function getConfigFormType()
    {
        return null;
    }

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
     * @return string
     */
    public function getAccessTokenUrl()
    {
        return $this->baseUrl.$this->accessTokenUrl;
    }

    /**
     * @return string
     */
    public function getAccessToken(Project $project)
    {
        return $project->getUsers()->first()->getProviderAccessToken($this->getName());
    }

    /**
     * Translates a Stage1 scope to the provider scope.
     * 
     * @param string $scope
     * 
     * @return string|null
     */
    public function translateScope($scope)
    {
        if (array_key_exists($scope, $this->scopeMap)) {
            return $this->scopeMap[$scope];
        }

        throw new InvalidArgumentException('Unknown internal scope "'.$scope.'"');
    }

    /**
     * Translates a provider scope to a Stage1 scope
     * @param string $scope
     * 
     * @return string
     */
    public function reverseTranslateScope($scope)
    {
        if (in_array($scope, $this->scopeMap)) {
            return $this->scopeMap[array_search($scope, $this->scopeMap)];
        }

        throw new InvalidArgumentException('Unknown provider scope "'.$scope.'"');
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

    /**
     * @param string $accessToken
     */
    public function getUserData($accessToken)
    {
        if (!isset($this->apiCache[$accessToken])) {
            $this->apiCache[$accessToken] = [];
        }

        if (!isset($this->apiCache[$accessToken]['/user'])) {
            $client = $this->configureClientForAccessToken($accessToken);
            $this->apiCache[$accessToken]['/user'] = $client->get('/user')->send()->json();
        }

        return $this->apiCache[$accessToken]['/user'];
    }

    /**
     * @param string $accessToken
     * 
     * @return string
     */
    public function getProviderUserId($accessToken)
    {
        return $this->getUserData($accessToken)['id'];
    }

    /**
     * @param string $accessToken
     * 
     * @return User
     */
    public function createUser($accessToken)
    {
        $data = $this->getUserData($accessToken);

        $user = new User();
        $user->setLoginProviderUserId($data['id']);
        $user->setLoginProviderName($this->getName());
        $user->setUsername($data['login']);

        if (isset($data['email']) && strlen($data['email']) > 0) {
            $user->setEmail($data['email']);
        }

        return $user;
    }

    /**
     * @param User $user
     */
    public function refreshScopes(User $user)
    {
        $client = $this->configureClientForUser($user);
        $url = sprintf('/applications/%s/tokens/%s', $this->getOAuthClientId(), $user->getProviderAccessToken($this->getName()));

        $data = $client->get($url, [], [
            'auth' => [$this->getOAuthClientId(), $this->getOAuthClientSecret(), 'Basic']
        ])->send()->json();

        $user->setProviderScopes($this->getName(), $data['scopes']);
    }

    /**
     * @param User  $user
     * @param array $scope
     * 
     * @return boolean
     */
    public function hasScope(User $user, $scope)
    {
        $translatedScope = $this->translateScope($scope);

        return (null === $translatedScope)
            ? $user->hasProviderAccessToken($this->getName())
            : $user->hasProviderScope($this->getName(), $translatedScope);
    }

    /**
     * @param string $scope
     * 
     * @return Response
     */
    public function requireScope($scope = null)
    {
        $token = $this->csrfProvider->generateCsrfToken($this->getName());
        $redirectUri = $this->router->generate('app_core_oauth_callback', ['providerName' => $this->getName()], true);

        $payload = [
            'client_id' => $this->getOAuthClientId(),
            'redirect_uri' => $redirectUri,
            'state' => $token
        ];

        if (null !== $scope) {
            $payload['scope'] = $this->translateScope($scope);
        }

        $oauthUrl = $this->getAuthorizeUrl().'?'.http_build_query($payload);

        return new RedirectResponse($oauthUrl);
    }

    /**
     * @return string
     */
    public function requireLogin()
    {
        return $this->requireScope();
    }

    /**
     * {@inheritDoc}
     */
    public function getDiscoverer()
    {
        return $this->discover;
    }

    /**
     * {@inheritDoc}
     */
    public function getImporter()
    {
        return $this->import;
    }

    /**
     * @param User $user
     * 
     * @return array
     */
    public function getIndexedRepositories(User $user)
    {
        $indexedProjects = [];

        foreach ($this->getRepositories($user) as $project) {
            if (!array_key_exists($project['owner_login'], $indexedProjects)) {
                $indexedProjects[$project['owner_login']] = [];
            }

            $indexedProjects[$project['owner_login']][] = $project;
        }

        return $indexedProjects;
    }

    /**
     * @param User $user
     * 
     * @return array
     */
    public function getRepositories(User $user)
    {
        return $this->discover->discover($user);
    }

    /**
     * @param Request $request
     * 
     * @return \App\CoreBundle\Provider\PayloadInterface
     */
    public function createPayloadFromRequest(Request $request)
    {
        return new Payload($request);
    }

    /**
     * @param Project           $project
     * @param PayloadInterface  $payload
     * 
     * @todo implement PayloadInterface#getPullRequestUrl and PayloadInterface#getPullRequestTitle
     * 
     * @return PullRequest
     */
    public function createPullRequestFromPayload(Project $project, $payload)
    {
        $json = json_decode($payload, true);

        $pr = new PullRequest();
        $pr->setNumber($json['pull_request']['number']);
        $pr->setTitle($json['pull_request']['title']);
        $pr->setRef(sprintf('pull/%d/head', $json['pull_request']['number']));
        $pr->setOpen($json['pull_request']['state'] === 'open');
        $pr->setUrl(sprintf('https://github.com/%s/pull/%d', $project->getFullName(), $json['pull_request']['number']));
        $pr->setProject($project);

        return $pr;
    }

    /**
     * @param Build  $build
     * @param string $separator
     * 
     * @return string
     */
    public function getPullRequestHead(Build $build, $separator = ':')
    {
        $project = $build->getProject();

        list($name,) = explode('/', $project->getFullName());

        return $name.$separator.$build->getRef();
    }

    /**
     * @param Project $project
     * 
     * @return Client
     */
    public function configureClientForProject(Project $project)
    {
        if (count($project->getUsers()) === 0) {
            throw new InvalidArgumentException('Project "'.$project->getFullName().'" has no users');
        }

        return $this->configureClientForUser($project->getUsers()->first());
    }

    /**
     * @param User $user
     * 
     * @return Client
     */
    public function configureClientForUser(User $user)
    {
        $accessToken = $user->getProviderAccessToken($this->getName());

        return $this->configureClientForAccessToken($accessToken);
    }

    /**
     * @param string $accessToken
     */
    public function configureClientForAccessToken($accessToken)
    {
        $client = clone $this->client;
        $client->setDefaultOption('headers/Authorization', 'token '.$accessToken);

        return $this->client;        
    }

    /**
     * @param Project $project
     * 
     * @return array
     */
    public function getCollaborators(Project $project)
    {
        $url = sprintf('/repos/%s/collaborators', $project->getFullName());
        $client = $this->configureClientForProject($project);

        return $client->get($url)->send()->json();
    }

    /**
     * @param Project $project
     * @param Build $build
     * @param string $status
     */
    public function setCommitStatus(Project $project, Build $build, $status)
    {
        $client = $this->configureClientForProject($project);
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.she-hulk-preview+json');

        $request = $client->post(['/repos/'.$project->getFullName().'/statuses/{sha}', [
            'sha' => $build->getHash(),
        ]]);

        $request->setBody(json_encode([
            'state' => 'success',
            'target_url' => $build->getUrl(),
            'description' => 'Stage1 instance ready',
            'context' => 'stage1',
        ]));

        $this->logger->info('sending commit status', [
            'build' => $build->getId(),
            'project' => $project->getGithubFullNAme(),
            'sha' => $build->getHash(),
        ]);

        try {
            $request->send();
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $this->logger->error('error sending commit status', [
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'url' => $e->getRequest()->getUrl(),
                'response' => (string) $e->getResponse()->getBody(),
            ]);
        }
    }

    /**
     * @param Project $project
     * @param Build $build
     */
    public function sendPullRequestComment(Project $project, Build $build)
    {
        $client = $this->configureClientForProject($project);

        $request = $client->get(['/repos/'.$project->getFullName().'/pulls{?data*}', [
            'state' => 'open',
            'head' => $this->getPullRequestHead($build, '/')
        ]]);

        $response = $request->send();

        foreach ($response->json() as $pr) {
            $this->logger->info('sending pull request comment', [
                'build' => $build->getId(),
                'project' => $project->getFullName(),
                'pr' => $pr['number'],
                'pr_url' => $pr['html_url']
            ]);

            $commentRequest = $client->post($pr['comments_url']);
            $commentRequest->setBody(json_encode(['body' => 'Stage1 build finished, url: '.$build->getUrl()]));
            $commentRequest->send();
        }
    }

    /**
     * @param Project $project
     * 
     * @return Branch[]
     */
    public function getBranches(Project $project)
    {
        $client = $this->configureClientForProject($project);
        $url = sprintf('/repos/%s/branches', $project->getFullName());

        $branches = [];

        foreach ($client->get($url)->send()->json() as $row) {
            $branches[] = $row['name'];
        }

        return $branches;
    }

    /**
     * @param Project $project
     */
    public function pruneDeployKeys(Project $project)
    {
        $keysUrl = $project->getProviderData('keys_url');
        $projectDeployKey = $project->getPublicKey();

        $client = $this->configureClientForProject($project);
        $keys = $client->get($keysUrl)->send()->json();

        foreach ($keys as $key) {
            if ($key['key'] !== $projectDeployKey) {
                $client->delete([$keys_url, ['key_id' => $key['id']]])->send();
            }
        }
    }

    /**
     * @param Project $project
     */
    public function installDeployKey(Project $project)
    {
        if ($this->hasDeployKey($project)) {
            return;
        }

        $client = $this->configureClientForProject($project);

        $request = $client->post($project->getKeysUrl());
        $request->setBody(json_encode([
            'key' => $project->getPublicKey(),
            'title' => 'stage1.io (added by support@stage1.io)',
        ]), 'application/json');

        $response = $request->send();
        $installedKey = $response->json();

        $project->setProviderData('deploy_key_id', $installedKey['id']);
    }

    /**
     * @param Project $project
     * 
     * @return boolean
     */
    public function hasDeployKey(Project $project)
    {
        return $this->countDeployKeys($project) > 0;
    }

    /**
     * @param Project $project
     */
    public function clearHooks(Project $project)
    {
        $client = $this->configureClientForProject($project);
        $hooksUrl = $project->getProviderData('hooks_url');

        $response = $client->get($hooksUrl)->send();

        foreach ($response->json() as $hook) {
            if ($hook['name'] === 'web' && strpos($hook['config']['url'], 'stage1.io') !== false) {
                $client->delete($hook['url'])->send();
            }
        }
    }

    /**
     * @param Project $project
     */
    public function installHooks(Project $project)
    {   
        $githubHookUrl = $this->router->generate('app_core_hooks_github', [], true);
        $githubHookUrl = str_replace('http://localhost', 'http://stage1.io', $githubHookUrl);

        $hooksUrl = $project->getProviderData('hooks_url');

        $client = $this->configureClientForProject($project);

        $request = $client->post($hooksUrl);
        $request->setBody(json_encode([
            'name' => 'web',
            'active' => true,
            'events' => ['push', 'pull_request'],
            'config' => ['url' => $githubHookUrl, 'content_type' => 'json'],
        ]), 'application/json');

        $response = $request->send();
        $installedHook = $response->json();

        $providerData = $project->setProviderData('hook_id', $installedHook['id']);
    }

    /**
     * @param Project $project
     */
    public function triggerWebHook(Project $project)
    {
        $fullName = $project->getFullName();
        $hookId = $project->getProviderData('hook_id');

        $url = sprintf('/repos/%s/hooks/%s/tests', $fullName, $hookId);

        $client = $this->configureClientForProject($project);
        $client->post($url)->send();
    }

    /**
     * @param Project $project
     * @param string  $ref
     *
     * @return string
     */
    public function getHashFromRef(Project $project, $ref)
    {
        if (substr($ref, 0, 4) !== 'pull') {
            $ref = 'heads/'.$ref;
        }

        $url = sprintf('/repos/%s/git/refs/%s', $project->getFullName(), $ref);
        $client = $this->configureClientForProject($project);

        return $client->get($url)->send()->json()['object']['sha'];
    }

    /**
     * @param Project $project
     * 
     * @return integer
     */
    public function countDeployKeys(Project $project)
    {
        $client = $this->configureClientForProject($project);
        $response = $client->get($project->getProviderData('keys_url'))->send();

        $count = 0;

        foreach ($response->json() as $deployKey) {
            if ($deployKey['key'] === $project->getPublicKey()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param Project $project
     * 
     * @return integer
     */
    public function countPushHooks(Project $project)
    {
        $client = $this->configureClientForProject($project);
        $response = $client->get($project->getProviderData('hooks_url'))->send();

        $count = 0;

        foreach ($response->json() as $hook) {
            if ($hook['name'] === 'web' && strpos($hook['config']['url'], 'stage1.io') !== false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param Project $project
     * 
     * @return integer
     */
    public function countPullRequestHooks(Project $project)
    {
        $client = $this->configureClientForProject($project);
        $response = $client->get($project->getProviderData('hooks_url'))->send();

        $count = 0;

        foreach ($response->json() as $hook) {
            if ($hook['name'] === 'web' && strpos($hook['config']['url'], 'stage1.io') !== false) {
                foreach ($hook['events'] as $event) {
                    if ($event === 'pull_request') {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}