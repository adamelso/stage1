<?php

namespace App\CoreBundle\Quota;

use App\Model\User;

interface QuotaInterface
{
    public function check(User $user);

    public function enforce(User $user);
}