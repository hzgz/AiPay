<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

use PHPMailer\PHPMailer\PHPMailer;

class AdminSmtpMailer
{
    public function configurationSummary(array $config): array
    {
        $host = trim((string)($config['smtp-host'] ?? ''));
        $port = $this->normalizePort($config['smtp-port'] ?? null);
        $fromAddress = trim((string)($config['smtp-user'] ?? ''));
        $password = trim((string)($config['smtp-pass'] ?? ''));
        $fromName = trim((string)($config['sitename'] ?? ''));
        $secure = $this->normalizeSecure($config['SmtpSecure'] ?? null);
        $enabled = (string)($config['email_switch'] ?? '0') === '1';
        $configured = $host !== '' && $port !== null && $fromAddress !== '' && $password !== '';

        return [
            'enabled' => $enabled,
            'configured' => $configured,
            'ready' => $enabled && $configured,
            'host' => $host,
            'port' => $port,
            'secure' => $secure,
            'from_address' => $fromAddress,
            'from_name' => $fromName !== '' ? $fromName : 'AiPay',
        ];
    }

    public function assertReady(array $config): array
    {
        $summary = $this->configurationSummary($config);

        if (!$summary['enabled']) {
            throw new \InvalidArgumentException('system email switch is disabled');
        }

        if (!$summary['configured']) {
            throw new \InvalidArgumentException('smtp configuration is incomplete');
        }

        return $summary;
    }

    public function sendHtml(string $recipient, string $title, string $content, array $config): void
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('recipient email format is invalid');
        }

        $summary = $this->assertReady($config);
        $mail = new PHPMailer(true);

        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;
        $mail->isSMTP();
        $mail->Host = (string)$summary['host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string)$summary['from_address'];
        $mail->Password = (string)($config['smtp-pass'] ?? '');
        $mail->Port = (int)$summary['port'];
        $mail->setFrom((string)$summary['from_address'], (string)$summary['from_name']);
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $this->subject((string)$summary['from_name'], $title);
        $mail->Body = $content;

        $secure = (string)$summary['secure'];
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = false;
        }

        $mail->send();
    }

    private function subject(string $fromName, string $title): string
    {
        $title = trim($title);
        if ($fromName === '') {
            return $title;
        }

        return $fromName . '-' . $title;
    }

    private function normalizeSecure(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            'ssl', 'smtps' => 'ssl',
            'tls', 'starttls' => 'tls',
            default => 'none',
        };
    }

    private function normalizePort(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $port = (int)$value;

        if ($port < 1 || $port > 65535) {
            return null;
        }

        return $port;
    }
}
