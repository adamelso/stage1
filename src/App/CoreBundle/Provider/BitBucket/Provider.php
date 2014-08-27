<?php

namespace App\CoreBundle\Provider;

use App\CoreBundle\Provider\ProviderInterface;
use App\CoreBundle\Provider\OAuthProviderTrait;
use App\CoreBundle\Provider\OAuthProviderInterface;
use Psr\Log\LoggerInterface;

class Provider implements ProviderInterface, OAuthProviderInterface
{
    use OAuthProviderTrait;

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}