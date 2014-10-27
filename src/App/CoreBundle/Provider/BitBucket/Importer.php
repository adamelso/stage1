<?php

namespace App\CoreBundle\Provider\BitBucket;

use App\Model\Organization;
use App\Model\Project;
use App\Model\PullRequest;
use App\CoreBundle\Provider\AbstractImporter;
use App\CoreBundle\SshKeysGenerator;
use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * App\CoreBundle\Provider\BitBucket\Importer
 */
class Importer extends AbstractImporter
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $oauthKey;

    /**
     * @var string
     */
    private $oauthSecret;

    /**
     * @param LoggerInterface   $logger
     * @param RegistryInterface $doctrine
     * @param Redis             $redis
     * @param SshKeysGenerator  $sshKeysGenerator
     * @param Client            $client
     */
    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Redis $redis, SshKeysGenerator $sshKeysGenerator, Client $client, $oauthKey, $oauthSecret)
    {
        $this->client = $client;
        $this->oauthKey = $oauthKey;
        $this->oauthSecret = $oauthSecret;

        parent::__construct($logger, $doctrine, $redis, $sshKeysGenerator);
    }

    /**
     * The BitBucket provider does not support PR yet
     *
     * {@inheritDoc}
     */
    public function getSteps()
    {
        $steps = parent::getSteps();

        foreach ($steps as $i => $step) {
            if ($step['id'] === 'pull_requests') {
                unset($steps[$i]);
            }
        }

        return array_values($steps);
    }

    /**
     * {@inheritDoc}
     */
    public function setAccessToken($accessToken)
    {
        parent::setAccessToken($accessToken);

        $this->client->addSubscriber(new OauthPlugin([
            'consumer_key' => $this->oauthKey,
            'consumer_secret' => $this->oauthSecret,
            'token' => $accessToken['identifier'],
            'token_secret' => $accessToken['secret'],
        ]));
    }

    /**
     * @param Project
     */
    protected function doInspect(Project $project)
    {
        try {
            $response = $this->client->get('2.0/repositories/'.$project->getProviderData('full_slug'))->send();
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $this->logger->error($e->getMessage());

            return false;
        }

        $infos = $response->json();

        # @todo @slug
        $project->setSlug(preg_replace('/[^a-z0-9\-]/', '-', strtolower($infos['full_name'])));

        $providerData = [
            'id' => $infos['full_name'],
            'owner_login' => $infos['owner']['username'],
            'full_name' => sprintf('%s/%s', $infos['owner']['username'], $infos['name']),
            'full_slug' => $infos['full_name'],
            'private' => $infos['is_private'],
            'url' => $infos['links']['self']['href'],
        ];

        foreach ($infos['links']['clone'] as $link) {
            switch ($link['name']) {
                case 'https':
                    $providerData['clone_url'] = $link['href'];
                    break;
                case 'ssh':
                    $providerData['ssh_url'] = $link['href'];
                    break;
            }
        }

        $project->setProviderName($this->provider->getName());
        $project->setProviderData($providerData);

        $project->setFullName($providerData['full_name']);
        $project->setName($infos['name']);
        $project->setIsPrivate($providerData['private']);
        $project->setGitUrl($providerData['private'] ? $providerData['ssh_url'] : $providerData['clone_url']);

        $project->setDockerBaseImage('stage1/symfony2');

        if (strpos($infos['owner']['links']['self']['href'], '.0/teams')) {
            $orgName = $infos['owner']['username'];

            $this->logger->info('attaching project\'s organization', ['organization' => $orgName]);

            $rp = $this->doctrine->getRepository('Model:Organization');

            $org = $rp->findOneBy([
                'name' => $orgName,
                'providerName' => $this->provider->getName()
            ]);

            if (null === $org) {
                $this->logger->info('organization not found, creating', ['organization' => $orgName]);
                $orgKeys = $this->sshKeysGenerator->generate();

                $org = new Organization();
                $org->setName($orgName);
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
     */
    protected function doPullRequests(Project $project)
    {
        // right now, there's no clean way to checkout bitbucket's pull request, so we're just going to not support them
        return;

        // @bitbucketapi sometimes its {owner}, sometimes {accountname}
        // also, URL is wrong in documentation (is 1.0, should be 2.0)
        // see https://confluence.atlassian.com/display/BITBUCKET/pullrequests+Resource#pullrequestsResource-GETalistofopenpullrequests
        $url = sprintf('2.0/repositories/%s/pullrequests?state=OPEN', $project->getFullName());

        foreach ($this->fetchPullRequests($url) as $data) {
            $pr = new PullRequest();
            $pr->setNumber($data['id']);
            $pr->setOpen(true);
            $pr->setTitle($data['title']);

            // $pr->setRef(sprintf('pull/%d/head', $data['number']));

            $pr->setProject($project);
            $project->addPullRequest($pr);
        }
    }

    /**
     * @param string $url
     *
     * @return array
     */
    protected function fetchPullRequests($url)
    {
        $data = $this->client->get($url)->send()->json();
        $pullRequests = [];

        foreach ($data['values'] as $pr) {
            var_dump($pr);
            $pullRequests[] = $pr;
        }

        return isset($data['next'])
            ? array_merge($pullRequests, $this->fetchPullRequests($data['next']))
            : $pullRequests;
    }
}
