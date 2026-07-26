<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\service\payment\PaymentPluginManager;
use app\support\RequestPayload;
use InvalidArgumentException;
use Plugins\Payments\Shared\Support\EpayProtocolNotifyBridgeSupport;
use Plugins\Payments\Shared\Support\PaymentPluginException;
use RuntimeException;
use support\Log;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

class PaymentNotifyController
{
    public function epayNotifyzj(Request $request): Response
    {
        return $this->handleLegacyNotify($request, 'notify', 'epay_notifyzj');
    }

    public function epayReturnzj(Request $request): Response
    {
        return $this->handleLegacyNotify($request, 'return', 'epay_returnzj');
    }

    public function universalEpayNotify(Request $request): Response
    {
        return $this->handlePluginNotify($request, 'universal_epay', 'notify', 'universal_epay_notify');
    }

    public function universalEpayReturn(Request $request): Response
    {
        return $this->handlePluginNotify($request, 'universal_epay', 'return', 'universal_epay_return');
    }

    public function leshuaNotify(Request $request): Response
    {
        return $this->handlePluginNotify($request, 'leshua', 'notify', 'leshua_notify');
    }

    private function handleLegacyNotify(Request $request, string $mode, string $entry): Response
    {
        return $this->dispatchPluginNotify(
            $request,
            $mode,
            $entry,
            $this->resolveLegacyNotifyPluginSelection()
        );
    }

    private function handlePluginNotify(
        Request $request,
        string $pluginCode,
        string $mode,
        string $entry
    ): Response {
        return $this->dispatchPluginNotify(
            $request,
            $mode,
            $entry,
            $this->resolvePluginNotifySelection($pluginCode)
        );
    }

