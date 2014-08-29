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
     * 
     * @return ImporterInterface
     */
    public function setInitialProjectAccess(ProjectAccess $initialProjectAccess);

    /**
     * @param boolean $bool
     * 
     * @return ImporterInterface
     */
    public function setFeatureIpAccessList($bool);

    /**
     * @param boolean $bool
     * 
     * @return ImporterInterface
     */
    public function setFeatureTokenAccessList($bool);

    /**
     * @param string|array $accessToken
     * 
     * @return ImporterInterface
     */
    public function setAccessToken($accessToken);

    /**
     * @param string
     */
    public function getAccessToken();

    /**
     * @param User $user
     * 
     * @return ImporterInterface
     */
    public function setUser(User $user);

    /**
     * @return User
     */
    public function getUser();

    /**
     * @param array     $providerData
     * @param Closure   $callback
     * 
     * @return App\Model\Project
     */
    public function import(array $providerData, Closure $callback = null);
}