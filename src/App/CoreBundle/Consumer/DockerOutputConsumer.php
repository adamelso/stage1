<?php

namespace App\CoreBundle\Consumer;

use App\Model\Build;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Redis;

class DockerOutputConsumer implements ConsumerInterface
{
    private $logger;

    private $redis;

    private $streamMap = [0 => 'stdin', 1 => 'stdout', 2 => 'stderr'];

    public function __construct(LoggerInterface $logger, Redis $redis)
    {
        $this->logger = $logger;
        $this->redis = $redis;

        $this->logger->info('initialized '.__CLASS__, [
            'pid' => posix_getpid(),
        ]);
    }

    private function getStreamType($type)
    {
        return isset($this->streamMap[$type]) ? $this->streamMap[$type] : null;
    }

    public function execute(AMQPMessage $message)
    {
        $logger = $this->logger;
        $redis = $this->redis;

        $body = json_decode($message->body, true);

        if (!array_key_exists('BUILD_ID', $body['env'])) {
            $logger->warn('no build information', [
                'container' => $body['container'],
                'message' => $body
            ]);

            return true;
        }

        $buildId = $body['env']['BUILD_ID'];

        $logger->debug('processing log fragment', [
            'build' => $buildId,
            'container' => isset($body['container']) ? $body['container'] : null,
            'keys' => array_keys($body),
        ]);

        $build = new Build();
        $build->setId($buildId);

        $redis->rpush($build->getLogsList(), json_encode([
            'type' => Build::LOG_OUTPUT,
            'message' => $body['content'],
            'stream' => $this->getStreamType($body['type']),
            'microtime' => $body['timestamp'],
            'fragment_id' => $body['fragment_id'],
            'build_id' => $buildId,
        ]));
    }
}
