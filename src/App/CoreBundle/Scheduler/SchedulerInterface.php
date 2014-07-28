<?php

namespace App\CoreBundle\Scheduler;

use App\Model\Build;

interface SchedulerInterface
{
    public function stop(Build $build);

    public function kill(Build $build);
}