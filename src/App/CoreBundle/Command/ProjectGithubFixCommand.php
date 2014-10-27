<?php

namespace App\CoreBundle\Command;

use App\Model\Project;
use App\Model\ProjectSettings;
use App\Model\Organization;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @deprecated */
class ProjectGithubFixCommand extends ContainerAwareCommand
{
    private $githubInfos = [];

    public function configure()
    {
        $this
            ->setName('stage1:project:github:fix')
            ->setDescription('Fixes malformed GitHub Project entities');
    }

    public function message(OutputInterface $output, Project $project, $message)
    {
        $output->writeln(sprintf('<info>[%s]</info> %s', $project->getFullName(), $message));
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getContainer()->get('doctrine')->getRepository('Model:Project');
        $em = $this->getContainer()->get('doctrine')->getManager();

        foreach ($repository->findByProviderName('github') as $project) {
            $this->message($output, $project, 'using access token <info>'.$project->getUsers()->first()->getProviderAccessToken('github').'</info>');

            try {
                $githubInfos = $this->getGithubInfos($project);
            } catch (\Exception $e) {
                $output->writeln('<error>Could not fetch infos</error>');
                continue;
            }

            $providerData = $project->getProviderData();

            if (strlen($providerData['hooks_url']) === 0) {
                $this->message($output, $project, 'fixing hooks url');
                $providerData['hooks_url'] = $githubInfos['hooks_url'];
            }

            if (strlen($project->getDockerBaseImage()) === 0) {
                $this->message($output, $project, 'fixing base image');
                $project->setDockerBaseImage('symfony2:latest');
            }

            if (strlen($providerData['url']) === 0) {
                $this->message($output, $project, 'fixing github url');
                $providerData['url'] = 'https://api.github.com/repos/'.$project->getFullName();
            }

            if (!array_key_exists('private', $providerData) || !is_bool($providerData['private'])) {
                $this->message($output, $project, 'fixing private status');
                $providerData['private'] = $githubInfos['private'];
            }

            if (strlen($providerData['contents_url']) === 0) {
                $this->message($output, $project, 'fixing contents url');

                if (isset($githubInfos['contents_url'])) {
                    $providerData['contents_url'] = $githubInfos['contents_url'];
                }
            }

            if (null === $project->getOrganization() && isset($githubInfos['organization'])) {
                $this->message($output, $project, 'fixing organization');

                $orgKeys = $this
                    ->getContainer()
                    ->get('app_core.ssh_keys_generator')
                    ->generate();

                $org = new Organization();
                $org->setName($githubInfos['organization']['login']);
                $org->setGithubId($githubInfos['organization']['id']);
                $org->setPublicKey($orgKeys['public']);
                $org->setPrivateKey($orgKeys['private']);

                $project->setOrganization($org);
            }

            if (!$project->getSettings() || strlen($project->getSettings()->getPolicy()) === 0) {
                $this->message($output, $project, 'fixing build policy');

                $settings = $project->getSettings() ?: new ProjectSettings();
                $settings->setPolicy(ProjectSettings::POLICY_ALL);
                $settings->setProject($project);

                $em->persist($settings);
            }

            try {
                $githubHookUrl = $this->getContainer()->get('router')->generate('app_core_hooks_github', [], true);
                $githubHookUrl = str_replace('http://localhost', 'http://stage1.io', $githubHookUrl);

                $provider = $this->getContainer()->get('app_core.provider.github');
                $client = $provider->configureClientForProject($project);

                if (strlen($providerData['hook_id']) === 0) {
                    $this->message($output, $project, 'adding webhooks');

                    $request = $client->post($providerData['hooks_url']);
                    $request->setBody(json_encode([
                        'name' => 'web',
                        'active' => true,
                        'events' => ['push', 'pull_request'],
                        'config' => ['url' => $githubHookUrl, 'content_type' => 'json'],
                    ]), 'application/json');

                    $response = $request->send();
                    $installedHook = $response->json();

                    $providerData['hook_id'] = $installedHook['id'];
                } else {
                    $request = $client->get([$providerData['hooks_url'], [
                        'id' => $providerData['hook_id'],
                    ]]);

                    $response = $request->send();
                    $installedHook = $response->json()[0];

                    if (count($installedHook['events']) === 1) {
                        $this->message($output, $project, 'adding pull_request webhook');
                        $request = $client->patch($installedHook['url']);
                        $request->setBody(json_encode(['add_events' => ['pull_request']]));
                        $request->send();
                    }
                }
            } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                $output->writeln('<error>could not check webhook</error>');
            }

            $project->setProviderData($providerData);

            $em->persist($project);
        }

        $em->flush();
    }

    private function getGithubInfos(Project $project)
    {
        if (!array_key_exists($project->getFullName(), $this->githubInfos)) {
            $provider = $this->getContainer()->get('app_core.provider.github');
            $client = $provider->configureClientForProject($project);
            $request = $client->get($project->getGithubUrl());
            $response = $request->send();

            $this->githubInfos[$project->getFullName()] = $response->json();
        }

        return $this->githubInfos[$project->getFullName()];
    }
}
