<?php

namespace App\CoreBundle\Controller;

use App\CoreBundle\Provider\ConfigurableProviderInterface;
use App\CoreBundle\Provider\Scope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ImportController extends Controller
{
    /**
     * @return Response
     */
    public function indexAction()
    {
        return $this->render('AppCoreBundle:Import:index.html.twig', [
            'providers' => $this->container->getParameter('providers'),
        ]);
    }

    /**
     * @param Request   $request
     * @param string    $providerName
     * 
     * @return Response
     */
    public function importAction(Request $request, $providerName)
    {
        $user = $this->getUser();
        $infos = $request->request->all();
        $session = $request->getSession();

        if (!$request->get('force')) {
            $scope = $infos['private'] ? Scope::SCOPE_PRIVATE : Scope::SCOPE_PUBLIC;
            $provider = $this->get('app_core.provider.factory')->getProviderByName($providerName);

            if (!$provider->hasScope($user, $scope)) {
                $session->set('import/autostart', $infos['slug']);

                return new JsonResponse(json_encode(['ask_scope' => $scope, 'autostart' => $infos['slug']]));
            }
        }

        $session->remove('import/autostart');

        $this->get('logger')->info('requesting project import', [$infos]);

        $ret = $this->get('old_sound_rabbit_mq.project_import_producer')->publish(json_encode([
            'user_id' => $user->getId(),
            'request' => $infos,
            'provider_name' => $providerName,
            'websocket_channel' => $user->getChannel(),

            // client_ip and session_ip are needed for the initial ProjectAccess
            // (this will be refactored one day)
            'client_ip' => $this->getClientIp(),
            'session_id' => $session->getId(),
        ]));

        $this->get('logger')->debug($ret);

        return new JsonResponse(json_encode(true));
    }

    /**
     * @param Request   $request
     * @param string    $providerName
     * @param string    $scope
     * 
     * @return Response
     */
    public function scopeAction(Request $request, $providerName, $scope)
    {
        $provider = $this->get('app_core.provider.factory')->getProviderByName($providerName);

        $session = $request->getSession();
        $session->remove('import/redirect_uri');

        if (null !== $autostart = $request->get('autostart')) {
            $redirectUri = $this->generateUrl('app_core_import_provider', [
                'providerName' => $providerName,
                'autostart' => $autostart
            ]);

            $session->set('import/redirect_uri', $redirectUri);
        }

        return $provider->requireScope($scope);
    }

    /**
     * @param Request   $request
     * @param string    $providerName
     * 
     * @return Response
     */
    public function providerAction(Request $request, $providerName)
    {
        $provider = $this->get('app_core.provider.factory')->getProviderByName($providerName);

        if ($provider instanceof ConfigurableProviderInterface) {
            $type = $provider->getConfigFormType();
            $defaultConfig = $this->getUser()->getProviderConfig($provider->getName());

            $form = $this->createForm($type, $defaultConfig, [
                'method' => 'post',
                'action' => $this->generateUrl('app_core_import_provider', ['providerName' => $providerName])
            ]);

            if (false === $config = $provider->handleConfigForm($request, $form)) {
                return $this->render('AppCoreBundle:Import:providerConfig.html.twig', [
                    'provider' => $provider,
                    'form' => $form->createView(),
                ]);                
            }

            $user->setProviderConfig($provider->getName(), $config);
            $this->get('fos_user.user_manager')->updateUser($user);

            $provider->setConfig($config);
        }

        if (!$provider->hasScope($this->getUser(), Scope::SCOPE_ACCESS)) {
            $ret = $provider->requireScope(Scope::SCOPE_ACCESS);

            if ($ret instanceof Response) {
                return $ret;
            }
        }

        $indexedProjects = $provider->getIndexedRepositories($this->getUser());

        // looking for already imported projects
        $existingProjects = [];

        foreach ($this->getUser()->getProjectsByProvider($provider) as $project) {
            $existingProjects[$project->getFullName()] = true;
        }

        // looking for joinable projects
        $fullNames = [];

        foreach ($indexedProjects as $org => $projects) {
            foreach ($projects as $project) {
                $fullNames[] = $project['full_name'];
            }
        }

        $joinableProjects = [];
        $projects = $this->get('doctrine')->getRepository('Model:Project')->findByFullName($fullNames);

        foreach ($projects as $project) {
            $joinableProjects[$project->getFullName()] = [
                'users' => $project->getUsers()->map(function($user) { return $user->getUsername(); })->toArray(),
                'url' => $this->generateUrl('app_core_project_show', ['id' => $project->getId()]),
                'join_url' => $this->generateUrl('app_core_project_join', ['id' => $project->getId()]),
            ];
        }

        // @todo filter out existing projects from $indexedProjects

        return $this->render('AppCoreBundle:Import:provider.html.twig', [
            'provider' => $provider,
            'indexedProjects' => $indexedProjects,
            'existingProjects' => $existingProjects,
            'joinableProjects' => $joinableProjects,
            'importUrl' => $this->generateUrl('app_core_import_import', ['providerName' => $providerName]),
            'autostart' => $request->get('autostart')
        ]);            
    }
}