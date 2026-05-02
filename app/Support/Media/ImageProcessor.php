<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;
use RuntimeException;
use Throwable;

final class ImageProcessor
{
    private const MAX_DIMENSION = 2048;

    private const QUALITY = 82;

    private const DISK = 'public';

    public function processUpload(UploadedFile $file, string $directory = 'recipes'): string
    {
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new RuntimeException('Kon het ge-uploade bestand niet lezen.');
        }

        return $this->process($bytes, $directory);
    }

    public function processBytes(string $bytes, string $directory = 'recipes'): ?string
    {
        if ($bytes === '') {
            return null;
        }

        try {
            return $this->process($bytes, $directory);
        } catch (Throwable) {
            return null;
        }
    }

    private function process(string $bytes, string $directory): string
    {
        $imagick = new Imagick;

        try {
            try {
                $imagick->readImageBlob($bytes);
            } catch (ImagickException $e) {
                throw new RuntimeException('Onbekend of beschadigd afbeeldingsformaat.', previous: $e);
            }

            if ($imagick->getNumberImages() > 1) {
                $imagick->setIteratorIndex(0);
                $single = $imagick->getImage();
                $imagick->clear();
                $imagick = $single;
            }

            $this->autoOrient($imagick);

            if ($imagick->getImageColorspace() !== Imagick::COLORSPACE_SRGB) {
                $imagick->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            }

            $imagick->stripImage();

            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            $longest = max($width, $height);
            if ($longest > self::MAX_DIMENSION) {
                $ratio = self::MAX_DIMENSION / $longest;
                $imagick->resizeImage(
                    (int) round($width * $ratio),
                    (int) round($height * $ratio),
                    Imagick::FILTER_LANCZOS,
                    1,
                );
            }

            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality(self::QUALITY);

            $blob = $imagick->getImageBlob();
        } finally {
            $imagick->clear();
        }

        $path = trim($directory, '/').'/'.bin2hex(random_bytes(16)).'.webp';
        Storage::disk(self::DISK)->put($path, $blob);

        return $path;
    }

    private function autoOrient(Imagick $img): void
    {
        match ($img->getImageOrientation()) {
            Imagick::ORIENTATION_BOTTOMRIGHT => $img->rotateImage('#0000', 180),
            Imagick::ORIENTATION_RIGHTTOP => $img->rotateImage('#0000', 90),
            Imagick::ORIENTATION_LEFTBOTTOM => $img->rotateImage('#0000', -90),
            default => null,
        };

        $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }
}
