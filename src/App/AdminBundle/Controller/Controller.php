<?php

namespace App\AdminBundle\Controller;

use App\CoreBundle\Controller\Controller as BaseController;

class Controller extends BaseController
{
    protected function findBuild($id, $checkAuth = false)
    {
        return parent::findBuild($id, $checkAuth);
    }

    protected function findProject($id, $checkAuth = false)
    {
        return parent::findProject($id, $checkAuth);
    }
}
