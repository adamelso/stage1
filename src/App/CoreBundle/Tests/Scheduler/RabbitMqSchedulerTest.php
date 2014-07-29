<?php

namespace App\CoreBundle\Tests\Scheduler;

use App\CoreBundle\Scheduler\RabbitMqScheduler;
use PHPUnit_Framework_TestCase;

class RabbitMqSchedulerTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->build    = $this->getMock('App\\Model\\Build');
        $this->logger   = $this->getMock('Psr\\Log\\LoggerInterface');
    }

    public function getProducer()
    {
        return $this->getMockBuilder('OldSound\\RabbitMqBundle\\RabbitMq\\Producer')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testStop()
    {
        $this->build
            ->method('getId')
            ->willReturn(42);

        $stopProducer = $this->getProducer();
        $stopProducer
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function($message) {
                $message = json_decode($message);
                return $message->build_id === 42;
            }));

        $scheduler = new RabbitMqScheduler($this->logger, $stopProducer, $this->getProducer());
        $scheduler->stop($this->build);
    }

    public function testKill()
    {
        $this
            ->build
            ->method('getId')
            ->willReturn(42);

        $killProducer = $this->getProducer();
        $killProducer
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function($message) {
                $message = json_decode($message);
                return $message->build_id === 42;
            }));

        $scheduler = new RabbitMqScheduler($this->logger, $this->getProducer(), $killProducer);
        $scheduler->kill($this->build);
    }
}