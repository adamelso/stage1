<?php

namespace App\CoreBundle\Provider\GitHub;

use App\CoreBundle\Provider\AbstractProvider;
use App\CoreBundle\Provider\Exception as ProviderException;
use App\CoreBundle\Provider\InsufficientScopeException;
use App\CoreBundle\Provider\OAuthProviderInterface;
use App\CoreBundle\Provider\PayloadInterface;
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
class Provider extends AbstractProvider implements OAuthProviderInterface
{
    use \App\CoreBundle\Provider\OAuthProviderTrait;

    /**
     * @var string
     */
    protected $baseUrl = 'https://github.com';

    /**
     * @var string
     */
    protected $baseApiUrl = 'https://api.github.com';

    /**
     * @var string
     */
    protected $accessTokenUrl = '/login/oauth/access_token';

    /**
     * @var array
     */
    protected $scopeMap = [
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

        $import->setProvider($this);

        $this->client = $client;
        $this->discover = $discover;
        $this->import = $import;
        $this->csrfProvider = $csrfProvider;

        $this->oauthClientId = $oauthClientId;
        $this->oauthClientSecret = $oauthClientSecret;

        $this->authorizeUrl = '/login/oauth/authorize';

        parent::__construct($logger, $router);
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
     * @param string $scope
     * 
     * @return Response
     */
    public function requireScope($scope = null)
    {
        $token = $this->getCsrfProvider()->generateCsrfToken($this->getName());
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
        $deliveryId = $this->request->headers->get('X-GitHub-Delivery');
        $event = $this->request->headers->get('X-GitHub-Event');

        return new Payload($request->getContent(), $deliveryId, $event);
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
     * {@inheritDoc}
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

        $request = $client->post($project->getProviderData('keys_url'));

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
        $user = $project->getUsers()->first();
        $neededScope = $project->getIsPrivate() ? Scope::SCOPE_PRIVATE : Scope::SCOPE_PUBLIC;

        if (!$this->hasScope($user, $neededScope)) {
            throw new InsufficientScopeException($neededScope, $user->getProviderScopes($this->getName()));
        }

        $githubHookUrl = $this->router->generate('app_core_hooks_provider', [
            'providerName' => $this->getName()
        ], true);

        /** When generating hooks from the VM, we'd rather have it pointing to a real URL */
        $githubHookUrl = str_replace('http://localhost', 'http://stage1.io', $githubHookUrl);

        $hooksUrl = $project->getProviderData('hooks_url');

        $client = $this->configureClientForProject($project);

        $events = [];

        if ($this->countPushHooks($project) === 0) {
            $events[] = 'push';
        }

        if ($this->countPullRequestHooks($project) === 0) {
            $events[] = 'pull_request';
        }

        if (count($events) === 0) {
            return true;
        }

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