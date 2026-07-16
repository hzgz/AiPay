<?php

declare(strict_types=1);

namespace Plugins\Payments\WxpayV3\Support;

use RuntimeException;
use Throwable;

final class WxpayV3NotifyException extends RuntimeException
{
    public function __construct(
        private readonly string $reason,
        string $message,
        private readonly int $httpStatus = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
