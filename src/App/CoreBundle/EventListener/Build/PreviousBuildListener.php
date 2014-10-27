<?php

namespace App\CoreBundle\EventListener\Build;

use App\Model\Build;
use App\Model\BuildRepository;
use App\CoreBundle\Event\BuildFinishedEvent;
use App\CoreBundle\Scheduler\SchedulerInterface;
use Psr\Log\LoggerInterface;

/**
 * Marks a previous build for a same ref obsolete
 */
class PreviousBuildListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var App\Model\BuildRepository
     */
    private $repository;

    /**
     * @var App\CoreBundle\Scheduler\SchedulerInterface
     */
    private $scheduler;

    /**
     * @param Psr\Log\LoggerInterface
     * @param Symfony\Bridge\Doctrine\RegistryInterface
     * @param Docker\Docker
     */
    public function __construct(LoggerInterface $logger, BuildRepository $repository, SchedulerInterface $scheduler)
    {
        $this->logger = $logger;
        $this->repository = $repository;
        $this->scheduler = $scheduler;

        $logger->info('initialized '.__CLASS__);
    }

    public function onBuildFinished(BuildFinishedEvent $event)
    {
        $build = $event->getBuild();

        if (!$build->isRunning()) {
            return;
        }

        $previousBuild = $this->repository->findPreviousBuild($build);

        if (!$previousBuild) {
            return;
        }

        $this->logger->info('detected previous build', [
            'previous_build_id' => $previousBuild->getId(),
        ]);

        $this->scheduler->stop($previousBuild, Build::STATUS_OBSOLETE);
    }
}
