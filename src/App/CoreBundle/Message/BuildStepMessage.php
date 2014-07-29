<?php

namespace App\CoreBundle\Message;

use App\Model\Build;

class BuildStepMessage extends AbstractMessage
{
    /**
     * @param string $step
     */
    public function __construct(Build $build, $step)
    {
        $this->setExtra(['announce' => ['step' => $step]]);

        parent::__construct($build);
    }
}