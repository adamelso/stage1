<?php

namespace App\CoreBundle\Provider;

/**
 * App\CoreBundle\Provider\PayloadInterface
 */
interface PayloadInterface
{
    /**
     * @return string
     */
    public function getRawContent();

    /**
     * @return boolean
     */
    public function isDummy();

    /**
     * @return string
     */
    public function getProviderName();

    /**
     * @return boolean
     */
    public function isPullRequest();

    /**
     * @return boolean
     */
    public function isBuildable();

    /**
     * @return boolean
     */
    public function hasRef();

    /**
     * @return string
     */
    public function getRef();

    /**
     * @return string
     */
    public function getHash();

    /**
     * @return integer
     */
    public function getPullRequestNumber();

    /**
     * @return string|integer
     */
    public function getRepositoryId();

    /**
     * @return string
     */
    public function getRepositoryFullName();

    /**
     * @return string|integer
     */
    public function getDeliveryId();

    /**
     * @return string
     */
    public function getEvent();
}