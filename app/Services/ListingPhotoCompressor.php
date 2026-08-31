<?php

namespace App\Services;

use Spatie\Image\Image;

/**
 * Any resolution is accepted for a listing photo — nothing gets rejected for
 * being "too big" — this is what backs that promise instead: every stored
 * photo is re-encoded as webp and shrunk (quality first, then, only if
 * that alone isn't enough, dimensions too) until it fits a 2MB ceiling.
 */
class ListingPhotoCompressor
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const MIN_QUALITY = 35;

    private const MAX_ATTEMPTS = 8;

    /**
     * Compress the image at `$sourcePath` and return the path to a new,
     * temporary webp file at or under the size ceiling (best effort on the
     * last attempt, if it truly can't be shrunk further). Callers own
     * cleanup of both the source and the returned path.
     *
     * A no-op copy when the source is already webp and already fits: the
     * browser has usually already shrunk it before upload, and re-encoding
     * an already-compressed webp gains nothing but a second, pointless
     * quality-loss pass. A source in any other format always gets encoded
     * at least once, so the output format promise ("always webp") holds
     * regardless of what produced the source file.
     */
    public function compress(string $sourcePath): string
    {
        if ($this->isWebp($sourcePath) && filesize($sourcePath) <= self::MAX_BYTES) {
            $copyPath = tempnam(sys_get_temp_dir(), 'listing-photo-').'.webp';
            copy($sourcePath, $copyPath);

            return $copyPath;
        }

        $quality = 82;
        $scale = 1.0;
        $outputPath = null;

        // Decoding a large source image (nothing here rejects resolution
        // upfront, so this has to handle genuinely huge photos) needs more
        // than PHP's default request memory limit — raised only for this
        // operation, restored immediately after.
        $previousMemoryLimit = ini_set('memory_limit', '512M');

        try {
            for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
                $image = Image::load($sourcePath)->format('webp')->quality($quality);

                if ($scale < 1.0) {
                    $image->width((int) round($image->getWidth() * $scale));
                }

                $attemptPath = tempnam(sys_get_temp_dir(), 'listing-photo-').'.webp';
                $image->save($attemptPath);

                if ($outputPath !== null) {
                    @unlink($outputPath);
                }

                $outputPath = $attemptPath;

                if (filesize($outputPath) <= self::MAX_BYTES) {
                    break;
                }

                $quality = max(self::MIN_QUALITY, $quality - 12);

                if ($quality <= self::MIN_QUALITY) {
                    $scale *= 0.8;
                }
            }
        } finally {
            if ($previousMemoryLimit !== false) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
        }

        return $outputPath;
    }

    private function isWebp(string $path): bool
    {
        $info = @getimagesize($path);

        return is_array($info) && ($info[2] ?? null) === IMAGETYPE_WEBP;
    }
}
