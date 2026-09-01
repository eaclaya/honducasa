<?php

namespace App\Http\Controllers;

use App\Support\PhotoEnhancementStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingPhotoEnhancementStatusController extends Controller
{
    public function __invoke(
        Request $request,
        string $requestId,
        PhotoEnhancementStatus $status,
    ): JsonResponse {
        $result = $status->get((int) $request->user()->getKey(), $requestId);

        abort_if($result === null, 404);

        return response()->json($result);
    }
}
