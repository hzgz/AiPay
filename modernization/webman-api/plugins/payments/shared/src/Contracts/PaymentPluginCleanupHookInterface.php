<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace Plugins\Payments\Shared\Contracts;

interface PaymentPluginCleanupHookInterface
{
    public function cleanup(string $mode, array $context = []): array;
}
