<?php

declare(strict_types=1);

namespace app\support;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;
use Zxing\QrReader;

final class QrCodeService
{
    public function renderPng(string $text, int $size): string
    {
        $writer = new PngWriter();
        $qrCode = QrCode::create($text)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
            ->setSize($size)
            ->setMargin(0)
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        $png = $writer->write($qrCode)->getString();
        if (!is_string($png) || !$this->isPng($png)) {
            throw new RuntimeException('qrcode renderer returned invalid png');
        }

        return $png;
    }

    public function decodeFile(string $path): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('二维码图片不存在');
        }

        $reader = new QrReader($path, QrReader::SOURCE_TYPE_FILE, extension_loaded('imagick'));
        $decoded = $reader->text([
            'TRY_HARDER' => true,
            'NR_ALLOW_SKIP_ROWS' => 0,
        ]);

        if (!is_string($decoded)) {
            throw new RuntimeException('二维码解析失败，请换一张更清晰的二维码图片');
        }

        $content = trim($decoded);
        if ($content === '') {
            throw new RuntimeException('二维码解析失败，请换一张更清晰的二维码图片');
        }

        return $content;
    }

    private function isPng(string $body): bool
    {
        return str_starts_with($body, "\x89PNG\r\n\x1a\n");
    }
}
