<?php

namespace App\Model;

/**
 * Feedback
 */
class Feedback
{
    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $message;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \App\Model\Project
     */
    private $project;

    /**
     * @var \App\Model\Build
     */
    private $build;

    /**
     * @var \App\Model\Project
     */
    private $user;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $url;

    /**
     * Set email
     *
     * @param  string   $email
     * @return Feedback
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set message
     *
     * @param  string   $message
     * @return Feedback
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set project
     *
     * @param  \App\Model\Project $project
     * @return Feedback
     */
    public function setProject(\App\Model\Project $project = null)
    {
        $this->project = $project;

        return $this;
    }

    /**
     * Get project
     *
     * @return \App\Model\Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * Set build
     *
     * @param  \App\Model\Build $build
     * @return Feedback
     */
    public function setBuild(\App\Model\Build $build = null)
    {
        $this->build = $build;

        return $this;
    }

    /**
     * Get build
     *
     * @return \App\Model\Build
     */
    public function getBuild()
    {
        return $this->build;
    }

    /**
     * Set user
     *
     * @param  \App\Model\User $user
     * @return Feedback
     */
    public function setUser(\App\Model\User $user = null)
    {
        $this->user = $user;
        $this->setEmail($user->getEmail());

        return $this;
    }

    /**
     * Get user
     *
     * @return Project
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set token
     *
     * @param  string   $token
     * @return Feedback
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get token
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set url
     *
     * @param  string   $url
     * @return Feedback
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * Set createdAt
     *
     * @param  \DateTime $createdAt
     * @return Feedback
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param  \DateTime $updatedAt
     * @return Feedback
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
