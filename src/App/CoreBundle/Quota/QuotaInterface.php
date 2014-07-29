<?php

namespace App\CoreBundle\Quota;

use App\Model\User;

/**
 * App\CoreBundle\Quota\QuotaInterface
 */
interface QuotaInterface
{
    /**
     * @param User $user
     * 
     * @return boolean
     */
    public function check(User $user);

    /**
     * @param User $user
     * @return void
     */
    public function enforce(User $user);
}