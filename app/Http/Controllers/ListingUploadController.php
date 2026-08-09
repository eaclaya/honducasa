<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    public function store(Request $request): Response
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120'],
        ]);

        $media = $request->user()->addMedia($request->file('file'))->toMediaCollection('pending-listing-photos');

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
