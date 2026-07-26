<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\support\RequestPayload;
use Plugins\Payments\AlipayOfficial\Support\AlipayOfficialNotifySupport;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

final class AlipayNotifyController
{
    public function notify(Request $request): Response
    {
        try {
            $payload = RequestPayload::all($request);
            if ($payload === []) {
                $query = $request->get();
                $payload = is_array($query) ? $query : [];
            }

            (new AlipayOfficialNotifySupport())->handle($payload);

            return $this->plainText('success');
        } catch (Throwable $exception) {
            error_log('[alipay_notify] ' . $exception->getMessage());

            return $this->plainText('fail');
        }
    }

    private function plainText(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
