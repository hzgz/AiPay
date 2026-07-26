<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Shared\Support;

use RuntimeException;

class PaymentPluginException extends RuntimeException
{
    public static function validation(string $message, int $code = 422): self
    {
        return new self($message, $code);
    }

    public static function unauthorized(?string $message = null, int $code = 401): self
    {
        return new self($message ?? PaymentErrorMessageCatalog::signatureInvalid(), $code);
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
