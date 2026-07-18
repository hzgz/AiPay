<?php

declare(strict_types=1);

namespace app\support;

use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use Overtrue\EasySms\Strategies\OrderStrategy;

class MerchantSmsCodeSender
{
    public function configurationSummary(array $config): array
    {
        $provider = $this->normalizeProvider($config['smstype'] ?? null);
        $packageInstalled = class_exists(EasySms::class);
        $enabled = (string)($config['code_switch'] ?? '0') === '1';
        $configured = $provider !== '' && $this->providerConfigured($provider, $config);

        return [
            'enabled' => $enabled,
            'provider' => $provider,
            'package_installed' => $packageInstalled,
            'configured' => $configured,
            'ready' => $enabled && $packageInstalled && $configured,
        ];
    }

    public function assertReady(array $config): array
    {
        $summary = $this->configurationSummary($config);

        if (!$summary['enabled']) {
            throw new \InvalidArgumentException('系统未开启短信验证功能');
        }

        if (!$summary['package_installed']) {
            throw new \InvalidArgumentException('当前环境未安装短信发送依赖');
        }

        if (($summary['provider'] ?? '') === '') {
            throw new \InvalidArgumentException('暂不支持当前短信服务商配置');
        }

        if (!$summary['configured']) {
            throw new \InvalidArgumentException('短信服务商配置不完整');
        }

        return $summary;
    }

    public function sendCode(string $mobile, string $code, array $config): void
    {
        if (!preg_match('/^1\d{10}$/', $mobile)) {
            throw new \InvalidArgumentException('手机号格式不正确');
        }

        $summary = $this->assertReady($config);
        $provider = (string)$summary['provider'];

        $easySms = new EasySms([
            'timeout' => 10.0,
            'default' => [
                'strategy' => OrderStrategy::class,
                'gateways' => [$provider],
            ],
            'gateways' => [
                'aliyun' => [
                    'access_key_id' => (string)($config['alisms-accessKeyId'] ?? ''),
                    'access_key_secret' => (string)($config['alisms-Secret'] ?? ''),
                    'sign_name' => (string)($config['alisms-SignName'] ?? ''),
                ],
                'qcloud' => [
                    'sdk_app_id' => (string)($config['tensms-AppId'] ?? ''),
                    'secret_id' => (string)($config['tensms-accessKeyId'] ?? ''),
                    'secret_key' => (string)($config['tensms-Secret'] ?? ''),
                    'sign_name' => (string)($config['tensms-SignName'] ?? ''),
                ],
                'smsbao' => [
                    'user' => (string)($config['smsbao-user'] ?? ''),
                    'password' => (string)($config['smsbao-pass'] ?? ''),
                ],
            ],
        ]);

        $message = match ($provider) {
            'smsbao' => [
                'content' => sprintf(
                    '【%s】您的验证码是 %s，5 分钟内有效。',
                    trim((string)($config['smsbao-SignName'] ?? 'AiPay')),
                    $code
                ),
            ],
            'qcloud' => [
                'template' => (string)($config['tensms-LoginCodeId'] ?? ''),
                'data' => [
                    'code' => $code,
                ],
            ],
            default => [
                'template' => (string)($config['alisms-LoginCodeId'] ?? ''),
                'data' => [
                    'code' => $code,
                ],
            ],
        };

        try {
            $result = $easySms->send($mobile, $message);
        } catch (NoGatewayAvailableException $exception) {
            throw new \RuntimeException('短信验证码发送失败', 0, $exception);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('短信验证码发送失败', 0, $exception);
        }

        if (($result[$provider]['status'] ?? '') !== 'success') {
            throw new \RuntimeException('短信验证码发送失败');
        }
    }

    private function normalizeProvider(mixed $value): string
    {
        return match (strtolower(trim((string)$value))) {
            'aliyun', 'alisms' => 'aliyun',
            'qcloud', 'tensms' => 'qcloud',
            'smsbao' => 'smsbao',
            default => '',
        };
    }

    private function providerConfigured(string $provider, array $config): bool
    {
        return match ($provider) {
            'aliyun' => $this->allPresent([
                $config['alisms-accessKeyId'] ?? null,
                $config['alisms-Secret'] ?? null,
                $config['alisms-SignName'] ?? null,
                $config['alisms-LoginCodeId'] ?? null,
            ]),
            'qcloud' => $this->allPresent([
                $config['tensms-AppId'] ?? null,
                $config['tensms-accessKeyId'] ?? null,
                $config['tensms-Secret'] ?? null,
                $config['tensms-SignName'] ?? null,
                $config['tensms-LoginCodeId'] ?? null,
            ]),
            'smsbao' => $this->allPresent([
                $config['smsbao-user'] ?? null,
                $config['smsbao-pass'] ?? null,
                $config['smsbao-SignName'] ?? null,
            ]),
            default => false,
        };
    }

    private function allPresent(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string)$value) === '') {
                return false;
            }
        }

        return true;
    }
}
