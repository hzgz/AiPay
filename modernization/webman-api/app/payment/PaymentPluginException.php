<?php

declare(strict_types=1);

namespace app\payment;

use Plugins\Payments\Shared\Support\PaymentPluginException as SharedPaymentPluginException;

class PaymentPluginException extends SharedPaymentPluginException
{
}
