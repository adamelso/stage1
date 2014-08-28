<?php

namespace App\CoreBundle\Provider;

use App\CoreBundle\Value\ProjectAccess;
use App\CoreBundle\SshKeysGenerator;
use App\Model\Project;
use App\Model\Branch;
use App\Model\ProjectSettings;
use App\Model\User;
use Closure;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * App\CoreBundle\Provider\AbstractImporter
 */
abstract class AbstractImporter implements ImporterInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var RegistryInterface
     */
    protected $doctrine;

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * @var SshKeysGenerator 
     */
    protected $sshKeysGenerator;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var ProviderInterface
     */
    protected $provider;

    /**
     * @var string|array
     */
    protected $accessToken;

    /**
     * @var ProjectAccess
     */
    protected $initialProjectAccess;

    /**
     * @var boolean
     */
    protected $feature_ip_access_list = false;

    /**
     * @var boolean
     */
    protected $feature_token_access_list = true;

    /**
     * @param LoggerInterface $logger
     * @param RegistryInterface $doctrine
     * @param Redis $redis
     * @param UrlGeneratorInterface $router
     * @param SshKeysGenerator $sshKeysGenerator
     */
    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Redis $redis, UrlGeneratorInterface $router, SshKeysGenerator $sshKeysGenerator)
    {
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->redis = $redis;
        $this->router = $router;
        $this->sshKeysGenerator = $sshKeysGenerator;
    }

    /**
     * @param LoggerInterface $logger
     * 
     * @return ImporterInterface
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }


    /**
     * @param ProviderInterface $provider
     * 
     * @return ImporterInterface
     */
    public function setProvider(ProviderInterface $provider)
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @return ProviderInterface
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Default steps list
     * 
     * Note that most steps are already implemented in this AbstractImporter
     * 
     * {@inheritDoc}
     */
    public function getSteps()
    {
        return [
            [
                'id' => 'inspect',
                'label' => 'Inspecting project',
                'callback' => 'doInspect',
                'abort_on_error' => true,
            ],
            [
                'id' => 'keys',
                'label' => 'Generating keys',
                'callback' => 'doKeys',
                'abort_on_error' => false,
            ],
            [
                'id' => 'deploy_key',
                'label' => 'Adding deploy key',
                'callback' => 'doDeployKey',
                'abort_on_error' => false,
            ],
            [
                'id' => 'webhook',
                'label' => 'Configuring webhook',
                'callback' => 'doWebhook',
                'abort_on_error' => false,
            ],
            [
                'id' => 'branches',
                'label' => 'Importing branches',
                'callback' => 'doBranches',
                'abort_on_error' => false,
            ],
            [
                'id' => 'pull_requests',
                'label' => 'Importing pull requests',
                'callback' => 'doPullRequests',
                'abort_on_error' => false,
            ],
            [
                'id' => 'access',
                'label' => 'Granting default access',
                'callback' => 'doAccess',
                'abort_on_error' => false,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function setInitialProjectAccess(ProjectAccess $initialProjectAccess)
    {
        $this->initialProjectAccess = $initialProjectAccess;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setFeatureIpAccessList($bool)
    {
        $this->feature_ip_access_list = (bool) $bool;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setFeatureTokenAccessList($bool)
    {
        $this->feature_token_access_list = (bool) $bool;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(User $user)
    {
        $this->user = $user;

        $providerName = $this->getProvider()->getName();

        if (strlen($user->hasProviderAccessToken($providerName))) {
            $this->setAccessToken($user->getProviderAccessToken($providerName));
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function import($fullName, Closure $callback = null)
    {
        if (null === $this->provider) {
            throw new RuntimeException('No provider set');
        }

        if (null === $this->user) {
            throw new RuntimeException('No user set');
        }

        $logger = $this->logger;
        $logger->info('importing project', ['full_name' => $fullName]);

        $project = new Project();
        $project->setFullName($fullName);
        $project->addUser($this->getUser());

        if (null === $callback) {
            $callback = function() {};
        }

        $announce = function($step) use ($callback, $logger) {
            $logger->debug('running import step', $step);
            $callback($step);
        };

        foreach ($this->getSteps() as $step) {
            $announce($step);

            if (!method_exists($this, $step['callback'])) {
                $logger->error('could not run step: method does not exist', $step);

                throw new RuntimeException(sprintf('Step method does not exist (step id: %s, method: %s', $step['id'], $step['callback']));
            }

            $ret = call_user_func([$this, $step['callback']], $project);

            if ($ret === false && $step['abort_on_error']) {
                $logger->error('aborting import on step error', $step);

                return false;
            }
        }

        # set default build policy
        $settings = new ProjectSettings();
        $settings->setProject($project);
        $settings->setPolicy(ProjectSettings::POLICY_ALL);

        $em = $this->doctrine->getManager();

        $em->persist($project);
        $em->flush();

        $em->persist($settings);
        $em->flush();

        return $project;
    }

    /**
     * @param Project $project
     * 
     * @return boolean
     */
    protected function doDeployKey(Project $project, $prune = false)
    {
        if (!$project->getIsPrivate()) {
            return;
        }

        $provider = $this->getProvider();

        if (!$provider->hasDeployKey($project)) {
            $provider->installDeployKey($project);
        }


        if ($prune) {
            $provider->pruneDeployKeys($project);
        }

        return true;
    }

    /**
     * @param Project $project
     * 
     * @return boolean
     */
    protected function doWebhook(Project $project)
    {
        try {
            $this->provider->installHooks($project);
        } catch (InsufficientScopeException $e) {
            return false;
        }
    }

    /**
     * @param Project $project
     * 
     * @return boolean
     */
    protected function doKeys(Project $project)
    {
        $keys = $this->sshKeysGenerator->generate();

        $project->setPublicKey($keys['public']);
        $project->setPrivateKey($keys['private']);

        return true;
    }

    /**
     * @param Project $project
     * 
     * @return boolean
     */
    protected function doBranches(Project $project)
    {
        foreach ($this->provider->getBranches($project) as $name) {
            $branch = new Branch();
            $branch->setName($name);

            $branch->setProject($project);
            $project->addBranch($branch);
        }

        return true;
    }

    /**
     * @param Project $project
     * 
     * @return boolean
     */
    protected function doAccess(Project $project)
    {
        if (null === $this->initialProjectAccess) {
            # no initial project access means the project is public
            return true;
        }

        if (!$project->getIsPrivate()) {
            # public projects don't have access management
            return true;
        }

        # this is one special ip that cannot be revoked
        # it is used to keep the access list "existing"
        # thus activating auth on the staging areas
        # yes, it's a bit hacky.
        $this->grantProjectAccess($project, new ProjectAccess('0.0.0.0'));

        # this, however, is perfectly legit.
        $this->grantProjectAccess($project, $this->initialProjectAccess);

        return true;
    }

    /**
     * @todo @project_access refactor
     * 
     * @param Project $project
     * @param ProjectAccess $access
     * 
     * @return boolean
     */
    protected function grantProjectAccess(Project $project, ProjectAccess $access)
    {
        $args = ['auth:'.$project->getSlug()];

        if ($this->feature_ip_access_list || $access->getIp() === '0.0.0.0') {
            $args[] = $access->getIp();
        }

        if ($this->feature_token_access_list) {
            $args[] = $access->getToken();
        }

        $args = array_filter($args, function($arg) { return strlen($arg) > 0; });

        return (bool) call_user_func_array([$this->redis, 'sadd'], $args);
    }

    /**
     * @param string    $route
     * @param array     $parameters
     * @param boolean   $referenceType
     * 
     * @return string
     */
    protected function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }
}