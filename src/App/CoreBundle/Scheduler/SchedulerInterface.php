<?php

namespace App\CoreBundle\Scheduler;

use App\Model\Build;

/**
 * App\CoreBundle\Scheduler\SchedulerInterface
 */
interface SchedulerInterface
{
    /**
     * @param App\Model\Build   $build
     * @param string            $message
     */
    public function stop(Build $build, $message = null);

    /**
     * @param App\Model\Build   $build
     * @param string            $message
     */
    public function kill(Build $build, $message = null);
}