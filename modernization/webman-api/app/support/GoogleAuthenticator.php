<?php

namespace app\support;

use RuntimeException;

class GoogleAuthenticator
{
    protected int $codeLength = 6;

    public function createSecret(int $secretLength = 16): string
    {
        $validChars = $this->getBase32LookupTable();

        if ($secretLength < 16 || $secretLength > 128) {
            throw new RuntimeException('Bad secret length');
        }

        $random = random_bytes($secretLength);
        $secret = '';
        for ($i = 0; $i < $secretLength; ++$i) {
            $secret .= $validChars[ord($random[$i]) & 31];
        }

        return $secret;
    }

    public function getCode(string $secret, ?int $timeSlice = null): string
    {
        $timeSlice ??= (int)floor(time() / 30);
        $secretKey = $this->base32Decode($secret);

        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $hashPart = substr($hash, $offset, 4);

        $value = unpack('N', $hashPart);
        $value = ($value[1] ?? 0) & 0x7FFFFFFF;
        $modulo = (int)pow(10, $this->codeLength);

        return str_pad((string)($value % $modulo), $this->codeLength, '0', STR_PAD_LEFT);
    }

    public function buildOtpAuthUrl(string $name, string $secret, ?string $issuer = null): string
    {
        $label = rawurlencode($name);
        $url = 'otpauth://totp/' . $label . '?secret=' . $secret;
        if ($issuer !== null && trim($issuer) !== '') {
            $url .= '&issuer=' . rawurlencode(trim($issuer));
        }

        return $url;
    }

    public function getQRCodeGoogleUrl(string $name, string $secret, ?string $issuer = null, array $params = []): string
    {
        $width = !empty($params['width']) && (int)$params['width'] > 0 ? (int)$params['width'] : 200;
        $height = !empty($params['height']) && (int)$params['height'] > 0 ? (int)$params['height'] : 200;
        $size = max($width, $height);
        $otpAuthUrl = $this->buildOtpAuthUrl($name, $secret, $issuer);

        return '/qrcode.php?text=' . rawurlencode($otpAuthUrl) . '&size=' . $size;
    }

    public function verifyCode(string $secret, string $code, int $discrepancy = 1, ?int $currentTimeSlice = null): bool
    {
        $currentTimeSlice ??= (int)floor(time() / 30);

        if (strlen($code) !== $this->codeLength) {
            return false;
        }

        for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
            $calculated = $this->getCode($secret, $currentTimeSlice + $i);
            if ($this->timingSafeEquals($calculated, $code)) {
                return true;
            }
        }

        return false;
    }

    protected function base32Decode(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        $base32chars = $this->getBase32LookupTable();
        $base32charsFlipped = array_flip($base32chars);

        $paddingCharCount = substr_count($secret, $base32chars[32]);
        $allowedValues = [6, 4, 3, 1, 0];
        if (!in_array($paddingCharCount, $allowedValues, true)) {
            throw new RuntimeException('Invalid base32 secret');
        }

        for ($i = 0; $i < 4; ++$i) {
            if ($allowedValues[$i] === 0) {
                continue;
            }

            if (
                $paddingCharCount === $allowedValues[$i]
                && substr($secret, -$allowedValues[$i]) !== str_repeat($base32chars[32], $allowedValues[$i])
            ) {
                throw new RuntimeException('Invalid base32 padding');
            }
        }

        $secret = str_replace('=', '', strtoupper($secret));
        $chars = str_split($secret);
        $binaryString = '';

        for ($i = 0, $count = count($chars); $i < $count; $i += 8) {
            $chunk = '';
            for ($j = 0; $j < 8; ++$j) {
                $current = $chars[$i + $j] ?? '=';
                if (!array_key_exists($current, $base32charsFlipped)) {
                    throw new RuntimeException('Invalid base32 character');
                }

                $chunk .= str_pad((string)base_convert((string)$base32charsFlipped[$current], 10, 2), 5, '0', STR_PAD_LEFT);
            }

            foreach (str_split($chunk, 8) as $bits) {
                $value = (int)base_convert($bits, 2, 10);
                $binaryString .= chr($value);
            }
        }

        return $binaryString;
    }

    protected function getBase32LookupTable(): array
    {
        return [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
            'Y', 'Z', '2', '3', '4', '5', '6', '7',
            '=',
        ];
    }

    private function timingSafeEquals(string $known, string $user): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($known, $user);
        }

        if (strlen($known) !== strlen($user)) {
            return false;
        }

        $result = 0;
        for ($i = 0, $length = strlen($known); $i < $length; ++$i) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }

        return $result === 0;
    }
}
