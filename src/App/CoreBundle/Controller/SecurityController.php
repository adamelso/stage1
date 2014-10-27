<?php

namespace App\CoreBundle\Controller;

use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SecurityController extends Controller
{
    /**
     * @param Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function primusAuthAction(Request $request)
    {
        try {
            # @todo @channel_auth move channel auth to an authenticator service
            $channel = $request->request->get('channel');
            $token = uniqid(mt_rand(), true);

            if (strlen($channel) > 0) {
                $repo = $this->getDoctrine()->getRepository('Model:User');
                $authUser = $repo->findByChannel($channel);

                if ($authUser !== $this->getUser()) {
                    return new JsonResponse(null, 403);
                }
            } else {
                if (!$this->getUser()) {
                    throw new \RuntimeException();
                }

                $channel = $this->getUser()->getChannel();
            }

            $this->get('app_core.redis')->sadd('channel:auth:' . $channel, $token);

            return new JsonResponse(json_encode([
                'channel' => $this->getUser()->getChannel(),
                'token' => $token,
            ]));
        } catch (\Exception $e) {
            return new JsonResponse(json_encode(false), 500);
        }
    }
}
