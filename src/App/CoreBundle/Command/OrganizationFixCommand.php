<?php

namespace App\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OrganizationFixCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:organization:fix')
            ->setDescription('Fixes malformed Organization entities');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('Model:Organization');
        $em = $this->getContainer()->get('doctrine')->getManager();

        foreach ($repository->findAll() as $organization) {
            if (strlen($organization->getProviderName()) === 0) {
                $output->writeln('Fixing <info>providerName</info> for <infor>'.$organization->getName().'</info>');

                $organization->setProviderName('github');
                $em->persist($organization);
            }
        }

        $em->flush();
    }
}
