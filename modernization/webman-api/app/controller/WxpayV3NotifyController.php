<?php

declare(strict_types=1);

namespace app\controller;

use Plugins\Payments\WxpayV3\Support\WxpayV3NotifyException;
use Plugins\Payments\WxpayV3\Support\WxpayV3NotifySupport;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

final class WxpayV3NotifyController
{
    public function notify(Request $request): Response
    {
        if (strtoupper($request->method()) !== 'POST') {
            return $this->failure('POST is required', 405);
        }

        try {
            (new WxpayV3NotifySupport())->handle($request->rawBody(), [
                'Wechatpay-Timestamp' => $request->header('Wechatpay-Timestamp', ''),
                'Wechatpay-Nonce' => $request->header('Wechatpay-Nonce', ''),
                'Wechatpay-Signature' => $request->header('Wechatpay-Signature', ''),
                'Wechatpay-Serial' => $request->header('Wechatpay-Serial', ''),
            ]);

            return json([
                'code' => 'SUCCESS',
                'message' => 'ok',
            ], JSON_UNESCAPED_SLASHES);
        } catch (WxpayV3NotifyException $exception) {
            return $this->failure($exception->getMessage(), $exception->httpStatus());
        } catch (Throwable) {
            return $this->failure('temporary processing failure', 500);
        }
    }

    private function failure(string $message, int $status): Response
    {
        return json([
            'code' => 'FAIL',
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES)->withStatus($status);
    }
}
