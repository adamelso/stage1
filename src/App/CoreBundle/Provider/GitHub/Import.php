<?php

namespace App\CoreBundle\Provider\GitHub;

use App\Model\Branch;
use App\Model\Organization;
use App\Model\Project;
use App\Model\ProjectSettings;
use App\Model\PullRequest;
use App\Model\User;
use App\CoreBundle\Provider\AbstractImporter;
use App\CoreBundle\Provider\InsufficientScopeException;
use App\CoreBundle\SshKeysGenerator;
use App\CoreBundle\Value\ProjectAccess;
use Closure;
use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;
use Redis;
use RuntimeException;
use Symfony\Bridge\Doctrine\RegistryInterface;


/**
 * App\CoreBundle\Provider\GitHub\Import
 */
class Import extends AbstractImporter
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param LoggerInterface       $logger
     * @param RegistryInterface     $doctrine
     * @param Redis                 $redis
     * @param SshKeysGenerator      $sshKeysGenerator
     * @param Client                $client
     */
    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Redis $redis, SshKeysGenerator $sshKeysGenerator, Client $client)
    {
        $this->client = $client;
        $this->client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        parent::__construct($logger, $doctrine, $redis, $sshKeysGenerator);
    }

    /**
     * {@inheritDoc}
     */
    public function setAccessToken($accessToken)
    {
        parent::setAccessToken($accessToken);

        $this->logger->info('using access token', ['access_token' => $accessToken]);

        $this->client->setDefaultOption('headers/Authorization', 'token '.$accessToken);
    }

    /**
     * @todo use github api instead of relying on request parameters
     * 
     * @param Project $project
     */
    protected function doInspect(Project $project)
    {
        try {
            $request = $this->client->get('/repos/'.$project->getFullName());
            $response = $request->send();
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        $infos = $response->json();

        # @todo @slug
        $project->setSlug(preg_replace('/[^a-z0-9\-]/', '-', strtolower($infos['full_name'])));

        $providerData = [
            'id' => $infos['id'],
            'owner_login' => $infos['owner']['login'],
            'full_name' => $infos['full_name'],
            'url' => $infos['url'],
            'clone_url' => $infos['clone_url'],
            'ssh_url' => $infos['ssh_url'],
            'keys_url' => $infos['keys_url'],
            'hooks_url' => $infos['hooks_url'],
            'contents_url' => $infos['contents_url'],
            'private' => $infos['private'],
        ];

        $project->setProviderName($this->provider->getName());
        $project->setProviderData($providerData);

        $project->setFullName($infos['full_name']);
        $project->setName($infos['name']);
        $project->setDockerBaseImage('symfony2:latest');
        $project->setIsPrivate($infos['private']);
        $project->setGitUrl($infos['private'] ? $infos['ssh_url'] : $infos['clone_url']);

        if (isset($infos['organization'])) {
            $this->logger->info('attaching project\'s organization', ['organization' => $infos['organization']['login']]);

            $rp = $this->doctrine->getRepository('Model:Organization');

            $org = $rp->findOneBy([
                'name' => $infos['organization']['login'],
                'providerName' => $this->provider->getName()
            ]);

            if (null === $org) {
                $this->logger->info('organization not found, creating', ['organization' => $infos['organization']['login']]);
                $orgKeys = $this->sshKeysGenerator->generate();

                $org = new Organization();
                $org->setName($infos['organization']['login']);
                $org->setPublicKey($orgKeys['public']);
                $org->setPrivateKey($orgKeys['private']);
                $org->setProviderName($this->provider->getName());
            }

            $project->setOrganization($org);
        } else {
            $this->logger->info('project has no organization, skipping');
        }
    }

    /**
     * @param Project $project
     * 
     * @return boolean
     */
    protected function doPullRequests(Project $project)
    {
        $url = sprintf('/repos/%s/pulls', $project->getFullName());

        $request = $this->client->get($url);
        $response = $request->send();

        foreach ($response->json() as $data) {
            $pr = new PullRequest();
            $pr->setNumber($data['number']);
            $pr->setOpen($data['state'] === 'open');
            $pr->setTitle($data['title']);
            $pr->setRef(sprintf('pull/%d/head', $data['number']));

            $pr->setProject($project);
            $project->addPullRequest($pr);
        }
    }
}