<?php

namespace App\CoreBundle\Controller;

use App\CoreBundle\Provider\Exception as ProviderException;
use App\CoreBundle\Provider\ProviderInterface;
use App\Model\User;
use App\Model\BetaSignup;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class OAuthController extends Controller
{
    /**
     * @param User $user
     * @param SessionInterface $session
     * 
     * @return boolean
     */
    private function isForceEnabled(User $user, SessionInterface $session)
    {
        if (in_array($user->getUsername(), $this->container->getParameter('force_enable'))) {
            return true;
        }

        if (null === ($betaKey = $session->get('beta_key'))) {
            return false;
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository('Model:BetaSignup');

        return (null !== $repo->findByBetaKey($betaKey));
    }

    /**
     * @param User $user
     * 
     * @return BetaSignup
     */
    private function createBetaSignup(User $user)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('Model:BetaSignup');

        if (null === $beta = $repo->findOneByEmail($user->getEmail())) {
            $beta = new BetaSignup();
            $beta->setBetaKey(md5(uniqid()));
            $beta->setEmail($user->getEmail());
            $beta->setTries($user->getWaitingList());
            $beta->setStatus(BetaSignup::STATUS_DEFAULT);

            $em->persist($beta);            
        }

        return $beta;
    }

    /**
     * @param Request $request
     * 
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function providerAction(Request $request, $providerName)
    {
        $provider = $this->get('app_core.provider.factory')->getProviderByName($providerName);

        return $provider->requireLogin();
    }

    /**
     * @param Request   $request
     * @param string    $providerName
     * 
     * @return Response
     */
    public function callbackAction(Request $request, $providerName)
    {
        $provider = $this->get('app_core.provider.factory')->getProviderByName($providerName);
        $user = $this->getUser();

        if ($this->get('security.context')->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->handleOAuthCallbackAuthenticated($request, $user, $provider);
        } else {
            return $this->handleOAuthCallbackNotAuthenticated($request, $provider);
        }
    }

    /**
     * @param Request   $request
     * @param string    $providerName
     * 
     * @return Response
     */
    private function handleOAuthCallbackNotAuthenticated(Request $request, ProviderInterface $provider)
    {
        try {
            $data = $provider->handleOAuthCallback($request);

            $repository = $this->get('doctrine')->getRepository('Model:User');

            $user = $repository->findOneBy([
                'loginProviderUserId' => $provider->getProviderUserId($data['access_token']),
                'loginProviderName' => $provider->getName(),
            ]);

            if (!$user) {
                $user = $provider->createUser($data['access_token']);
                $user->setStatus(User::STATUS_WAITING_LIST);
                $user->setWaitingList($user->getWaitingList() + 1);
                $user->addRole('ROLE_USER');
                $user->setPassword(md5(uniqid()));
                $user->setEnabled(true);
            }

            if ($user->getStatus() === User::STATUS_WAITING_LIST) {
                if ($this->isForceEnabled($user, $request->getSession())) {
                    $user->setStatus(User::STATUS_ENABLED);
                } else {
                    $user->setWaitingList($user->getWaitingList() + 1);
                    $user->setBetaSignup($this->createBetaSignup($user));
                }
            }

            if (null !== $id = $this->get('session')->get('beta_signup')) {
                $betaSignup = $this->get('doctrine')->getRepository('Model:BetaSignup')->find($id);
                $user->setBetaSignup($betaSignup);
            }

            $user->setLastLogin(new DateTime());
            $user->setProviderAccessToken($provider->getName(), $data['access_token']);

            $provider->refreshScopes($user);

            $manager = $this->get('doctrine.orm.entity_manager');
            $manager->persist($user);
            $manager->flush();
        } catch (ProviderException $e) {

            $csrfToken = $this->container->has('form.csrf_provider')
                ? $this->container->get('form.csrf_provider')->generateCsrfToken('authenticate')
                : null;

            $session = $request->getSession();
            $lastUsername = (null === $session) ? '' : $session->get(SecurityContextInterface::LAST_USERNAME);

            return $this->render('FOSUserBundle:Security:login.html.twig', [
                'error' => $e->getMessage(),
                'csrf_token' => $csrfToken,
                'last_username' => $lastUsername,
            ]);
        }

        $this->get('fos_user.security.login_manager')->loginUser('main', $user);

        if ($request->getSession()->has('_security.main.target_path')) {
            $redirectUrl = $request->getSession()->get('_security.main.target_path');
            $request->getSession()->remove('_security.main.target_path');
        } else {
            $redirectRoute = (count($user->getProjects()) == 0) ? 'app_core_import' : 'app_core_homepage';
            $redirectUrl = $this->generateUrl($redirectRoute);
        }

        return $this->redirect($redirectUrl);
    }


    /**
     * @param Request   $request
     * @param string    $providerName
     * 
     * @return Response
     */
    private function handleOAuthCallbackAuthenticated(Request $request, User $user, ProviderInterface $provider)
    {
        try {
            $provider->handleOAuthCallback($request, $user);

            $manager = $this->get('fos_user.user_manager');
            $manager->updateUser($user);
        } catch (ProviderException $e) {
            return $this->render('AppCoreBundle:Import:index.html.twig', [
                'exception' => $e,
                'providers' => $this->container->getParameter('providers'),
            ]);
        }

        $session = $request->getSession();

        if (null !== $redirectUri = $session->get('import/redirect_uri')) {
            $session->remove('import/redirect_uri');

            return $this->redirect($redirectUri);
        }

        return $this->redirect($this->generateUrl('app_core_import_provider', [
            'providerName' => $provider->getName()
        ]));
    }
}