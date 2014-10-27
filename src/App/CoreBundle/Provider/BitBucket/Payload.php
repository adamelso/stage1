<?php

namespace App\CoreBundle\Provider\BitBucket;

use App\CoreBundle\Provider\AbstractPayload;

/**
 * App\CoreBundle\Provider\BitBucket\Payload
 */
class Payload extends AbstractPayload
{
    /**
     * {@inheritDoc}
     */
    public function getProviderName()
    {
        return 'bitbucket';
    }

    /**
     * {@inheritDoc}
     */
    public function isPullRequest()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isBuildable()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function hasRef()
    {
        return isset($this->parsed['commits'][0]['branch']);
    }

    /**
     * {@inheritDoc}
     */
    public function getRef()
    {
        return $this->parsed['commits'][0]['branch'];
    }

    /**
     * {@inheritDoc}
     */
    public function getHash()
    {
        return $this->parsed['commits'][0]['raw_node'];
    }

    /**
     * {@inheritDoc}
     */
    public function isDummy()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getRepositoryId()
    {
        return $this->getRepositoryFullName();
    }

    /**
     * {@inheritDoc}
     */
    public function getRepositoryFullName()
    {
        return sprintf('%s/%s', $this->parsed['repository']['owner'], $this->parsed['repository']['name']);
    }

    /**
     * {@inheritDoc}
     */
    public function getDeliveryId()
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getEvent()
    {
        return 'push';
    }

    /**
     * {@inheritDoc}
     */
    public function getPullRequestNumber()
    {
        throw new RuntimeException('bitbucket provider does not support Pull Requests yet');
    }
}
