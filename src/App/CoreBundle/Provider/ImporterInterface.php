<?php

namespace App\CoreBundle\Provider;

use App\CoreBundle\Value\ProjectAccess;
use App\Model\User;
use Closure;

/**
 * App\CoreBundle\Provider\ImporterInterface
 */
interface ImporterInterface
{
    /**
     * @return array
     */
    public function getSteps();

    /**
     * @param ProjectAccess $initialProjectAccess
     */
    public function setInitialProjectAccess(ProjectAccess $initialProjectAccess);

    /**
     * @param boolean $bool
     */
    public function setFeatureIpAccessList($bool);

    /**
     * @param boolean $bool
     */
    public function setFeatureTokenAccessList($bool);

    /**
     * @param string $accessToken
     */
    public function setAccessToken($accessToken);

    /**
     * @param string
     */
    public function getAccessToken();

    /**
     * @return User
     */
    public function getUser();

    /**
     * @param User $user
     */
    public function setUser(User $user);

    /**
     * @param string $fullName
     * @param Closure $callback
     * 
     * @return App\Model\Project
     */
    public function import($fullName, Closure $callback = null);
}