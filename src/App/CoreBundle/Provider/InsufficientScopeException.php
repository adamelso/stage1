<?php

namespace App\CoreBundle\Provider;

/**
 * App\CoreBundle\Provider\InsufficientScopeException
 */
class InsufficientScopeException extends Exception
{
    /**
     * @var string
     */
    private $expected;

    /**
     * @var array
     */
    private $actuals;

    /**
     * @param string $expected
     * @param string $actual
     */
    public function __construct($expected, $actuals)
    {
        $this->expected = $expected;
        $this->actuals = $actuals;

        parent::__construct(sprintf('Expected scope "%s", only has "%s"', $expected, implode(', ', $actuals)));
    }

    /**
     * @return string
     */
    public function getExpectedScope()
    {
        return $this->expected;
    }

    /**
     * @return array
     */
    public function getActualScopes()
    {
        return $this->actuals;
    }
}