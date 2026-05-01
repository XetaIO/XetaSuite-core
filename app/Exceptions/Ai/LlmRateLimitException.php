<?php

declare(strict_types=1);

namespace XetaSuite\Exceptions\Ai;

use RuntimeException;

class LlmRateLimitException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('AI rate limit reached. Please wait before sending another request.');
    }
}
