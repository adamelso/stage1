<?php


namespace App\CoreBundle\Provider;

use App\CoreBundle\Provider\PayloadInterface;
use App\CoreBundle\Provider\ProviderInterface;
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

/**
 * App\CoreBundle\Provider\ProviderInterface
 */
interface ProviderInterface
{
    /**
     * Returns a user-displayable name for the provider
     * 
     * Example: GitHub
     * 
     * @return string
     */
    public function __toString();

    /**
     * Returns the internal name of the provider
     * 
     * Example: github
     * 
     * @return string
     */
    public function getName();

    /**
     * @see ProviderInterface::__toString
     */
    public function getDisplayName();

    /**
     * Returns the provider's OAuth access token URL
     * 
     * @return string
     */
    public function getAccessTokenUrl();

    /**
     * Extracts an access token from a project
     * 
     * @return string
     */
    public function getAccessToken(Project $project);

    /**
     * Translates a Stage1 scope to the provider scope.
     * 
     * @param string $scope
     * 
     * @return string|null
     */
    public function translateScope($scope);

    /**
     * Translates a provider scope to a Stage1 scope
     * 
     * @param string $scope
     * 
     * @return string
     */
    public function reverseTranslateScope($scope);

    /**
     * Fetches user data from an access token
     * 
     * @param string $accessToken
     */
    public function getUserData($accessToken);

    /**
     * Fetches the provider user id from an access token
     * 
     * @param string $accessToken
     * 
     * @return string
     */
    public function getProviderUserId($accessToken);

    /**
     * Creates an user, retrieving data from an access token
     * 
     * @param string $accessToken
     * 
     * @return User
     */
    public function createUser($accessToken);

    /**
     * Refreshes OAuth scopes for a user
     * 
     * @param User $user
     */
    public function refreshScopes(User $user);

    /**
     * Checks if a user has a specific scope for this provider
     * 
     * @param User  $user
     * @param array $scope
     * 
     * @return boolean
     */
    public function hasScope(User $user, $scope);

    /**
     * Require a specific scope for this provider.
     * 
     * @param string $scope
     * 
     * @return boolean|Response
     */
    public function requireScope($scope = null);

    /**
     * Require login scope for this provider
     * 
     * @return string
     */
    public function requireLogin();

    /**
     * Returns a Discoverer for this provider
     * 
     * @return \App\CoreBundle\Provider\DiscovererInterface
     */
    public function getDiscoverer();

    /**
     * Returns an Importer for this provider
     * 
     * @return \App\CoreBundle\Provider\ImporterInterface
     */
    public function getImporter();

    /**
     * Fetches indexed repositories indexed by organisation name
     * 
     * @param User $user
     * 
     * @return array
     */
    public function getIndexedRepositories(User $user);

    /**
     * Fetches repositories
     * 
     * @param User $user
     * 
     * @return array
     */
    public function getRepositories(User $user);

    /**
     * Creates a Payload object from a request
     * 
     * @param Request $request
     * 
     * @return \App\CoreBundle\Provider\PayloadInterface
     */
    public function createPayloadFromRequest(Request $request);

    /**
     * Creates a pull request from a Payload object
     * 
     * @param Project           $project
     * @param PayloadInterface  $payload
     * 
     * @todo implement PayloadInterface#getPullRequestUrl and PayloadInterface#getPullRequestTitle
     * 
     * @return PullRequest
     */
    public function createPullRequestFromPayload(Project $project, $payload);

    /**
     * Returns the head branch of the pull request of a build
     * 
     * @param Build  $build
     * @param string $separator
     * 
     * @return string
     */
    public function getPullRequestHead(Build $build, $separator = ':');

    /**
     * Returns an HTTP client configured for a Project
     * 
     * @param Project $project
     * 
     * @return Client
     */
    public function configureClientForProject(Project $project);

    /**
     * Returns an HTTP client configured for a User
     * 
     * @param User $user
     * 
     * @return Client
     */
    public function configureClientForUser(User $user);

    /**
     * Returns an HTTP client configured for a User
     * 
     * @param string $accessToken
     * 
     * @return Client
     */
    public function configureClientForAccessToken($accessToken);

    /**
     * Fetches collaborators for a project
     * 
     * @param Project $project
     * 
     * @return array
     */
    public function getCollaborators(Project $project);

    /**
     * Sets commit status for a build
     * 
     * @param Project $project
     * @param Build $build
     * @param string $status
     */
    public function setCommitStatus(Project $project, Build $build, $status);

    /**
     * Sends a comment on a pull request
     * 
     * @param Project $project
     * @param Build $build
     */
    public function sendPullRequestComment(Project $project, Build $build);

    /**
     * Fetches branches for a project
     * 
     * @param Project $project
     * 
     * @return Branch[]
     */
    public function getBranches(Project $project);

    /**
     * Prune deploy keys for a project
     * 
     * @param Project $project
     */
    public function pruneDeployKeys(Project $project);

    /**
     * Install the deploy keys on a project
     * 
     * @param Project $project
     */
    public function installDeployKey(Project $project);

    /**
     * Checks if deploy keys are installed
     * 
     * @param Project $project
     * 
     * @return boolean
     */
    public function hasDeployKey(Project $project);

    /**
     * Uninstall all web hooks from a project
     * 
     * @param Project $project
     */
    public function clearHooks(Project $project);

    /**
     * Install web hooks for a project
     * 
     * @param Project $project
     */
    public function installHooks(Project $project);

    /**
     * Trigger web hooks for a project (testing purpose mainly)
     * 
     * @param Project $project
     */
    public function triggerWebHook(Project $project);

    /**
     * Translate a commit hash to a ref
     * 
     * @param Project $project
     * @param string  $ref
     *
     * @return string
     */
    public function getHashFromRef(Project $project, $ref);

    /**
     * Counts deploy keys for a project
     * 
     * @param Project $project
     * 
     * @return integer
     */
    public function countDeployKeys(Project $project);

    /**
     * Counts push hooks on a project
     * 
     * @param Project $project
     * 
     * @return integer
     */
    public function countPushHooks(Project $project);

    /**
     * Counts PR hooks on a project
     * 
     * @param Project $project
     * 
     * @return integer
     */
    public function countPullRequestHooks(Project $project);
}