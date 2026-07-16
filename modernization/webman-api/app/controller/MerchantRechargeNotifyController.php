<?php

declare(strict_types=1);

namespace app\controller;

use app\service\MerchantRechargeService;
use Webman\Http\Request;
use Webman\Http\Response;

class MerchantRechargeNotifyController
{
    public function notify(Request $request): Response
    {
        return $this->respond((new MerchantRechargeService())->handleRechargeCallback($request, 'notify'));
    }

    public function return(Request $request): Response
    {
        return $this->respond((new MerchantRechargeService())->handleRechargeCallback($request, 'return'));
    }

    private function respond(array $result): Response
    {
        $kind = strtolower(trim((string)($result['kind'] ?? 'text')));

        return match ($kind) {
            'redirect' => redirect((string)($result['location'] ?? '/Deal/Recharge')),
            'xml' => response((string)($result['body'] ?? ''), 200, [
                'Content-Type' => 'application/xml; charset=utf-8',
            ]),
            default => response((string)($result['body'] ?? ''), 200, [
                'Content-Type' => (string)($result['content_type'] ?? 'text/plain; charset=utf-8'),
            ]),
        };
    }
}
