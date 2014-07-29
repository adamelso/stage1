<?php

namespace App\CoreBundle\Quota;

use Doctrine\Common\Persistence\ObjectManager;
use App\CoreBundle\Scheduler\SchedulerInterface;
use App\Model\Build;
use App\Model\BuildRepository;
use App\Model\User;
use Psr\Log\LoggerInterface;

use Exception;

/**
 * App\CoreBundle\Quota\RunningBuildsQuota
 */
class RunningBuildsQuota implements QuotaInterface
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var App\CoreBundle\Scheduler\SchedulerInterface
     */
    private $scheduler;

    /**
     * @var App\Model\BuildRepository
     */
    private $repository;

    /**
     * @var integer
     */
    private $limit;

    /**
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @param Psr\Log\LoggerInterface                       $logger
     * @param App\CoreBundle\Scheduler\SchedulerInterface   $scheduler
     * @param App\Model\BuildRepository                     $repository
     * @param integer                                       $limit
     */
    public function __construct(LoggerInterface $logger, SchedulerInterface $scheduler, BuildRepository $repository, $limit)
    {
        $this->logger = $logger;
        $this->scheduler = $scheduler;
        $this->repository = $repository;
        $this->limit = (int) $limit;
    }

    /**
     * @param App\Model\User $user
     * 
     * @return App\Model\Build[]
     */
    private function getRunningBuilds(User $user)
    {
        $builds = $this->repository->findRunningBuildsByUser($user);

        // demo builds don't count in the running builds quota
        $builds = array_filter($builds, function(Build $build) {
            return !$build->isDemo();
        });

        return $builds;
    }

    /**
     * @param App\Model\User $user
     * 
     * @return boolean
     */
    public function check(User $user)
    {
        $builds = $this->getRunningBuilds($user);

        if (count($builds) === 0 || count($builds) <= $this->limit) {
            return true;
        }

        return false;
    }

    /**
     * @param App\Model\User $user
     */
    public function enforce(User $user)
    {
        if ($this->check($user)) {
            return;
        }

        $builds = $this->getRunningBuilds($user);
        $excessBuilds = array_slice($builds, $this->limit);

        foreach ($excessBuilds as $build) {
            $this->logger->info('Terminating excess build', ['build_id' => $build->getId()]);
            $this->scheduler->stop($build);
        }
    }
}