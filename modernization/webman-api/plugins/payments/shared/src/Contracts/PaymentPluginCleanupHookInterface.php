<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Contracts;

interface PaymentPluginCleanupHookInterface
{
    public function cleanup(string $mode, array $context = []): array;
}
