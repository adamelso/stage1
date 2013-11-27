<?php

namespace App\CoreBundle\Message;

class BuildStartedMessage extends AbstractMessage
{
    public function __toString()
    {
        $build = $this->build;

        return json_encode([
            'event' => 'build.started',
            'channel' => $build->getChannel(),
            'timestamp' => microtime(true),
            'data' => [
                'progress' => 0,
                'build' => $build->asMessage(),
            ]
        ])
    }
}