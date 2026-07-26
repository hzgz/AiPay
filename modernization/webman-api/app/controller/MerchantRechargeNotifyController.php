<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\service\MerchantRechargeService;
use app\support\FrontendUrlBuilder;
use Webman\Http\Request;
use Webman\Http\Response;

class MerchantRechargeNotifyController
{
    public function notify(Request $request): Response
    {
        return $this->respond($request, (new MerchantRechargeService())->handleRechargeCallback($request, 'notify'));
    }

    public function return(Request $request): Response
    {
        return $this->respond($request, (new MerchantRechargeService())->handleRechargeCallback($request, 'return'));
    }

    private function respond(Request $request, array $result): Response
    {
        $kind = strtolower(trim((string)($result['kind'] ?? 'text')));

        return match ($kind) {
            'redirect' => redirect($this->resolveRedirectLocation($request, (string)($result['location'] ?? ''))),
            'xml' => response((string)($result['body'] ?? ''), 200, [
                'Content-Type' => 'application/xml; charset=utf-8',
            ]),
            default => response((string)($result['body'] ?? ''), 200, [
                'Content-Type' => (string)($result['content_type'] ?? 'text/plain; charset=utf-8'),
            ]),
        };
    }

    private function resolveRedirectLocation(Request $request, string $location): string
    {
        $location = trim($location);
        if ($location === '') {
            return FrontendUrlBuilder::merchantUrl($request, '/merchant/recharges');
        }

        if (preg_match('#^(https?:)?//#i', $location) === 1) {
            return $location;
        }

        $path = strtolower('/' . ltrim((string)(parse_url($location, PHP_URL_PATH) ?: $location), '/'));
        if ($path === '/deal/recharge') {
            return FrontendUrlBuilder::merchantUrl($request, '/merchant/recharges');
        }

        return $location;
    }
}
