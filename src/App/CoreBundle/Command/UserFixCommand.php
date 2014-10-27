<?php

namespace App\CoreBundle\Command;

use App\Model\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserFixCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('stage1:user:fix')
            ->setDescription('Fixes malformed User entities');
    }

    public function message(OutputInterface $output, User $user, $message)
    {
        $output->writeln(sprintf('<info>[%s]</info> %s', $user->getUsername(), $message));
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('Model:User');
        $em = $this->getContainer()->get('doctrine')->getManager();

        $env = $this->getContainer()->getParameter('kernel.environment');

        /** @deprecated */
        $client = $this->getContainer()->get('app_core.provider.github.client');
        $client->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        $emailCanonicalizer = $this->getContainer()->get('fos_user.util.email_canonicalizer');
        $usernameCanonicalizer = $this->getContainer()->get('fos_user.util.username_canonicalizer');

        foreach ($repository->findAll() as $user) {
            $client->setDefaultOption('headers/Authorization', 'token '.$user->getAccessToken());

            if ($env === 'prod' && strlen($user->getChannel(true)) === 0) {
                $this->message($output, $user, 'generating channel');
                $user->setChannel(uniqid(mt_rand(), true));
            }

            if ($env === 'dev' && strlen($user->getChannel(true)) > 0) {
                $this->message($output, $user, 'nulling channel');
                $user->setChannel(null);
            }

            if (strlen($user->getPublicKey()) === 0) {
                $this->message($output, $user, 'generating ssh keys');

                $keys = $this
                    ->getContainer()
                    ->get('app_core.ssh_keys_generator')
                    ->generate();

                $user->setPublicKey($keys['public']);
                $user->setPrivateKey($keys['private']);
            }

            if (null === $user->getRoles(true) || count($user->getRoles()) === 0) {
                $this->message($output, $user, 'setting default roles');
                $user->setRoles(['ROLE_USER']);
            }

            if (strlen($user->getAccessToken()) > 0 && count($user->getProvidersAccessTokens()) === 0) {
                $this->message($output, $user, 'fixing provider tokens');
                $user->setProviderAccessToken('github', $user->getAccessToken());
            }

            if (strlen($user->getAccessTokenScope()) > 0 && count($user->getProvidersScopes()) === 0) {
                $this->message($output, $user, 'fixing provider scopes');
                $user->setProviderScopes('github', explode(',', $user->getAccessTokenScope()));
            }

            if (strlen($user->getEmail()) > 0 && strlen($user->getEmailCanonical()) === 0) {
                $this->message($output, $user, 'generating canonical email');
                $user->setEmailCanonical($emailCanonicalizer->canonicalize($user->getEmail()));
            }

            if (strlen($user->getUsername()) > 0 && strlen($user->getUsernameCanonical()) === 0) {
                $this->message($output, $user, 'generating canonical username');
                $user->setUsernameCanonical($emailCanonicalizer->canonicalize($user->getUsername()));
            }

            if (strlen($user->getLoginProviderName()) === 0) {
                $this->message($output, $user, 'updating login provider informations');
                // default login provider is github
                $user->setLoginProviderName('github');
                $user->setLoginProviderUserId($user->getGithubId());

                $user->setEnabled(true);
            }

            $em->persist($user);
        }

        $em->flush();
    }
}
