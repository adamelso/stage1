<?php

namespace App\CoreBundle\Provider;

/**
 * App\CoreBundle\Provider\AbstractPayload
 */
abstract class AbstractPayload implements PayloadInterface
{
    /**
     * @var string
     */
    protected $raw;

    /**
     * @var array
     */
    protected $parsed;

    /**
     * @param string $raw
     */
    public function __construct($raw)
    {
        $this->raw = $raw;
        $this->parsed = json_decode($raw, true);
    }

    /**
     * @return string
     */
    public function getRawContent()
    {
        return $this->raw;
    }
}