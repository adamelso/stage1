<?php

namespace App\CoreBundle\Quota;

use App\Model\User;

/**
 * App\CoreBundle\Quota\QuotaInterface
 */
interface QuotaInterface
{
    /**
     * @param App\Model\User $user
     * 
     * @return boolean
     */
    public function check(User $user);

    /**
     * @param App\Model\User $user
     */
    public function enforce(User $user);
}