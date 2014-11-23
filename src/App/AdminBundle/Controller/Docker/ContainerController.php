<?php

namespace App\AdminBundle\Controller\Docker;

use App\AdminBundle\Controller\Controller;

class ContainerController extends Controller
{
    private function getClient()
    {
        return $this->container->get('app_core.docker.http_client');
    }

    private function fetch($urlspec, array $params = [])
    {
        $request = $this->getClient()->get($urlspec, $params);

        return $this->getClient()->send($request)->json(true);
    }

    public function indexAction()
    {
        $docker = $this->get('app_core.docker');

        return $this->render('AppAdminBundle:Docker/Container:index.html.twig', [
            'containers' => $docker->getContainerManager()->findAll(),
        ]);
    }

    public function stopAction($id)
    {
        $docker    = $this->get('app_core.docker');
        $container = $docker->getContainerManager()->find($id);

        $docker->getContainerManager()->stop($container);

        $this->addFlash('success', 'The container has been stopped');

        return $this->redirect($this->generateUrl('app_admin_docker_containers'));
    }

    public function inspectAction($id)
    {
        $docker = $this->get('app_core.docker');

        return $this->render('AppAdminBundle:Docker/Container:inspect.html.twig', [
            'container' => $docker->getContainerManager()->find($id),
        ]);
    }

    public function topAction($id)
    {
        $top = $this->fetch(['/containers/{id}/top?{query*}', ['id' => $id, 'query' => ['ps_args' => 'aux']]]);

        foreach ($top['Processes'] as &$entry) {
            $entry[10] = implode(' ', array_splice($entry, 10));
        }

        return $this->render('AppAdminBundle:Docker/Container:top.html.twig', [
            'top' => $top,
            'container' => $this->fetch(['/containers/{id}/json', ['id' => $id]]),
        ]);
    }
}
