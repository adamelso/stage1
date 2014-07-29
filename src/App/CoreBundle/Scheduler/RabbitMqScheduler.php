<?php

namespace App\CoreBundle\Scheduler;

use App\Model\Build;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Psr\Log\LoggerInterface;

/**
 * App\CoreBundle\Scheduler\RabbitMqScheduler
 */
class RabbitMqScheduler implements SchedulerInterface
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var OldSound\RabbitMqBundle\RabbitMq\Producer[]
     */
    private $producers = [];

    /**
     * @param Psr\Log\LoggerInterface                   $logger
     * @param OldSound\RabbitMqBundle\RabbitMqProducer  $stopProducer
     * @param OldSound\RabbitMqBundle\RabbitMqProducer  $killProducer
     */
    public function __construct(LoggerInterface $logger, Producer $stopProducer, Producer $killProducer)
    {
        $this->logger = $logger;
        $this->producers['stop'] = $stopProducer;
        $this->producers['kill'] = $killProducer;
    }

    /**
     * @param string            $order
     * @param App\Model\Build   $build
     */
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

    /**
     * @param App\Model\Build $build
     */
    public function stop(Build $build)
    {
        $this->send('stop', $build);
    }

    /**
     * @param App\Model\Build $build
     */
    public function kill(Build $build)
    {
        $this->send('kill', $build);
    }
}