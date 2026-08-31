<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnhanceListingPhotoRequest;
use App\Jobs\EnhanceListingPhoto;
use App\Support\PhotoEnhancementStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListingPhotoEnhancementController extends Controller
{
    public function __invoke(
        EnhanceListingPhotoRequest $request,
        Media $media,
        PhotoEnhancementStatus $status,
    ): JsonResponse {
        $requestId = (string) Str::uuid();
        $userId = (int) $request->user()->getKey();

        $status->put($userId, $requestId, ['status' => 'queued']);
        EnhanceListingPhoto::dispatch($media->getKey(), $userId, $requestId);

        return response()->json(['request_id' => $requestId], 202);
    }
}
