<?php

namespace App\CoreBundle\Scheduler;

use App\Model\Build;

/**
 * App\CoreBundle\Scheduler\SchedulerInterface
 */
interface SchedulerInterface
{
    /**
     * @param Build   $build
     * @param string            $message
     * @return void
     */
    public function stop(Build $build, $message = null);

    /**
     * @param Build   $build
     * @param string            $message
     * @return void
     */
    public function kill(Build $build, $message = null);
}