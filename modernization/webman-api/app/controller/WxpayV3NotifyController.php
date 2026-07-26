<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\support\ApiResponse;
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
            return $this->failure('请求方式必须为 POST', 405);
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
            return $this->failure('系统暂时处理失败', 500);
        }
    }

    private function failure(string $message, int $status): Response
    {
        return json([
            'code' => 'FAIL',
            'message' => ApiResponse::normalizeText($message),
        ], JSON_UNESCAPED_SLASHES)->withStatus($status);
    }
}
