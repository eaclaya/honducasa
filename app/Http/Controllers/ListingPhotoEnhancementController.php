<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnhanceListingPhotoRequest;
use App\Jobs\EnhanceListingPhoto;
use App\Models\ListingPhotoEnhancement;
use App\Support\ListingPhotoEnhancementQuota;
use App\Support\PhotoEnhancementStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListingPhotoEnhancementController extends Controller
{
    public function __invoke(
        EnhanceListingPhotoRequest $request,
        Media $media,
        PhotoEnhancementStatus $status,
        ListingPhotoEnhancementQuota $quota,
    ): JsonResponse {
        $user = $request->user();
        $listing = $request->listing();
        $draftKey = $quota->draftKey($request);
        $requestId = (string) Str::uuid();
        $userId = (int) $user->getKey();

        // The form request already reported an exhausted allowance with a
        // proper validation error; this returning null means concurrent
        // requests raced for the last slot and this one lost.
        $dispatched = $quota->consume($user, $listing, $draftKey, $media, function () use ($status, $userId, $requestId, $media): bool {
            $status->put($userId, $requestId, ['status' => 'queued']);
            EnhanceListingPhoto::dispatch($media->getKey(), $userId, $requestId);

            return true;
        });

        if ($dispatched === null) {
            throw ValidationException::withMessages([
                'media' => __('This listing has already used all :limit AI photo enhancements.', [
                    'limit' => ListingPhotoEnhancement::PER_LISTING_LIMIT,
                ]),
            ]);
        }

        return response()->json([
            'request_id' => $requestId,
            'remaining' => $quota->remaining($user, $listing, $draftKey),
        ], 202);
    }
}
