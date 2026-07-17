<?php

declare(strict_types=1);

namespace app\payment;

use Plugins\Payments\Shared\Support\PaymentPluginAutoloader as SharedPaymentPluginAutoloader;

final class PaymentPluginAutoloader
{
    public static function register(): void
    {
        require_once base_path('plugins/payments/_shared/src/Support/PaymentPluginAutoloader.php');
        SharedPaymentPluginAutoloader::register();
    }
}
