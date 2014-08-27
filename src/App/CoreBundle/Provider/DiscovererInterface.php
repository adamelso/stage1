<?php

namespace App\CoreBundle\Provider;

use App\Model\User;

/**
 * App\CoreBundle\Provider\DiscovererInterface
 */
interface DiscovererInterface
{
    /**
     * @param User $user
     */
    public function discover(User $user);

    /**
     * @return array
     */
    public function getNonImportableProjects();

    /**
     * @return array
     */
    public function getImportableProjects();
}