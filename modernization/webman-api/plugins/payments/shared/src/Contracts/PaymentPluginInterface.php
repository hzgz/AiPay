<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared\Contracts;

interface PaymentPluginInterface
{
    public function code(): string;

    public function install(): void;

    public function upgrade(string $fromVersion, string $toVersion): void;

    public function uninstall(bool $purge = false): void;

    public function configSchema(): array;

    public function createOrder(array $payload): array;

    public function query(string $orderNo): array;

    public function refund(array $payload): array;

    public function handleNotify(array $payload): array;
}
