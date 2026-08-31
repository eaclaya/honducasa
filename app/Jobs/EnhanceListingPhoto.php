<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ListingPhotoCompressor;
use App\Services\OpenAiPropertyPhotoEnhancer;
use App\Support\PhotoEnhancementStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class EnhanceListingPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(
        public int $mediaId,
        public int $userId,
        public string $requestId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        OpenAiPropertyPhotoEnhancer $photoEnhancer,
        PhotoEnhancementStatus $status,
        ListingPhotoCompressor $photoCompressor,
    ): void {
        $status->put($this->userId, $this->requestId, ['status' => 'processing']);

        $media = Media::query()->findOrFail($this->mediaId);
        $user = User::query()->findOrFail($this->userId);
        $enhancedImage = $photoEnhancer->enhance($media);

        $rawPath = tempnam(sys_get_temp_dir(), 'enhanced-photo-');
        file_put_contents($rawPath, $enhancedImage);
        $compressedPath = $photoCompressor->compress($rawPath);
        @unlink($rawPath);

        try {
            $candidate = $user->addMedia($compressedPath)
                ->usingFileName('enhanced-'.Str::uuid().'.webp')
                ->withCustomProperties(['ai_enhanced' => true, 'source_media_id' => $media->getKey()])
                ->toMediaCollection('pending-listing-photos');
        } finally {
            @unlink($compressedPath);
        }

        $status->put($this->userId, $this->requestId, [
            'status' => 'completed',
            'candidate' => [
                'id' => $candidate->getKey(),
                'url' => $candidate->getFullUrl(),
                'name' => $candidate->name,
                'size' => $candidate->size,
            ],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        app(PhotoEnhancementStatus::class)->put($this->userId, $this->requestId, [
            'status' => 'failed',
            'message' => __('Photo enhancement is temporarily unavailable. Please try again.'),
        ]);
    }
}
