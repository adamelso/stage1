<?php

namespace App\CoreBundle\Tests\Quota;

use App\CoreBundle\Quota\PerUserRunningBuildsQuota;
use App\Model\Build;
use PHPUnit_Framework_TestCase;

class PerUserRunningBuildsQuotaTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->user       = $this->getMock('App\\Model\\User');
        $this->build      = $this->getMock('App\\Model\\Build');
        $this->logger     = $this->getMock('Psr\\Log\\LoggerInterface');
        $this->scheduler  = $this->getMock('App\\CoreBundle\\Scheduler\\SchedulerInterface');
        $this->repository = $this->getMockBuilder('App\\Model\\BuildRepository')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testCheckInQuotaUser()
    {
        $build = $this->build;

        $this->repository
            ->method('findRunningBuildsByUser')
            ->willReturn([clone $build, clone $build]);

        $limit = 2;

        $quota = new PerUserRunningBuildsQuota($this->logger, $this->scheduler, $this->repository, $limit);

        $this->assertTrue($quota->check($this->user));
    }

    public function testCheckOffQuotaUser()
    {
        $build = $this->build;

        $this->repository
            ->method('findRunningBuildsByUser')
            ->willReturn([clone $build, clone $build, clone $build]);

        $limit = 2;

        $quota = new PerUserRunningBuildsQuota($this->logger, $this->scheduler, $this->repository, $limit);

        $this->assertFalse($quota->check($this->user));
    }

    public function testCheckDoesnCountDemoBuilds()
    {
        $build = $this->build;

        $demoBuild = clone $build;
        $demoBuild
            ->method('isDemo')
            ->willReturn(true);

        $this->repository
            ->method('findRunningBuildsByUser')
            ->willReturn([$demoBuild, clone $build, clone $build]);

        $limit = 2;

        $quota = new PerUserRunningBuildsQuota($this->logger, $this->scheduler, $this->repository, $limit);

        $this->assertTrue($quota->check($this->user));
    }

    public function testEnforce()
    {
        $build = $this->build;

        $excessBuild = clone $build;

        $this->user
            ->method('getUsername')
            ->willReturn('jdoe');

        $this->scheduler
            ->expects($this->once())
            ->method('stop')
            ->with($excessBuild, Build::STATUS_STOPPED, 'Per-user running builds limit reached (jdoe)');

        $this->repository
            ->method('findRunningBuildsByUser')
            ->willReturn([$excessBuild, clone $build, clone $build]);

        $limit = 2;

        $quota = new PerUserRunningBuildsQuota($this->logger, $this->scheduler, $this->repository, $limit);

        $quota->enforce($this->user);
    }
}
