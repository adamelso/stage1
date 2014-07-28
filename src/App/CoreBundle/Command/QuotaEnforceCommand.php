<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QuotaEnforceCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:quota:enforce')
            ->setDescription('Enforces quotas');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $repository = $container->get('doctrine')->getRepository('Model:User');
        $users = $repository->findAll();

        $runningBuildsQuota = $container->get('app_core.quota.running_builds');

        foreach ($users as $user) {
            $runningBuildsQuota->enforce($user);
        }
    }
}