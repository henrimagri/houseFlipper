<?php

namespace App\Services\Visualization;

class PhotoPixelSampler
{
    public function sample(?string $absolutePath): array
    {
        if ($absolutePath === null || $absolutePath === '' || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return ['ok' => false, 'reason' => 'file_missing'];
        }
        if (! function_exists('imagecreatefromstring')) {
            return ['ok' => false, 'reason' => 'gd_unavailable'];
        }
        $size = filesize($absolutePath);
        if ($size === false || $size < 32 || $size > 8_000_000) {
            return ['ok' => false, 'reason' => 'size'];
        }
        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            return ['ok' => false, 'reason' => 'unreadable'];
        }
        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return ['ok' => false, 'reason' => 'decode'];
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $targetW = 48;
        $targetH = max(1, (int) round($height * ($targetW / max($width, 1))));
        $small = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($small, $image, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagedestroy($image);

        $buckets = [];
        $brightness = 0;
        $pixels = $targetW * $targetH;
        for ($y = 0; $y < $targetH; $y++) {
            for ($x = 0; $x < $targetW; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r = ($rgb >> 16) & 255;
                $g = ($rgb >> 8) & 255;
                $b = $rgb & 255;
                $brightness += (0.299 * $r + 0.587 * $g + 0.114 * $b);
                $key = sprintf('%02x%02x%02x', intdiv($r, 32) * 32, intdiv($g, 32) * 32, intdiv($b, 32) * 32);
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }
        }
        imagedestroy($small);
        arsort($buckets);
        $palette = [];
        foreach (array_slice($buckets, 0, 5, true) as $hex => $count) {
            $palette[] = [
                'hex' => '#'.$hex,
                'share' => round($count / max($pixels, 1), 3),
                'origin' => 'pixel_histogram',
            ];
        }

        return [
            'ok' => true,
            'width' => $width,
            'height' => $height,
            'brightness' => (int) round($brightness / max($pixels, 1)),
            'aspect' => round($width / max($height, 1), 3),
            'palette' => $palette,
        ];
    }
}
