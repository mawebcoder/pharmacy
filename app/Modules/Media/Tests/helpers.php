<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;

/**
 * A real image file (Imagick) for upload tests: a solid colour with a darker square in the middle.
 */
function testImage(int $width, int $height, string $format = 'png', string $name = 'image'): UploadedFile
{
    $image = new Imagick;
    $image->newImage($width, $height, new ImagickPixel('#2a9d8f'));
    $draw = new ImagickDraw;
    $draw->setFillColor(new ImagickPixel('#264653'));
    $draw->rectangle(intdiv($width, 4), intdiv($height, 4), intdiv($width * 3, 4), intdiv($height * 3, 4));
    $image->drawImage($draw);
    $image->setImageFormat($format);

    // UploadedFile::fake() files work with Livewire's upload testing; the content is a real image.
    return UploadedFile::fake()->createWithContent("{$name}.{$format}", $image->getImagesBlob());
}

function testSvg(string $body = '<rect width="40" height="20" fill="#0f766e"/>'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 20">'.$body.'</svg>');
}
