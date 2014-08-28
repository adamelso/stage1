<?php

namespace App\CoreBundle\Provider\BitBucket;

use App\Model\User;
use App\CoreBundle\Provider\AbstractImporter;
use App\CoreBundle\SshKeysGenerator;
use Guzzle\Plugin\Oauth\OauthPlugin;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
     * @param LoggerInterface       $logger
     * @param RegistryInterface     $doctrine
     * @param Redis                 $redis
     * @param UrlGeneratorInterface $router
     * @param SshKeysGenerator      $sshKeysGenerator
     * @param Client                $client
     */
    public function __construct(LoggerInterface $logger, RegistryInterface $doctrine, Redis $redis, UrlGeneratorInterface $router, SshKeysGenerator $sshKeysGenerator, Client $client, $oauthKey, $oauthSecret)
    {
        $this->client = $client;
        $this->oauthKey = $oauthKey;
        $this->oauthSecret = $oauthSecret;

        parent::__construct($logger, $doctrine, $redis, $router, $sshKeysGenerator);
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
     * {@inheritDoc}
     */
    public function setUser(User $user)
    {
        parent::setUser($user);

        /** @todo use ProviderInterface#getName */
        if (strlen($user->hasProviderAccessToken('bitbucket'))) {
            /** @todo use ProviderInterface#getName */
            $this->setAccessToken($user->getProviderAccessToken('bitbucket'));
        }
    }
}