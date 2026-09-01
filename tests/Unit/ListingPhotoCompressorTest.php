<?php

use App\Services\ListingPhotoCompressor;

test('an image already under the size ceiling is copied through unchanged', function () {
    $source = tempnam(sys_get_temp_dir(), 'small-photo-').'.webp';
    $image = imagecreatetruecolor(400, 300);
    imagewebp($image, $source, 82);
    imagedestroy($image);
    $originalContents = file_get_contents($source);

    $output = app(ListingPhotoCompressor::class)->compress($source);

    expect($output)->not->toBe($source)
        ->and(file_get_contents($output))->toBe($originalContents);

    unlink($source);
    unlink($output);
});

test('a moderately oversized image is compressed under the 2MB ceiling on quality alone', function () {
    $source = noisyImagePath(width: 3000, height: 2000, cellSize: 6);
    expect(filesize($source))->toBeGreaterThan(2 * 1024 * 1024);

    $output = app(ListingPhotoCompressor::class)->compress($source);

    expect(filesize($output))->toBeLessThanOrEqual(2 * 1024 * 1024);
    [$width, $height] = getimagesize($output);
    expect($width)->toBe(3000)->and($height)->toBe(2000);

    unlink($source);
    unlink($output);
});

test('a photo that cannot reach the ceiling on quality alone is downscaled as a last resort', function () {
    $source = noisyImagePath(width: 3000, height: 2000, cellSize: 2);
    expect(filesize($source))->toBeGreaterThan(2 * 1024 * 1024);

    $output = app(ListingPhotoCompressor::class)->compress($source);

    expect(filesize($output))->toBeLessThanOrEqual(2 * 1024 * 1024);
    [$width] = getimagesize($output);
    expect($width)->toBeLessThan(3000);

    unlink($source);
    unlink($output);
});

test('the compressed output is always webp regardless of source format', function () {
    $source = tempnam(sys_get_temp_dir(), 'source-photo-').'.png';
    $image = imagecreatetruecolor(3000, 2000);
    imagepng($image, $source, 0);
    imagedestroy($image);

    $output = app(ListingPhotoCompressor::class)->compress($source);

    expect(getimagesize($output)[2])->toBe(IMAGETYPE_WEBP);

    unlink($source);
    unlink($output);
});

test('a small non-webp photo is still re-encoded rather than copied through', function () {
    $source = tempnam(sys_get_temp_dir(), 'small-photo-').'.jpg';
    $image = imagecreatetruecolor(400, 300);
    imagejpeg($image, $source, 90);
    imagedestroy($image);

    $output = app(ListingPhotoCompressor::class)->compress($source);

    // The under-the-ceiling shortcut only applies to sources that are already
    // webp; misreading the source format here would copy JPEG bytes into a
    // file named .webp.
    expect(getimagesize($output)[2])->toBe(IMAGETYPE_WEBP)
        ->and(file_get_contents($output))->not->toBe(file_get_contents($source));

    unlink($source);
    unlink($output);
});
