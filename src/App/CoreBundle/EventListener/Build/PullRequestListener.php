<?php

namespace App\CoreBundle\EventListener\Build;

use App\CoreBundle\Event\BuildFinishedEvent;
use App\Model\Build;
use Guzzle\Http\Client;
use Psr\Log\LoggerInterface;

/**
 * Marks a previous build for a same ref obsolete
 */
class PullRequestListener
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Guzzle\Http\Client
     */
    private $github;

    /**
     * @var boolean
     */
    private $enabled;

    /**
     * @param LoggerInterface   $logger
     * @param Client        $github
     * @param Docker\Docker
     */
    public function __construct(LoggerInterface $logger, Client $github, $enabled)
    {
        $github->setDefaultOption('headers/Accept', 'application/vnd.github.v3');

        $this->logger = $logger;
        $this->github = $github;
        $this->enabled = $enabled;

        $logger->info('initialized '.__CLASS__);
    }

    public function onBuildFinished(BuildFinishedEvent $event)
    {
        if (!$this->enabled) {
            return;
        }
        
        $build = $event->getBuild();

        if (!$build->isRunning()) {
            return;
        }

        $project = $build->getProject();

        $this->github->setDefaultOption('headers/Authorization', 'token '.$project->getUsers()->first()->getAccessToken());
        $request = $this->github->get(['/repos/'.$project->getGithubFullName().'/pulls{?data*}', [
            'state' => 'open',
            'head' => str_replace('/', ':', $build->getPullRequestHead())
        ]]);

        $response = $request->send();

        foreach ($response->json() as $pr) {
            $this->logger->info('sending pull request comment', [
                'build' => $build->getId(),
                'project' => $project->getGithubFullNAme(),
                'pr' => $pr['number'],
                'pr_url' => $pr['html_url']
            ]);

            $commentRequest = $this->github->post($pr['comments_url']);
            $commentRequest->setBody(json_encode([
                'body' => 'Stage1 build finished, url: '.$build->getUrl(),
            ]));

            $commentRequest->send();
        }
    }
}