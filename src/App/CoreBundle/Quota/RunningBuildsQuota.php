<?php

namespace App\CoreBundle\Quota;

use Docker\Docker;
use Doctrine\Common\Persistence\ObjectManager;
use App\Model\Build;
use App\Model\BuildRepository;
use App\Model\User;
use Psr\Log\LoggerInterface;

class RunningBuildsQuota
{
    private $logger;

    private $docker;

    private $repository;

    private $limit;

    private $output;

    public function __construct(LoggerInterface $logger, Docker $docker, ObjectManager $manager, $limit)
    {
        $this->logger = $logger;
        $this->docker = $docker;
        $this->manager = $manager;
        $this->limit = (int) $limit;
    }

    private function getRunningBuilds(User $user)
    {
        $builds = $this->manager
            ->getRepository('Model:Build')
            ->findRunningBuildsByUser($user);

        // demo builds don't count in the running builds quota
        $builds = array_filter($builds, function(Build $build) {
            return !$build->getBranch()->getIsDemo();
        });

        return $builds;
    }

    private function terminate(Build $build)
    {
        $container = $build->getContainer();

        $this->logger->info('Terminating excess build', [
            'build_id' => $build->getId(),
            'container_id' => ($container ? $container->getId() : 'null'),
        ]);

        $build->setStatus(Build::STATUS_STOPPED);
        $build->setMessage('Per-user running containers limit reached');

        if (!$container) {
            return;
        }

        try {
            $this->docker
                ->getContainerManager()
                ->stop($container)
                ->remove($container);
        } catch (Exception $e) {
            $this->logger->error('Could not stop container', [
                'build_id' => $build->getId(),
                'container_id' => $build->getContainer()->getId(),
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function check(User $user)
    {
        $builds = $this->getRunningBuilds($user);

        if (count($builds) === 0 || count($builds) <= $this->limit) {
            return true;
        }

        return false;
    }

    public function enforce(User $user)
    {
        if ($this->check($user)) {
            return;
        }

        $manager = $this->manager;
        $builds = $this->getRunningBuilds($user);
        $excessBuilds = array_slice($builds, $this->limit);

        foreach ($excessBuilds as $build) {
            $this->terminate($build);
            $manager->persist($build);
        }

        $manager->flush();
    }
}