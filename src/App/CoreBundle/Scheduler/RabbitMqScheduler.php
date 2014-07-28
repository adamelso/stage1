<?php

namespace App\CoreBundle\Scheduler;

use App\Model\Build;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Psr\Log\LoggerInterface;

class RabbitMqScheduler implements SchedulerInterface
{
    private $logger;

    private $stopProducer;

    private $killProducer;

    public function __construct(LoggerInterface $logger, Producer $stopProducer, Producer $killProducer)
    {
        $this->logger = $logger;
        $this->stopProducer = $stopProducer;
        $this->killProducer = $killProducer;
    }

    public function stop(Build $build)
    {
        $this->logger->info('sending stop order', [
            'build_id' => $build->getId(),
            'routingKey' => $build->getRoutingKey(),
        ]);

        $message = json_encode(['build_id' => $build->getId()]);
        $routingKey = $build->getRoutingKey();

        $this->stopProducer->publish($message, $routingKey);
    }

    public function kill(Build $build)
    {
        $this->logger->info('sending kill order', [
            'build_id' => $build->getId(),
            'routingKey' => $build->getRoutingKey(),
        ]);

        $message = json_encode(['build_id' => $build->getId()]);
        $routingKey = $build->getRoutingKey();

        $this->killProducer->publish($message, $routingKey);
    }
}