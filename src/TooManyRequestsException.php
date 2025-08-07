<?php

namespace Ryantxr\GooSheets;

class TooManyRequestsException extends \RuntimeException
{
    private ?int $retryAfter;

    public function __construct(?int $retryAfter = null, ?\Throwable $previous = null)
    {
        $this->retryAfter = $retryAfter;
        parent::__construct('Too Many Requests', 429, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
