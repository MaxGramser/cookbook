<?php

use App\Support\Media\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

function makeImageBlob(string $format, int $width = 100, int $height = 100, string $color = 'red'): string
{
    $im = new Imagick;
    $im->newImage($width, $height, $color);
    $im->setImageFormat($format);
    $blob = $im->getImageBlob();
    $im->clear();

    return $blob;
}

beforeEach(function () {
    Storage::fake('public');
});

test('processes a JPEG upload to WebP', function () {
    $file = UploadedFile::fake()->createWithContent('photo.jpg', makeImageBlob('jpeg'));

    $path = (new ImageProcessor)->processUpload($file);

    expect($path)->toEndWith('.webp');
    expect($path)->toStartWith('recipes/');
    Storage::disk('public')->assertExists($path);

    $stored = Storage::disk('public')->get($path);
    $im = new Imagick;
    $im->readImageBlob($stored);
    expect(strtolower($im->getImageFormat()))->toBe('webp');
    $im->clear();
});

test('processes a PNG upload to WebP', function () {
    $file = UploadedFile::fake()->createWithContent('photo.png', makeImageBlob('png'));

    $path = (new ImageProcessor)->processUpload($file);

    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

test('processes an iPhone HEIC upload to WebP', function () {
    $heicBlob = file_get_contents(__DIR__.'/../Fixtures/iphone-sample.heic');
    $file = UploadedFile::fake()->createWithContent('IMG_0001.HEIC', $heicBlob);

    $path = (new ImageProcessor)->processUpload($file);

    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);

    $stored = Storage::disk('public')->get($path);
    $im = new Imagick;
    $im->readImageBlob($stored);
    expect(strtolower($im->getImageFormat()))->toBe('webp');
    $im->clear();
});

test('processes a WebP upload (re-encoded)', function () {
    $file = UploadedFile::fake()->createWithContent('photo.webp', makeImageBlob('webp'));

    $path = (new ImageProcessor)->processUpload($file);

    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

test('downscales oversized images to max 2048px on longest edge', function () {
    $blob = makeImageBlob('jpeg', 4000, 3000);
    $file = UploadedFile::fake()->createWithContent('big.jpg', $blob);

    $path = (new ImageProcessor)->processUpload($file);

    $im = new Imagick;
    $im->readImageBlob(Storage::disk('public')->get($path));
    expect($im->getImageWidth())->toBe(2048);
    expect($im->getImageHeight())->toBe(1536);
    $im->clear();
});

test('does not upscale small images', function () {
    $blob = makeImageBlob('jpeg', 400, 300);
    $file = UploadedFile::fake()->createWithContent('small.jpg', $blob);

    $path = (new ImageProcessor)->processUpload($file);

    $im = new Imagick;
    $im->readImageBlob(Storage::disk('public')->get($path));
    expect($im->getImageWidth())->toBe(400);
    expect($im->getImageHeight())->toBe(300);
    $im->clear();
});

test('strips EXIF metadata from output', function () {
    $im = new Imagick;
    $im->newImage(100, 100, 'red');
    $im->setImageFormat('jpeg');
    $im->setImageProperty('exif:Make', 'TestCamera');
    $im->setImageProperty('exif:GPSLatitude', '52/1, 23/1, 0/1');
    $blob = $im->getImageBlob();
    $im->clear();

    $file = UploadedFile::fake()->createWithContent('exif.jpg', $blob);

    $path = (new ImageProcessor)->processUpload($file);

    $out = new Imagick;
    $out->readImageBlob(Storage::disk('public')->get($path));
    $properties = $out->getImageProperties('exif:*');
    expect($properties)->toBeEmpty();
    $out->clear();
});

test('processBytes returns null on garbage input', function () {
    $path = (new ImageProcessor)->processBytes('not an image');

    expect($path)->toBeNull();
});

test('processBytes returns null on empty input', function () {
    $path = (new ImageProcessor)->processBytes('');

    expect($path)->toBeNull();
});

test('processBytes converts raw image bytes to WebP', function () {
    $path = (new ImageProcessor)->processBytes(makeImageBlob('jpeg', 800, 600));

    expect($path)->not->toBeNull();
    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);
});

test('writes to a custom directory', function () {
    $file = UploadedFile::fake()->createWithContent('avatar.jpg', makeImageBlob('jpeg'));

    $path = (new ImageProcessor)->processUpload($file, 'avatars');

    expect($path)->toStartWith('avatars/');
    expect($path)->toEndWith('.webp');
});
