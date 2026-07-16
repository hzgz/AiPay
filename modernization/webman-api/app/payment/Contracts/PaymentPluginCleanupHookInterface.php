<?php

namespace app\payment\Contracts;

interface PaymentPluginCleanupHookInterface
{
    public function cleanup(string $mode, array $context = []): array;
}
