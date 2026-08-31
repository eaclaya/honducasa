<?php

use App\Models\Location;
use App\Support\HondurasCityCoordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function compressedListingPhoto(
    string $name = 'listing.webp',
    int $width = 1200,
    int $height = 800,
    int $quality = 82,
): UploadedFile {
    $image = imagecreatetruecolor($width, $height);

    ob_start();
    imagewebp($image, null, $quality);
    $contents = ob_get_clean();

    imagedestroy($image);

    return UploadedFile::fake()->createWithContent($name, $contents);
}

/**
 * A high-entropy webp, unlike `compressedListingPhoto()`'s flat canvas —
 * random-colored cells resist webp's spatial compression, so this reliably
 * produces a multi-megabyte file for testing the compression pipeline
 * itself. Returns a filesystem path, since that's what
 * `ListingPhotoCompressor` operates on.
 */
function noisyImagePath(int $width = 3000, int $height = 2000, int $cellSize = 2): string
{
    // The full suite shares one PHP process, so by the time this runs late
    // in a run there may be little headroom left under the default 128M —
    // generating the raw bitmap alone needs roughly width * height * 4 bytes.
    ini_set('memory_limit', '512M');

    $image = imagecreatetruecolor($width, $height);

    for ($x = 0; $x < $width; $x += $cellSize) {
        for ($y = 0; $y < $height; $y += $cellSize) {
            $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($image, $x, $y, $x + $cellSize, $y + $cellSize, $color);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'noisy-photo-').'.webp';
    imagewebp($image, $path, 100);
    imagedestroy($image);

    return $path;
}

/**
 * A submission pinned at the given city's center. The city itself is never
 * submitted — `SaveListingRequest` derives it from the pin — so pinning there is
 * what files the listing under `$location`.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function listingPayload(Location $location, array $overrides = []): array
{
    $center = HondurasCityCoordinates::for($location->name);

    return array_replace([
        'name' => 'Casa moderna en Tegucigalpa',
        'type' => 'house',
        'listing_type' => 'rent',
        'status' => 'draft',
        'price_amount' => 22_000,
        'currency' => 'HNL',
        'deposit_amount' => 22_000,
        'utilities_included' => false,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'parking_spaces' => 2,
        'interior_area_m2' => 180,
        'lot_area_m2' => 300,
        'year_built' => 2022,
        'furnishing' => 'unfurnished',
        'description' => 'Casa amplia y segura, cerca de comercios, escuelas y las principales vías de la ciudad.',
        'address_line' => 'Colonia Palmira, Tegucigalpa',
        'location_mode' => 'exact',
        'latitude' => $center?->latitude,
        'longitude' => $center?->longitude,
        'approximate_shape' => null,
        'approximate_radius_km' => null,
        'approximate_polygon' => null,
    ], $overrides);
}
