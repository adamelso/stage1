<?php

namespace App\CoreBundle\Message;

interface MessageInterface
{
    /**
     * @return string
     */
    public function getEvent();

    public function getData();

    public function getChannel();

    public function getRoutes();

    /**
     * @return string
     */
    public function __toString();
}