    /**
     * @param array<string, mixed> $pluginSelection
     */
    private function dispatchPluginNotify(
        Request $request,
        string $mode,
        string $entry,
        array $pluginSelection
    ): Response {
        try {
            $plugin = $this->pluginInstance((array)$pluginSelection['detail']['manifest']);
            $result = $plugin->handleNotify([
                'mode' => $mode,
                'entry' => $entry,
                'plugin' => $pluginSelection['code'],
                'plugin_resolution' => $pluginSelection['resolution'],
                'plugin_availability' => $pluginSelection['availability'],
                'plugin_state' => $pluginSelection['detail']['state'],
                'security' => $this->securityContext($pluginSelection),
                'payload' => $this->payload($request),
                'raw_body' => (string)$request->rawBody(),
                'query' => $request->get(),
                'headers' => [
                    'content_type' => (string)$request->header('content-type', ''),
                    'user_agent' => (string)$request->header('user-agent', ''),
                ],
            ]);

            if ($mode === 'notify') {
                $body = $result['notify_response'] ?? (($result['verified'] ?? false) ? 'success' : 'fail');

                return response((string)$body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            }

            if (!empty($result['return_redirect'])) {
                return redirect((string)$result['return_redirect']);
            }

            $body = $result['return_response'] ?? '回调已接收';

            return response((string)$body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        } catch (Throwable $exception) {
            $this->logNotifyFailure($request, $mode, $entry, $pluginSelection, $exception);

            return response(
                $this->fallbackBody($exception, $mode),
                200,
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        }
    }

    /**
     * @return array{
     *     code: string,
     *     resolution: string,
     *     availability: string,
     *     detail: array<string, mixed>
     * }
     */
    private function resolveLegacyNotifyPluginSelection(): array
    {
        $detail = $this->assertNotifyCapablePlugin(EpayProtocolNotifyBridgeSupport::PLUGIN_CODE);
        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        $enabled = (bool)($state['enabled'] ?? false);

        return [
            'code' => EpayProtocolNotifyBridgeSupport::PLUGIN_CODE,
            'resolution' => EpayProtocolNotifyBridgeSupport::resolution($enabled),
            'availability' => EpayProtocolNotifyBridgeSupport::availability($enabled),
            'detail' => $detail,
        ];
    }

    /**
     * @return array{
     *     code: string,
     *     resolution: string,
     *     availability: string,
     *     detail: array<string, mixed>
     * }
     */
    private function resolvePluginNotifySelection(string $pluginCode): array
    {
        $detail = $this->assertNotifyCapablePlugin($pluginCode);
        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        $enabled = (bool)($state['enabled'] ?? false);

        return [
            'code' => $pluginCode,
            'resolution' => 'plugin_manifest_direct',
            'availability' => $enabled ? 'installed_enabled' : 'installed_disabled',
            'detail' => $detail,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assertNotifyCapablePlugin(string $pluginCode): array
    {
        $detail = $this->manager()->detail($pluginCode);
        $manifest = is_array($detail['manifest'] ?? null) ? $detail['manifest'] : [];
        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        $capabilities = array_map(
            static fn (mixed $value): string => strtolower(trim((string)$value)),
            is_array($manifest['capabilities'] ?? null) ? $manifest['capabilities'] : []
        );

        if (!in_array('notify', $capabilities, true)) {
            throw new InvalidArgumentException(sprintf('支付插件[%s]不支持回调处理', $pluginCode));
        }

        if (!(bool)($state['installed'] ?? false)) {
            throw new InvalidArgumentException(sprintf('支付插件[%s]尚未安装', $pluginCode));
        }

        return $detail;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function pluginInstance(array $manifest): object
    {
        $entry = (string)($manifest['entry'] ?? '');
        $entryPath = base_path($entry);
        if ($entry === '' || !is_file($entryPath)) {
            throw new RuntimeException('插件入口文件不存在：' . $entry);
        }

        require_once $entryPath;

        $className = (string)($manifest['class'] ?? '');
        if ($className === '' || !class_exists($className)) {
            throw new RuntimeException('插件类不存在：' . $className);
        }

        return new $className();
    }

    private function manager(): PaymentPluginManager
    {
        return new PaymentPluginManager();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = RequestPayload::all($request);
        if ($payload !== []) {
            return $payload;
        }

        $query = $request->get();

        return is_array($query) ? $query : [];
    }

    /**
     * @param array<string, mixed> $selection
     * @return array<string, mixed>
     */
    private function securityContext(array $selection): array
    {
        if ((string)($selection['code'] ?? '') === EpayProtocolNotifyBridgeSupport::PLUGIN_CODE) {
            return EpayProtocolNotifyBridgeSupport::securityContext(
                (string)$selection['code'],
                (string)$selection['availability']
            );
        }

        return [
            'plugin' => (string)($selection['code'] ?? ''),
            'resolution' => (string)($selection['resolution'] ?? ''),
            'availability' => (string)($selection['availability'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $selection
     */
    private function logNotifyFailure(
        Request $request,
        string $mode,
        string $entry,
        array $selection,
        Throwable $exception
    ): void {
        if ($mode !== 'notify') {
            return;
        }

        $payload = $this->payload($request);
        $safePayload = [];
        $safeKeys = [
            'third_order_id',
            'leshua_order_id',
            'out_trade_no',
            'trade_no',
            'merchant_id',
            'amount',
            'money',
            'status',
            'resp_code',
            'result_code',
            'pay_way',
        ];
        foreach ($payload as $key => $value) {
            if (!in_array((string)$key, $safeKeys, true)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safePayload[$key] = $value;
            }
        }

        Log::warning('payment_notify_failed', [
            'plugin' => (string)($selection['code'] ?? ''),
            'entry' => $entry,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'payload_keys' => array_keys($payload),
            'payload' => $safePayload,
            'content_type' => (string)$request->header('content-type', ''),
            'user_agent' => (string)$request->header('user-agent', ''),
        ]);
    }

    private function fallbackBody(Throwable $exception, string $mode): string
    {
        if (
            $exception instanceof PaymentPluginException
            || $exception instanceof InvalidArgumentException
            || $exception instanceof RuntimeException
        ) {
            return $mode === 'notify' ? 'fail' : $exception->getMessage();
        }

        return $mode === 'notify' ? 'fail' : '回调处理失败';
    }
}
