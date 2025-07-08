<?php

namespace FormatD\Mailer\Traits;

/*
 * This file is part of the FormatD.Mailer package.
 */

trait InterceptionTrait
{
    /**
     * @var boolean
     */
    protected $intercepted = false;

    /**
     * @return bool
     */
    public function isIntercepted(): bool
    {
        return $this->intercepted;
    }

    /**
     * @param bool $intercepted
     */
    public function setIntercepted(bool $intercepted): void
    {
        $this->intercepted = $intercepted;
    }
}