<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QuotaCheckCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:quota:check')
            ->setDescription('Checks quotas')
            ->setDefinition([
                new InputOption('user', 'u', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'One or several usernames to check', []),
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $repository = $container->get('doctrine')->getRepository('Model:User');

        if (count($input->getOption('user')) > 0) {
            $users = $repository->findBy(['username' => $input->getOption('user')]);
        } else {
            $users = $repository->findAll();
        }

        $quota = $container->get('app_core.quota.per_user_running_builds');

        foreach ($users as $user) {
            if (!$quota->check($user)) {
                $output->writeln('User <info>'.((string) $user).'</info> is off-quota');
            }
        }
    }
}