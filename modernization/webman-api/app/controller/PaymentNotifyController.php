<?php

namespace app\controller;

use app\payment\PaymentPluginException;
use app\payment\PaymentPluginManager;
use app\support\RequestPayload;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

class PaymentNotifyController
{
    private const LEGACY_NOTIFY_PLUGIN = 'legacy_epay';

    public function epayNotifyzj(Request $request): Response
    {
        return $this->handleLegacyEpayNotify($request, 'notify');
    }

    public function epayReturnzj(Request $request): Response
    {
        return $this->handleLegacyEpayNotify($request, 'return');
    }

    private function handleLegacyEpayNotify(Request $request, string $mode): Response
    {
        try {
            $pluginSelection = $this->resolveLegacyNotifyPluginSelection();
            $plugin = $this->pluginInstance($pluginSelection['detail']['manifest']);
            $payload = $this->payload($request);
            $result = $plugin->handleNotify([
                'mode' => $mode,
                'entry' => $mode === 'notify' ? 'epay_notifyzj' : 'epay_returnzj',
                'plugin' => $pluginSelection['code'],
                'plugin_resolution' => $pluginSelection['resolution'],
                'plugin_availability' => $pluginSelection['availability'],
                'plugin_state' => $pluginSelection['detail']['state'],
                'security' => $this->legacyNotifySecurityContext($pluginSelection),
                'payload' => $payload,
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

            $body = $result['return_response'] ?? 'return received';
            return response((string)$body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        } catch (Throwable $exception) {
            $body = $this->fallbackBody($exception, $mode);
            return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
    }

    private function resolveLegacyNotifyPluginSelection(): array
    {
        $detail = $this->assertLegacyNotifyCapablePlugin(self::LEGACY_NOTIFY_PLUGIN);
        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        $enabled = (bool)($state['enabled'] ?? false);

        return [
            'code' => self::LEGACY_NOTIFY_PLUGIN,
            'resolution' => $enabled
                ? 'fixed_compatibility_binding'
                : 'fixed_compatibility_binding_drain_mode',
            'availability' => $enabled ? 'enabled' : 'drain_only',
            'detail' => $detail,
        ];
    }

    private function assertLegacyNotifyCapablePlugin(string $pluginCode): array
    {
        $detail = $this->manager()->detail($pluginCode);
        $manifest = is_array($detail['manifest'] ?? null) ? $detail['manifest'] : [];
        $state = is_array($detail['state'] ?? null) ? $detail['state'] : [];
        $capabilities = array_map(
            static fn (mixed $value): string => strtolower(trim((string)$value)),
            is_array($manifest['capabilities'] ?? null) ? $manifest['capabilities'] : []
        );

        if (!in_array('notify', $capabilities, true)) {
            throw new InvalidArgumentException("payment plugin [$pluginCode] does not support notify callbacks");
        }

        if (!(bool)($state['installed'] ?? false)) {
            throw new InvalidArgumentException("payment plugin [$pluginCode] is not installed");
        }

        return $detail;
    }

    private function pluginInstance(array $manifest): object
    {
        $entryPath = base_path($manifest['entry']);
        if (!is_file($entryPath)) {
            throw new RuntimeException('plugin entry file was not found: ' . $manifest['entry']);
        }

        require_once $entryPath;

        $className = (string)$manifest['class'];
        if ($className === '' || !class_exists($className)) {
            throw new RuntimeException('plugin class was not found: ' . $className);
        }

        return new $className();
    }

    private function manager(): PaymentPluginManager
    {
        return new PaymentPluginManager();
    }

    private function payload(Request $request): array
    {
        $payload = RequestPayload::all($request);
        if (!empty($payload)) {
            return $payload;
        }

        $query = $request->get();
        return is_array($query) ? $query : [];
    }

    private function legacyNotifySecurityContext(array $pluginSelection): array
    {
        return [
            'scope' => 'legacy_epay_notify_compatibility',
            'plugin' => (string)($pluginSelection['code'] ?? self::LEGACY_NOTIFY_PLUGIN),
            'availability' => (string)($pluginSelection['availability'] ?? 'enabled'),
            'signature' => [
                'algorithm' => 'md5',
                'field' => 'sign',
                'secret_source' => 'upstream_paylist.key',
            ],
            'replay_protection' => [
                'strategy' => 'settlement_idempotency',
                'window_seconds' => null,
                'duplicate_response' => 'success_or_return_redirect',
            ],
        ];
    }

    private function fallbackBody(Throwable $exception, string $mode): string
    {
        if ($exception instanceof PaymentPluginException) {
            return $mode === 'notify' ? 'fail' : $exception->getMessage();
        }

        if ($exception instanceof InvalidArgumentException) {
            return $mode === 'notify' ? 'fail' : $exception->getMessage();
        }

        if ($exception instanceof RuntimeException) {
            return $mode === 'notify' ? 'fail' : $exception->getMessage();
        }

        return $mode === 'notify' ? 'fail' : 'notify migration error';
    }
}
