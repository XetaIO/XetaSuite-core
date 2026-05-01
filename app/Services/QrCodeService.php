<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    /**
     * Generate an SVG QR code (base64 encoded) for the given URL.
     *
     * @return array{svg: string, url: string, size: int}
     */
    public function generateSvg(string $url, int $size = 200): array
    {
        $size = max(100, min(400, $size));

        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $writer = new SvgWriter();
        $result = $writer->write($qrCode);

        return [
            'svg' => base64_encode($result->getString()),
            'url' => $url,
            'size' => $size,
        ];
    }
}
