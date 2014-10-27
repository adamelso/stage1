<?php

namespace App\CoreBundle\Provider\GitHub;

use App\CoreBundle\Provider\DiscovererInterface;
use App\Model\User;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Response;
use Psr\Log\LoggerInterface;

class Discover implements DiscovererInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $projectsCache = [];

    /**
     * @var array
     */
    private $importableProjects = [];

    /**
     * @var array
     */
    private $nonImportableProjects = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Client          $client
     * @param LoggerInterface $logger
     */
    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function discover(User $user)
    {
        $client = clone $this->client;
        $client->setDefaultOption('headers/Authorization', 'token '.$user->getProviderAccessToken('github'));

        $request = $client->get('/user/orgs');

        $response = $request->send();
        $data = $response->json();

        $orgRequests = [$client->get('/user/repos')];

        foreach ($data as $org) {
            $this->logger->debug(sprintf('adding "'.$org['repos_url'].'" for crawl'));

            $orgRequests[] = $client->get($org['repos_url']);
        }

        $orgResponses = $client->send($orgRequests);

        $composerRequests = [];

        foreach ($orgResponses as $orgResponse) {
            $this->fetchRepos($orgResponse);

            if ($orgResponse->hasHeader('link')) {
                $link = $orgResponse->getHeader('link');

                if (preg_match('/.* <(.+?)\?page=(\d+)>; rel="last"$/', $link, $matches)) {
                    $pagesRequests = [];

                    for ($i = 2; $i <= $matches[2]; $i++) {
                        $this->logger->debug(sprintf('adding "'.($matches[1].'?page='.$i).'" for crawl'));

                        $pagesRequests[] = $client->get($matches[1].'?page='.$i);
                    }

                    $pagesResponses = $client->send($pagesRequests);

                    foreach ($pagesResponses as $pagesResponse) {
                        $this->fetchRepos($pagesResponse);
                    }
                }
            }
        }

        return $this->getImportableProjects();
    }

    /**
     * {@inheritDoc}
     */
    public function getImportableProjects()
    {
        return $this->importableProjects;
    }

    /**
     * {@inheritDoc}
     */
    public function getNonImportableProjects()
    {
        return $this->nonImportableProjects;
    }

    /**
     * @param string $fullName
     */
    private function addImportableProject($fullName)
    {
        $this->importableProjects[$fullName] = $this->getProjectInfo($fullName);
    }

    /**
     * @param string $reason
     */
    private function addNonImportableProject($fullName, $reason)
    {
        $this->nonImportableProjects[] = [
            'fullName' => $fullName,
            'reason' => $reason
        ];
    }

    /**
     * @param array $data
     */
    private function cacheProjectInfo($data)
    {
        $this->projectsCache[$data['full_name']] = array(
            'name' => $data['name'],
            'full_name' => $data['full_name'],
            'slug' => preg_replace('/[^a-z0-9\-]/', '-', strtolower($data['full_name'])),
            'owner_login' => $data['owner']['login'],
            'owner_avatar_url' => $data['owner']['avatar_url'],
            'id' => $data['id'],
            'clone_url' => $data['clone_url'],
            'ssh_url' => $data['ssh_url'],
            'hooks_url' => $data['hooks_url'],
            'keys_url' => $data['keys_url'],
            'private' => $data['private'],
            'exists' => false,
        );
    }

    /**
     * @param string $fullName
     *
     * @return array
     */
    private function getProjectInfo($fullName)
    {
        return $this->projectsCache[$fullName];
    }

    /**
     * @param Response
     */
    private function fetchRepos(Response $orgResponse)
    {
        foreach ($orgResponse->json() as $repo) {
            if (!$repo['permissions']['admin']) {
                $this->addNonImportableProject($repo['full_name'], 'no admin rights on the project');
                continue;
            }

            $this->cacheProjectInfo($repo);

            $this->addImportableProject($repo['full_name']);
        }
    }
}
