<?php

namespace App\CoreBundle\Provider\GitHub;

use App\CoreBundle\Provider\AbstractPayload;

class Payload extends AbstractPayload
{
    /**
     * @param string $raw
     * @param string $repositoryId
     * @param string $event
     */
    public function __construct($raw, $deliveryId, $event)
    {
        $this->deliveryId = $deliveryId;
        $this->event = $event;
        
        parent::__construct($raw);
    }

    /**
     * @return string
     * 
     * @todo use ProviderInterface#getName
     */
    public function getProviderName()
    {
        return 'github';
    }

    /**
     * @return boolean
     */
    public function isPullRequest()
    {
        return isset($this->parsed['pull_request']);
    }

    /**
     * @param string
     */
    public function getRepositoryFullName()
    {
        return $this->parsed['repository']['full_name'];
    }

    /**
     * @return integer
     */
    public function getPullRequestNumber()
    {
        if (!$this->isPullRequest()) {
            throw new RuntimeException('Called PR specific method on a non-PR payload');
        }

        return $this->parsed['pull_request']['number'];
    }

    /**
     * @return string
     */
    public function isBuildable()
    {
        if ($this->isPullRequest()) {
            return in_array($this->parsed['action'], ['opened', 'synchronize']);
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function hasRef()
    {
        return $this->isPullRequest() || isset($this->parsed['ref']);
    }

    /**
     * @return string
     */
    public function getRef()
    {
        return $this->isPullRequest()
            ? sprintf('pull/%d/head', $this->getPullRequestNumber())
            : substr($this->parsed['ref'], 11);
    }

    /**
     * @return string
     * 
     * @todo check if this is implementable for a PR
     */
    public function getHash()
    {
        if ($this->isPullRequest()) {
            throw new RuntimeException('Called branch specific method on a non-branch payload');
        }
        
        return $this->parsed['after'];
    }

    /**
     * @return boolean
     */
    public function isDummy()
    {
        return isset($this->parsed['zen']);
    }

    /**
     * @return mixed
     */
    public function getRepositoryId()
    {
        return $this->parsed['repository']['id'];
    }

    /**
     * @return mixed
     */
    public function getDeliveryId()
    {
        return $this->deliveryId;
    }

    /**
     * @return string
     */
    public function getEvent()
    {
        return $this->event;
        
    }
}