<?php

namespace Talal\LabelPrinter;

use Talal\LabelPrinter\Command;
use Talal\LabelPrinter\Command\CommandInterface;

class VisitorBadge implements CommandInterface
{
    protected $width;

    protected $height;

    protected $data;

    protected $requiredFields = [
        'visitor_name',
        'company_name',
        'validity_date',
        'host_name'
    ];

    public function __construct($width, $height, array $data)
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Badge width and height must be greater than zero.');
        }

        foreach ($this->requiredFields as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw new \InvalidArgumentException(
                    'Missing required visitor badge field: ' . $field
                );
            }
        }

        $this->width = intval($width);
        $this->height = intval($height);
        $this->data = $data;
    }

    public function render()
    {
        return $this->renderGraphics(true);
    }

    public function save($path)
    {
        $image = $this->render();

        if (! imagepng($image, $path)) {
            imagedestroy($image);

            throw new \RuntimeException(
                'Unable to save visitor badge image.'
            );
        }

        imagedestroy($image);

        return $path;
    }

    public function printTo($resource)
    {
        if (! is_resource($resource)) {
            throw new \InvalidArgumentException('An invalid print resource has been provided.');
        }

        $this->writeFully($resource, $this->read());
        $this->finishPrintResource($resource);
    }

    protected function writeFully($resource, $data)
    {
        if (function_exists('stream_set_blocking')) {
            @stream_set_blocking($resource, true);
        }

        if (function_exists('stream_set_write_buffer')) {
            @stream_set_write_buffer($resource, 0);
        }

        $length = strlen($data);
        $offset = 0;
        $chunkSize = 4096;
        $blockedWrites = 0;

        while ($offset < $length) {
            $chunk = substr($data, $offset, min($chunkSize, $length - $offset));
            $written = @fwrite($resource, $chunk);

            if ($written === false || $written === 0) {
                $blockedWrites++;

                if ($blockedWrites > 100) {
                    throw new \RuntimeException('Printer did not accept visitor badge data.');
                }

                usleep(100000);
                continue;
            }

            $offset += $written;
            $blockedWrites = 0;
        }

        fflush($resource);
    }

    protected function finishPrintResource($resource)
    {
        if (function_exists('stream_socket_shutdown') && defined('STREAM_SHUT_WR')) {
            @stream_socket_shutdown($resource, STREAM_SHUT_WR);
        }
    }

    protected function getLayout()
    {
        $scale = $this->layoutScale();
        $hasPhoto = $this->hasReadableImage('visitor_photo');
        $margin = $this->marginDots();
        $headerY = intval(round($margin / 2));
        $left = $margin;
        $right = $margin;
        $textX = $hasPhoto ? $margin + $this->scaledX(252) : $margin;
        $identityWidth = max(1, $this->width - $textX - $right);
        $footerWidth = max(1, $this->width - ($left * 2));
        $nameSize = $this->fitNativeSize($this->data['visitor_name'], $identityWidth, $hasPhoto ? 58 : 50, 42);
        $companySize = $this->fitNativeSize($this->data['company_name'], $identityWidth, 38, 33);
        $hostLabelSize = $this->outlineSize(38);
        $validityLabelSize = $this->outlineSize(38);
        $hostValueX = $textX + $this->scaledX(92);
        $validityValueX = $textX + $this->scaledX(154);
        $hostWidth = max(1, $this->width - $hostValueX - $right);
        $validityWidth = max(1, $this->width - $validityValueX - $right);
        $hostSize = $this->fitNativeSize($this->data['host_name'], $hostWidth, 38, 33);
        $validitySize = $this->fitNativeSize($this->data['validity_date'], $validityWidth, 38, 33);

        return [
            'header' => [
                'x' => 0,
                'y' => $headerY,
                'width' => $this->width,
                'height' => max(1, $this->scaledY(120)),
                'text_area_x' => 0,
                'text_area_width' => max(1, $this->width - $this->scaledX(196)),
                'text' => 'VISITOR',
                'text_scale' => max(2, intval(round(5 * $scale)))
            ],
            'logo' => [
                'max_width' => $this->scaledX(160),
                'max_height' => $this->scaledY(166),
                'right' => $this->scaledX(30),
                'y' => $headerY
            ],
            'photo' => [
                'x' => 0,
                'y' => $headerY + $this->scaledY(197),
                'width' => $this->scaledX(252),
                'height' => $this->scaledY(245),
                'border' => 0
            ],
            'rule' => [
                'x' => $textX,
                'y' => $this->scaledY(214),
                'width' => $this->scaledX(238),
                'height' => max(1, $this->scaledY(2))
            ],
            'icons' => [
                'host' => [
                    'x' => $textX,
                    'y' => $headerY + $this->scaledY(335),
                    'size' => $this->scaledX(30)
                ],
                'validity' => [
                    'x' => $textX,
                    'y' => $headerY + $this->scaledY(423),
                    'size' => $this->scaledX(30)
                ]
            ],
            'bottom_wave' => [
                'height' => $this->scaledY(52)
            ],
            'lines' => [
                'visitor_name' => [
                    'text' => $this->data['visitor_name'],
                    'x' => $textX,
                    'y' => $headerY + $this->scaledY(197),
                    'size' => $nameSize,
                    'preview_size' => $this->previewFontSize($nameSize),
                    'max_width' => $identityWidth,
                    'bold' => true
                ],
                'company_name' => [
                    'text' => $this->data['company_name'],
                    'x' => $textX,
                    'y' => $headerY + $this->scaledY($hasPhoto ? 247 : 257),
                    'size' => $companySize,
                    'preview_size' => $this->previewFontSize($companySize),
                    'max_width' => $identityWidth,
                    'bold' => false
                ],
                'host_label' => [
                    'text' => 'Host:',
                    'x' => $textX,
                    'y' => $headerY + $this->scaledY(329),
                    'size' => $hostLabelSize,
                    'preview_size' => $this->previewFontSize($hostLabelSize),
                    'max_width' => $identityWidth,
                    'bold' => true
                ],
                'host_name' => [
                    'text' => $this->data['host_name'],
                    'x' => $hostValueX,
                    'y' => $headerY + $this->scaledY(329),
                    'size' => $hostSize,
                    'preview_size' => $this->previewFontSize($hostSize),
                    'max_width' => $hostWidth,
                    'bold' => false
                ],
                'validity_label' => [
                    'text' => 'Valid on:',
                    'x' => $textX,
                    'y' => $headerY + $this->scaledY(417),
                    'size' => $validityLabelSize,
                    'preview_size' => $this->previewFontSize($validityLabelSize),
                    'max_width' => $identityWidth,
                    'bold' => true
                ],
                'validity_date' => [
                    'text' => $this->data['validity_date'],
                    'x' => $validityValueX,
                    'y' => $headerY + $this->scaledY(417),
                    'size' => $validitySize,
                    'preview_size' => $this->previewFontSize($validitySize),
                    'max_width' => $validityWidth,
                    'bold' => false
                ]
            ]
        ];
    }

    protected function scaledX($value)
    {
        return intval(round($value * $this->layoutScale()));
    }

    protected function scaledY($value)
    {
        return intval(round($value * $this->layoutScale()));
    }

    protected function layoutScale()
    {
        return min($this->width / 696, $this->height / 509);
    }

    protected function marginDots()
    {
        return 35;
    }

    protected function outlineSize($baseSize)
    {
        $scale = $this->layoutScale();
        $target = $baseSize * $scale;
        $sizes = [33, 38, 42, 46, 50, 58, 67, 75, 83, 92, 100, 117, 133, 150, 167, 200];
        $selected = $sizes[0];

        foreach ($sizes as $size) {
            if (abs($size - $target) < abs($selected - $target)) {
                $selected = $size;
            }
        }

        return $selected;
    }

    protected function fitNativeSize($text, $maxWidth, $preferredSize, $minimumSize)
    {
        $preferred = $this->outlineSize($preferredSize);
        $sizes = [200, 167, 150, 133, 117, 100, 92, 83, 75, 67, 58, 50, 46, 42, 38, 33];
        $fallback = 33;

        foreach ($sizes as $size) {
            if ($size > $preferred || $size < $minimumSize) {
                continue;
            }

            if ($this->nativeTextWidth($text, $size) <= $maxWidth) {
                return $size;
            }

            $fallback = $size;
        }

        return $fallback;
    }

    protected function previewFontSize($nativeSize)
    {
        return max(18, intval(round($nativeSize * 0.68)));
    }

    protected function hasReadableImage($key)
    {
        return ! empty($this->data[$key]) && is_file($this->data[$key]);
    }

    protected function renderGraphics($includeText = false)
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('GD extension is required.');
        }

        $image = imagecreatetruecolor($this->width, $this->height);

        if ($image === false) {
            throw new \RuntimeException('Unable to create visitor badge image.');
        }

        $layout = $this->getLayout();
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $red = imagecolorallocate($image, 255, 0, 0);

        imagefill($image, 0, 0, $white);

        imagefilledrectangle(
            $image,
            $layout['header']['x'],
            $layout['header']['y'],
            $layout['header']['x'] + $layout['header']['width'] - 1,
            $layout['header']['y'] + $layout['header']['height'] - 1,
            $red
        );

        $this->drawHeaderText(
            $image,
            $layout['header'],
            $white,
            $red
        );

        if ($this->hasReadableImage('logo')) {
            $this->drawLogo($image, $layout['logo']);
        }

        if ($this->hasReadableImage('visitor_photo')) {
            $this->drawPhoto($image, $layout['photo']);
        }

        if ($includeText) {
            foreach ($layout['lines'] as $line) {
                $text = $this->fitText($line['text'], $line['max_width'], $line['size']);
                $x = $line['x'];

                if (isset($line['align']) && $line['align'] === 'center') {
                    $x = $this->centerPreviewTextX(
                        $text,
                        $line['preview_size'],
                        $line['bold'],
                        $line['x'],
                        $line['max_width']
                    );
                }

                $this->drawScaledText(
                    $image,
                    $x,
                    $line['y'],
                    $text,
                    $line['preview_size'],
                    $line['max_width'],
                    $black,
                    $line['bold']
                );
            }
        }

        return $image;
    }

    protected function drawLogo($image, array $layout)
    {
        $logo = $this->loadImage($this->data['logo']);

        if ($logo === false) {
            return;
        }

        $sourceWidth = imagesx($logo);
        $sourceHeight = imagesy($logo);

        $scale = min(
            $layout['max_width'] / $sourceWidth,
            $layout['max_height'] / $sourceHeight
        );

        $logoWidth = max(1, intval($sourceWidth * $scale));
        $logoHeight = max(1, intval($sourceHeight * $scale));
        $logoX = $this->width - $logoWidth - $layout['right'];

        imagecopyresampled(
            $image,
            $logo,
            $logoX,
            $layout['y'],
            0,
            0,
            $logoWidth,
            $logoHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($logo);
    }

    protected function drawHeaderAccents($image, array $header, $darkRed, $deepRed)
    {
        $y = $header['y'];
        $height = $header['height'];

        imagefilledpolygon(
            $image,
            [
                $this->scaledX(28), $y,
                $this->scaledX(76), $y,
                $this->scaledX(22), $y + $height,
                0, $y + $height
            ],
            4,
            $darkRed
        );

        imagefilledpolygon(
            $image,
            [
                $this->scaledX(84), $y,
                $this->scaledX(100), $y,
                $this->scaledX(44), $y + $height,
                $this->scaledX(28), $y + $height
            ],
            4,
            $deepRed
        );

        imagefilledpolygon(
            $image,
            [
                $this->width - $this->scaledX(36), $y,
                $this->width, $y,
                $this->width, $y + $height,
                $this->width - $this->scaledX(88), $y + $height
            ],
            4,
            $darkRed
        );
    }

    protected function drawWatermark($image, $color)
    {
        $baseY = $this->scaledY(468);
        $left = $this->scaledX(438);
        $right = $this->scaledX(678);
        $top = $this->scaledY(300);

        imagefilledpolygon(
            $image,
            [
                $left, $top,
                $right, $top,
                $right - $this->scaledX(34), $top - $this->scaledY(34),
                $left + $this->scaledX(34), $top - $this->scaledY(34)
            ],
            4,
            $color
        );

        imagefilledrectangle($image, $left + $this->scaledX(6), $top + $this->scaledY(12), $right - $this->scaledX(6), $top + $this->scaledY(20), $color);

        for ($i = 0; $i < 4; $i++) {
            $x = $left + $this->scaledX(28 + ($i * 46));
            imagefilledrectangle($image, $x, $top + $this->scaledY(32), $x + $this->scaledX(18), $baseY, $color);
        }

        imagefilledrectangle($image, $left, $baseY, $right, $baseY + $this->scaledY(14), $color);
    }

    protected function drawBottomWave($image, array $layout, $red, $darkRed, $lightGray)
    {
        $height = $layout['height'];
        $top = $this->height - $height;

        imagefilledpolygon(
            $image,
            [
                0, $this->height,
                0, $top + $this->scaledY(20),
                $this->scaledX(112), $top + $this->scaledY(10),
                $this->scaledX(238), $top + $this->scaledY(22),
                $this->scaledX(338), $this->height,
            ],
            5,
            $lightGray
        );

        imagefilledpolygon(
            $image,
            [
                0, $this->height,
                0, $top + $this->scaledY(30),
                $this->scaledX(110), $top + $this->scaledY(20),
                $this->scaledX(230), $top + $this->scaledY(34),
                $this->scaledX(318), $this->height,
            ],
            5,
            $red
        );

        imagefilledpolygon(
            $image,
            [
                0, $this->height,
                0, $top + $this->scaledY(48),
                $this->scaledX(120), $top + $this->scaledY(38),
                $this->scaledX(230), $top + $this->scaledY(50),
                $this->scaledX(286), $this->height,
            ],
            5,
            $darkRed
        );
    }

    protected function drawRule($image, array $layout, $color)
    {
        imagefilledrectangle(
            $image,
            $layout['x'],
            $layout['y'],
            $layout['x'] + $layout['width'] - 1,
            $layout['y'] + $layout['height'] - 1,
            $color
        );
    }

    protected function drawPersonIcon($image, array $layout, $color)
    {
        $x = $layout['x'];
        $y = $layout['y'];
        $size = $layout['size'];

        imagefilledellipse(
            $image,
            $x + intval($size * 0.50),
            $y + intval($size * 0.28),
            max(3, intval($size * 0.38)),
            max(3, intval($size * 0.38)),
            $color
        );

        imagefilledellipse(
            $image,
            $x + intval($size * 0.50),
            $y + intval($size * 0.78),
            max(3, intval($size * 0.78)),
            max(3, intval($size * 0.45)),
            $color
        );

        imagefilledpolygon(
            $image,
            [
                $x + intval($size * 0.16), $y + intval($size * 0.92),
                $x + intval($size * 0.84), $y + intval($size * 0.92),
                $x + intval($size * 0.74), $y + intval($size * 0.70),
                $x + intval($size * 0.26), $y + intval($size * 0.70)
            ],
            4,
            $color
        );
    }

    protected function drawCalendarIcon($image, array $layout, $color)
    {
        $x = $layout['x'];
        $y = $layout['y'];
        $size = $layout['size'];
        $stroke = max(2, intval($size / 8));

        imagesetthickness($image, $stroke);
        imagerectangle($image, $x + 1, $y + intval($size * 0.14), $x + $size - 1, $y + $size - 1, $color);
        imageline($image, $x + 1, $y + intval($size * 0.38), $x + $size - 1, $y + intval($size * 0.38), $color);
        imageline($image, $x + intval($size * 0.28), $y + 1, $x + intval($size * 0.28), $y + intval($size * 0.24), $color);
        imageline($image, $x + intval($size * 0.72), $y + 1, $x + intval($size * 0.72), $y + intval($size * 0.24), $color);
        imagesetthickness($image, 1);

        imagefilledrectangle($image, $x + intval($size * 0.23), $y + intval($size * 0.51), $x + intval($size * 0.34), $y + intval($size * 0.62), $color);
        imagefilledrectangle($image, $x + intval($size * 0.45), $y + intval($size * 0.51), $x + intval($size * 0.56), $y + intval($size * 0.62), $color);
        imagefilledrectangle($image, $x + intval($size * 0.67), $y + intval($size * 0.51), $x + intval($size * 0.78), $y + intval($size * 0.62), $color);
        imagefilledrectangle($image, $x + intval($size * 0.23), $y + intval($size * 0.70), $x + intval($size * 0.34), $y + intval($size * 0.81), $color);
        imagefilledrectangle($image, $x + intval($size * 0.45), $y + intval($size * 0.70), $x + intval($size * 0.56), $y + intval($size * 0.81), $color);
        imagefilledrectangle($image, $x + intval($size * 0.67), $y + intval($size * 0.70), $x + intval($size * 0.78), $y + intval($size * 0.81), $color);
    }

    protected function drawPhoto($image, array $layout)
    {
        $photo = $this->loadImage($this->data['visitor_photo']);

        if ($photo === false) {
            return;
        }

        $this->enhancePhoto($photo);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $border = isset($layout['border']) ? $layout['border'] : 0;

        if ($border > 0) {
            imagefilledrectangle(
                $image,
                $layout['x'] - $border,
                $layout['y'] - $border,
                $layout['x'] + $layout['width'] + $border - 1,
                $layout['y'] + $layout['height'] + $border - 1,
                $black
            );

            imagefilledrectangle(
                $image,
                $layout['x'] - $border + 1,
                $layout['y'] - $border + 1,
                $layout['x'] + $layout['width'] + $border - 2,
                $layout['y'] + $layout['height'] + $border - 2,
                $white
            );
        }

        $sourceWidth = imagesx($photo);
        $sourceHeight = imagesy($photo);
        $targetRatio = $layout['width'] / $layout['height'];
        $sourceRatio = $sourceWidth / $sourceHeight;
        $sourceX = 0;
        $sourceY = 0;

        if ($sourceRatio > $targetRatio) {
            $cropWidth = intval($sourceHeight * $targetRatio);
            $cropHeight = $sourceHeight;
            $sourceX = intval(($sourceWidth - $cropWidth) / 2);
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = intval($sourceWidth / $targetRatio);
            $sourceY = intval(($sourceHeight - $cropHeight) * 0.18);
        }

        imagecopyresampled(
            $image,
            $photo,
            $layout['x'],
            $layout['y'],
            $sourceX,
            $sourceY,
            $layout['width'],
            $layout['height'],
            $cropWidth,
            $cropHeight
        );

        imagedestroy($photo);
    }

    protected function enhancePhoto($photo)
    {
        imagefilter($photo, IMG_FILTER_GRAYSCALE);
        imagefilter($photo, IMG_FILTER_CONTRAST, -10);

        if (function_exists('imageconvolution')) {
            imageconvolution(
                $photo,
                [
                    [-1, -1, -1],
                    [-1, 16, -1],
                    [-1, -1, -1]
                ],
                8,
                0
            );
        }
    }

    protected function loadImage($path)
    {
        $imageData = file_get_contents($path);

        if ($imageData === false) {
            return false;
        }

        return @imagecreatefromstring($imageData);
    }

    protected function drawHeaderText($image, array $header, $color, $background)
    {
        $fontPath = $this->headerFontPath();
        $textAreaX = isset($header['text_area_x']) ? $header['text_area_x'] : $header['x'];
        $textAreaWidth = isset($header['text_area_width']) ? $header['text_area_width'] : $header['width'];

        if ($fontPath === null || ! function_exists('imagettftext')) {
            throw new \RuntimeException(
                'A TrueType font is required for visitor badge printing. Install Open Sans or Arial, or pass font_regular_path and font_bold_path.'
            );
        }

        $fontSize = max(18, intval($header['height'] * 0.78));

        while (
            $fontSize > 18 &&
            $this->trueTypeTextWidth($fontPath, $fontSize, $header['text']) > ($textAreaWidth * 0.84)
        ) {
            $fontSize--;
        }

        $box = imagettfbbox($fontSize, 0, $fontPath, $header['text']);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);
        $x = intval($textAreaX + (($textAreaWidth - $textWidth) / 2));
        $y = intval($header['y'] + (($header['height'] - $textHeight) / 2) + $textHeight);

        imagettftext(
            $image,
            $fontSize,
            0,
            $x,
            $y,
            $color,
            $fontPath,
            $header['text']
        );
    }

    protected function drawScaledText($image, $x, $y, $text, $fontSize, $maxWidth, $color, $bold = false)
    {
        $fontPath = $this->previewFontPath($bold);

        if ($fontPath === null || ! function_exists('imagettftext')) {
            throw new \RuntimeException(
                'A TrueType font is required for visitor badge printing. Install Open Sans or Arial, or pass font_regular_path and font_bold_path.'
            );
        }

        while ($fontSize > 12 && $this->trueTypeTextWidth($fontPath, $fontSize, $text) > $maxWidth) {
            $fontSize--;
        }

        imagettftext(
            $image,
            $fontSize,
            0,
            $x,
            $y + $fontSize,
            $color,
            $fontPath,
            $text
        );
    }

    protected function drawBitmapText($image, $x, $y, $text, $scale, $maxWidth)
    {
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);

        while (($textWidth * $scale) > $maxWidth && $scale > 1) {
            $scale--;
        }

        $temp = imagecreatetruecolor(max(1, $textWidth), $textHeight);
        $white = imagecolorallocate($temp, 255, 255, 255);
        $black = imagecolorallocate($temp, 0, 0, 0);

        imagefill($temp, 0, 0, $white);

        imagestring(
            $temp,
            $font,
            0,
            0,
            $text,
            $black
        );

        imagecopyresized(
            $image,
            $temp,
            $x,
            $y,
            0,
            0,
            $textWidth * $scale,
            $textHeight * $scale,
            $textWidth,
            $textHeight
        );

        imagedestroy($temp);
    }

    protected function previewFontPath($bold = false)
    {
        $fontKey = $bold ? 'font_bold_path' : 'font_regular_path';

        if (! empty($this->data[$fontKey]) && is_file($this->data[$fontKey])) {
            return $this->data[$fontKey];
        }

        if (! empty($this->data['font_path']) && is_file($this->data['font_path'])) {
            return $this->data['font_path'];
        }

        $paths = $bold
            ? [
                __DIR__ . '/../public/fonts/OpenSans-Bold.ttf',
                __DIR__ . '/../resources/fonts/OpenSans-Bold.ttf',
                'C:\\Windows\\Fonts\\OpenSans-Bold.ttf',
                'C:\\Windows\\Fonts\\OpenSans\\OpenSans-Bold.ttf',
                'C:\\Windows\\Fonts\\arialbd.ttf',
                'C:\\Windows\\Fonts\\Arial Bold.ttf',
                '/Library/Fonts/OpenSans-Bold.ttf',
                '/Library/Fonts/Arial Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/usr/share/fonts/truetype/open-sans/OpenSans-Bold.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/Arial_Bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                'C:\\Windows\\Fonts\\calibrib.ttf',
                'C:\\Windows\\Fonts\\georgiab.ttf'
            ]
            : [
                __DIR__ . '/../public/fonts/OpenSans-Regular.ttf',
                __DIR__ . '/../resources/fonts/OpenSans-Regular.ttf',
                'C:\\Windows\\Fonts\\OpenSans-Regular.ttf',
                'C:\\Windows\\Fonts\\OpenSans\\OpenSans-Regular.ttf',
                'C:\\Windows\\Fonts\\Arial.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
                '/Library/Fonts/OpenSans-Regular.ttf',
                '/Library/Fonts/Arial.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/usr/share/fonts/truetype/open-sans/OpenSans-Regular.ttf',
                '/usr/share/fonts/truetype/msttcorefonts/Arial.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                'C:\\Windows\\Fonts\\calibri.ttf',
                'C:\\Windows\\Fonts\\georgia.ttf'
            ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function headerFontPath()
    {
        return $this->previewFontPath(true);
    }

    protected function trueTypeTextWidth($fontPath, $fontSize, $text)
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text);

        return abs($box[2] - $box[0]);
    }

    protected function centerPreviewTextX($text, $fontSize, $bold, $x, $maxWidth)
    {
        $fontPath = $this->previewFontPath($bold);

        if ($fontPath !== null && function_exists('imagettfbbox')) {
            return intval($x + (($maxWidth - $this->trueTypeTextWidth($fontPath, $fontSize, $text)) / 2));
        }

        return intval($x);
    }

    protected function centerNativeTextX($text, $size, $x, $maxWidth)
    {
        return intval($x + (($maxWidth - $this->nativeTextWidth($text, $size)) / 2));
    }

    protected function fitText($text, $maxWidth, $size)
    {
        $text = (string) $text;

        while (strlen($text) > 1 && $this->nativeTextWidth($text, $size) > $maxWidth) {
            $text = substr($text, 0, -1);
        }

        return $text;
    }

    protected function nativeTextWidth($text, $size)
    {
        return strlen($text) * ($size * 0.62);
    }

    public function read()
    {
        $image = $this->renderGraphics(true);
        $ditherMask = $this->renderDitherMask();
        $printImage = null;
        $printDitherMask = null;

        try {
            $printImage = $this->rotateForLandscapePrint($image);
            $printDitherMask = $this->rotateForLandscapePrint($ditherMask);

            return $this->renderTwoColorRaster($printImage, $printDitherMask);
        } finally {
            if ($printImage !== null) {
                imagedestroy($printImage);
            }

            if ($printDitherMask !== null) {
                imagedestroy($printDitherMask);
            }

            imagedestroy($ditherMask);
            imagedestroy($image);
        }
    }

    protected function renderDitherMask()
    {
        $mask = imagecreatetruecolor($this->width, $this->height);

        if ($mask === false) {
            throw new \RuntimeException('Unable to create visitor badge dither mask.');
        }

        $white = imagecolorallocate($mask, 255, 255, 255);
        $black = imagecolorallocate($mask, 0, 0, 0);

        imagefill($mask, 0, 0, $white);

        if ($this->hasReadableImage('visitor_photo')) {
            $photo = $this->getLayout()['photo'];

            imagefilledrectangle(
                $mask,
                $photo['x'],
                $photo['y'],
                $photo['x'] + $photo['width'] - 1,
                $photo['y'] + $photo['height'] - 1,
                $black
            );
        }

        return $mask;
    }

    protected function rotateForLandscapePrint($image)
    {
        $white = imagecolorallocate($image, 255, 255, 255);
        $rotated = imagerotate($image, 90, $white);

        if ($rotated === false) {
            throw new \RuntimeException('Unable to rotate visitor badge for landscape printing.');
        }

        return $rotated;
    }

    protected function renderTwoColorRaster($image, $ditherMask = null)
    {
        if (imagesx($image) > $this->printerWidthDots()) {
            throw new \InvalidArgumentException(
                'The printable badge height must be ' . $this->printerWidthDots() .
                ' dots or less after landscape rotation. Pass the label length as width and the tape width as height.'
            );
        }

        $output = $this->invalidateRasterParser();
        $output .= chr(27) . chr(64);
        $output .= chr(27) . 'ia' . chr(1);
        $output .= chr(27) . 'i!' . chr(1);
        $output .= $this->rasterPrintInformation(imagesy($image));
        $output .= chr(27) . 'iM' . chr(64);
        $output .= chr(27) . 'iA' . chr(1);
        $output .= chr(27) . 'iK' . chr(9);
        $output .= chr(27) . 'id' . $this->littleEndian16(35);
        $output .= $this->twoColorRasterRows($image, $ditherMask);
        $output .= chr(26);

        return $output;
    }

    protected function invalidateRasterParser()
    {
        return str_repeat(chr(0), 400);
    }

    protected function rasterPrintInformation($rasterRows)
    {
        return chr(27) . 'iz' .
            chr(142) .
            chr(10) .
            chr(62) .
            chr(0) .
            $this->littleEndian32($rasterRows) .
            chr(0) .
            chr(2);
    }

    protected function twoColorRasterRows($image, $ditherMask = null)
    {
        $blackRows = $this->blackRasterRows($image, $ditherMask);
        $redRows = $this->redRasterRows($image);
        $output = '';
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            $output .= 'w' . chr(1) . chr(90) . $blackRows[$y];
            $output .= 'w' . chr(2) . chr(90) . $redRows[$y];
        }

        return $output;
    }

    protected function blackRasterRows($image, $ditherMask = null)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $values = [];
        $rows = [];

        for ($y = 0; $y < $height; $y++) {
            $values[$y] = [];

            for ($x = 0; $x < $width; $x++) {
                $channels = $this->pixelChannels($image, $x, $y);

                if ($this->isRedPixel($channels)) {
                    $values[$y][$x] = 255;
                } else {
                    $values[$y][$x] = $this->luminance($channels);
                }
            }
        }

        for ($y = 0; $y < $height; $y++) {
            $rows[$y] = str_repeat(chr(0), 90);
        }

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! $this->isDitherPixel($ditherMask, $x, $y)) {
                    if ($values[$y][$x] < 190) {
                        $this->setRasterPixel($rows[$y], $x, $width, true);
                    }

                    continue;
                }

                $old = $values[$y][$x];
                $new = $old < 170 ? 0 : 255;

                if ($new === 0) {
                    $this->setRasterPixel($rows[$y], $x, $width, true);
                }

                $error = $old - $new;
                $this->diffuseRasterError($values, $width, $height, $x + 1, $y, $error, 7 / 16, $ditherMask);
                $this->diffuseRasterError($values, $width, $height, $x - 1, $y + 1, $error, 3 / 16, $ditherMask);
                $this->diffuseRasterError($values, $width, $height, $x, $y + 1, $error, 5 / 16, $ditherMask);
                $this->diffuseRasterError($values, $width, $height, $x + 1, $y + 1, $error, 1 / 16, $ditherMask);
            }
        }

        return $rows;
    }

    protected function isDitherPixel($ditherMask, $x, $y)
    {
        if ($ditherMask === null) {
            return false;
        }

        return $this->luminance($this->pixelChannels($ditherMask, $x, $y)) < 128;
    }

    protected function redRasterRows($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $rows = [];

        for ($y = 0; $y < $height; $y++) {
            $rows[$y] = str_repeat(chr(0), 90);

            for ($x = 0; $x < $width; $x++) {
                if ($this->isRedPixel($this->pixelChannels($image, $x, $y))) {
                    $this->setRasterPixel($rows[$y], $x, $width, true);
                }
            }
        }

        return $rows;
    }

    protected function setRasterPixel(&$row, $x, $rasterWidth, $enabled)
    {
        if (! $enabled) {
            return;
        }

        $deviceWidth = $this->printerWidthDots();
        $offset = intval(($deviceWidth - $rasterWidth) / 2);
        $deviceX = $deviceWidth - 1 - ($offset + $x);

        if ($deviceX < 0 || $deviceX >= $deviceWidth) {
            return;
        }

        $byteIndex = intval($deviceX / 8);
        $bit = 7 - ($deviceX % 8);
        $row[$byteIndex] = chr(ord($row[$byteIndex]) | (1 << $bit));
    }

    protected function printerWidthDots()
    {
        return 720;
    }

    protected function pixelChannels($image, $x, $y)
    {
        $rgb = imagecolorat($image, $x, $y);

        if (! imageistruecolor($image)) {
            $colors = imagecolorsforindex($image, $rgb);

            return [
                'red' => $colors['red'],
                'green' => $colors['green'],
                'blue' => $colors['blue']
            ];
        }

        return [
            'red' => ($rgb >> 16) & 0xFF,
            'green' => ($rgb >> 8) & 0xFF,
            'blue' => $rgb & 0xFF
        ];
    }

    protected function isRedPixel(array $channels)
    {
        return $channels['red'] > 170 &&
            $channels['green'] < 120 &&
            $channels['blue'] < 120 &&
            ($channels['red'] - max($channels['green'], $channels['blue'])) > 70;
    }

    protected function luminance(array $channels)
    {
        return (
            ($channels['red'] * 0.299) +
            ($channels['green'] * 0.587) +
            ($channels['blue'] * 0.114)
        );
    }

    protected function diffuseRasterError(array &$values, $width, $height, $x, $y, $error, $factor, $ditherMask = null)
    {
        if ($x < 0 || $x >= $width || $y < 0 || $y >= $height) {
            return;
        }

        if (! $this->isDitherPixel($ditherMask, $x, $y)) {
            return;
        }

        $values[$y][$x] += $error * $factor;

        if ($values[$y][$x] < 0) {
            $values[$y][$x] = 0;
        } elseif ($values[$y][$x] > 255) {
            $values[$y][$x] = 255;
        }
    }

    protected function littleEndian16($value)
    {
        return chr($value % 256) . chr(intval($value / 256));
    }

    protected function littleEndian32($value)
    {
        return chr($value % 256) .
            chr(intval($value / 256) % 256) .
            chr(intval($value / 65536) % 256) .
            chr(intval($value / 16777216) % 256);
    }
}
