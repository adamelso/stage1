<?php

namespace App\Model;

class BuildLog
{
    protected $id;

    protected $message;

    protected $microtime;

    protected $createdAt;

    protected $updatedAt;

    protected $type;

    protected $stream;

    /**
     * @var \App\Model\Build
     */
    private $build;

    public function asMessage()
    {
        return [
            'message' => $this->getMessage(),
            'type' => $this->getType(),
            'stream' => $this->getStream(),
            'microtime' => $this->getMicrotime(),
            'build' => $this->getBuild()->asMessage(),
        ];
    }

    public function __toString()
    {
        return $this->getMessage();
    }

    /**
     * Set message
     *
     * @param  string   $message
     * @return BuildLog
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
     * Set createdAt
     *
     * @param  \DateTime $createdAt
     * @return BuildLog
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
     * @return BuildLog
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
     * Set build
     *
     * @param  \App\Model\Build $build
     * @return BuildLog
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
     * Set type
     *
     * @param  string   $type
     * @return BuildLog
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set stream
     *
     * @param  string   $stream
     * @return BuildLog
     */
    public function setStream($stream)
    {
        $this->stream = $stream;

        return $this;
    }

    /**
     * Get stream
     *
     * @return string
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Set microtime
     *
     * @param  string   $microtime
     * @return BuildLog
     */
    public function setMicrotime($microtime)
    {
        $this->microtime = $microtime;

        return $this;
    }

    /**
     * Get microtime
     *
     * @return string
     */
    public function getMicrotime()
    {
        return $this->microtime;
    }
}
