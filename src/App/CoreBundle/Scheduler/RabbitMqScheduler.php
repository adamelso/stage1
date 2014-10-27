<?php

namespace App\CoreBundle\Scheduler;

use App\Model\Build;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Psr\Log\LoggerInterface;

/**
 * \App\CoreBundle\Scheduler\RabbitMqScheduler
 */
class RabbitMqScheduler implements SchedulerInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \OldSound\RabbitMqBundle\RabbitMq\Producer[]
     */
    private $producers = [];

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param Producer                 $stopProducer
     * @param Producer                 $killProducer
     */
    public function __construct(LoggerInterface $logger, Producer $stopProducer, Producer $killProducer)
    {
        $this->logger = $logger;
        $this->producers['stop'] = $stopProducer;
        $this->producers['kill'] = $killProducer;
    }

    /**
     * @param string           $order
     * @param \App\Model\Build $build
     */
    private function send($order, Build $build, $vars = [])
    {
        $this->logger->info('sending scheduler order', [
            'order' => $order,
            'build_id' => $build->getId(),
            'routingKey' => $build->getRoutingKey(),
        ]);

        $vars = array_merge(['build_id' => $build->getId()], $vars);
        $routingKey = $build->getRoutingKey();

        $this->producers[$order]->publish(json_encode($vars), $routingKey);
    }

    /**
     * @param \App\Model\Build $build
     */
    public function stop(Build $build, $status = Build::STATUS_STOPPED, $message = null)
    {
        $this->send('stop', $build, ['message ' => $message, 'status' => $status]);
    }

    /**
     * @param \App\Model\Build $build
     */
    public function kill(Build $build, $status = Build::STATUS_KILLED, $message = null)
    {
        $this->send('kill', $build, ['message' => $message, 'status' => $status]);
    }
}
