<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildFinishedEvent;
use App\CoreBundle\Quota\QuotaInterface;
use Psr\Log\LoggerInterface;

/**
 * Ensures a user does not go over his running instances quota
 * @todo this should probably be moved to a QuotaListener or something
 */
class ContainersLimitListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var App\CoreBundle\Quota\QuotaInterface
     */
    private $quota;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger, QuotaInterface $quota)
    {
        $this->logger = $logger;
        $this->quota = $quota;

        $logger->info('initialized '.__CLASS__);
    }

    /**
     * @param App\CoreBundle\Event\BuildFinishedEvent
     */
    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();
        $logger = $this->logger;

        if (!$build->isRunning()) {
            return;
        }

        $this->quota->enforce($build->getUsers()->first());
   }
}
