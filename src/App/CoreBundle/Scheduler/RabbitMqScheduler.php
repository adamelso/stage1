<?php

namespace App\CoreBundle\Scheduler;

use App\Model\Build;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Psr\Log\LoggerInterface;

class RabbitMqScheduler implements SchedulerInterface
{
    private $logger;

    private $producers = [];

    public function __construct(LoggerInterface $logger, Producer $stopProducer, Producer $killProducer)
    {
        $this->logger = $logger;
        $this->producers['stop'] = $stopProducer;
        $this->producers['kill'] = $killProducer;
    }

    private function send($order, Build $build)
    {
        $this->logger->info('sending scheduler order', [
            'order' => $order,
            'build_id' => $build->getId(),
            'routingKey' => $build->getRoutingKey(),
        ]);

        $message = ['build_id' => $build->getId()];
        $routingKey = $build->getRoutingKey();

        $this->producers[$order]->publish(json_encode($message), $routingKey);
    }

    public function stop(Build $build)
    {
        $this->send('stop', $build);
    }

    public function kill(Build $build)
    {
        $this->send('kill', $build);
    }
}