<?php

namespace App\CoreBundle\Scheduler;

use App\Model\Build;

/**
 * App\CoreBundle\Scheduler\SchedulerInterface
 */
interface SchedulerInterface
{
    /**
     * @param App\Model\Build $build
     */
    public function stop(Build $build);

    /**
     * @param App\Model\Build $build
     */
    public function kill(Build $build);
}