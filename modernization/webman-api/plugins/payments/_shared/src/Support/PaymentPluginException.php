<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

use RuntimeException;

class PaymentPluginException extends RuntimeException
{
    public static function validation(string $message, int $code = 422): self
    {
        return new self($message, $code);
    }

    public static function unauthorized(string $message = 'signature verification failed', int $code = 401): self
    {
        return new self($message, $code);
    }

    public static function notFound(string $message, int $code = 404): self
    {
        return new self($message, $code);
    }

    public static function conflict(string $message, int $code = 409): self
    {
        return new self($message, $code);
    }
}
