<?php

namespace App\Http\Controllers;

use App\Actions\Moderation\RecordModerationStrike;
use App\Exceptions\ContentModerationUnavailableException;
use App\Services\ListingPhotoCompressor;
use App\Services\OpenAiContentModerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Handles FilePond's immediate per-file uploads for the listing wizard.
 *
 * Files land in the uploading user's `pending-listing-photos` collection —
 * a temporary holder, since the wizard may not have created the listing yet
 * — and are moved onto the property when the listing is actually saved (see
 * `ListingController`).
 */
class ListingUploadController extends Controller
{
    public function __construct(
        private OpenAiContentModerator $contentModerator,
        private RecordModerationStrike $recordModerationStrike,
        private ListingPhotoCompressor $photoCompressor,
    ) {}

    public function store(Request $request): Response
    {
        $request->validate([
            'file' => [
                'required',
                'image',
                'mimes:webp',
                'mimetypes:image/webp',
                'max:20480',
            ],
        ], [
            'file.max' => __('The photo is too large — it must be at most 20 MB.'),
            'file.mimes' => __('Photos must be in WebP format.'),
            'file.mimetypes' => __('Photos must be in WebP format.'),
        ]);

        $file = $request->file('file');

        try {
            if ($this->contentModerator->imageIsFlagged($file)) {
                $this->recordModerationStrike->handle(
                    $request->user(),
                    'listing_image',
                    'Automated image moderation flagged an uploaded listing photo.',
                    ['filename' => $file->getClientOriginalName()],
                );

                throw ValidationException::withMessages([
                    'file' => __('This image contains content that is not allowed.'),
                ]);
            }
        } catch (ContentModerationUnavailableException) {
            throw ValidationException::withMessages([
                'file' => __('Content moderation is temporarily unavailable. Please try again.'),
            ]);
        }

        // Resolution is never a rejection reason — the photo is compressed
        // (and, as a last resort, downscaled) to fit a 2MB ceiling instead.
        $compressedPath = $this->photoCompressor->compress($file->getRealPath());

        try {
            $media = $request->user()->addMedia($compressedPath)
                ->usingFileName(Str::random(40).'.webp')
                ->toMediaCollection('pending-listing-photos');
        } finally {
            @unlink($compressedPath);
        }

        return response((string) $media->id, 201);
    }

    public function destroy(Request $request, Media $media): Response
    {
        abort_unless(
            $media->collection_name === 'pending-listing-photos'
                && $media->model_type === $request->user()->getMorphClass()
                && $media->model_id === $request->user()->getKey(),
            403,
        );

        $media->delete();

        return response('', 204);
    }
}
