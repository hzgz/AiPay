<?php

declare(strict_types=1);

namespace Plugins\Payments\WxpayV3\Support;

final class WxpayV3Crypto
{
    public static function verifySignature(
        string $publicKey,
        string $timestamp,
        string $nonce,
        string $body,
        string $signature
    ): bool {
        $publicPem = self::formatPublicKey($publicKey);
        $signatureBytes = base64_decode(trim($signature), true);
        if (
            $publicPem === ''
            || $timestamp === ''
            || $nonce === ''
            || $body === ''
            || $signatureBytes === false
        ) {
            return false;
        }

        $key = @openssl_pkey_get_public($publicPem);
        if ($key === false) {
            return false;
        }

        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        return @openssl_verify($message, $signatureBytes, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    public static function decryptResource(
        string $apiV3Key,
        string $ciphertext,
        string $nonce,
        string $associatedData = ''
    ): string|false {
        $apiV3Key = trim($apiV3Key);
        $nonce = trim($nonce);
        if (strlen($apiV3Key) !== 32 || strlen($nonce) !== 12) {
            return false;
        }

        $cipherBytes = base64_decode(trim($ciphertext), true);
        if ($cipherBytes === false || strlen($cipherBytes) <= 16) {
            return false;
        }

        $authenticationTag = substr($cipherBytes, -16);
        $encryptedPayload = substr($cipherBytes, 0, -16);

        return openssl_decrypt(
            $encryptedPayload,
            'aes-256-gcm',
            $apiV3Key,
            OPENSSL_RAW_DATA,
            $nonce,
            $authenticationTag,
            $associatedData
        );
    }

    public static function formatPublicKey(string $publicKey): string
    {
        $publicKey = trim(str_replace(
            ['\\r\\n', '\\n', "\r\n", "\r"],
            ["\n", "\n", "\n", "\n"],
            $publicKey
        ));
        if ($publicKey === '') {
            return '';
        }

        if (str_contains($publicKey, '-----BEGIN')) {
            return $publicKey . (str_ends_with($publicKey, "\n") ? '' : "\n");
        }

        $body = preg_replace('/\s+/', '', $publicKey);
        if (!is_string($body) || $body === '' || base64_decode($body, true) === false) {
            return '';
        }

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($body, 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }
}
