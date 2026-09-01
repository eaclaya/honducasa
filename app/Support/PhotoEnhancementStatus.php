<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PhotoEnhancementStatus
{
    /** @param array<string, mixed> $data */
    public function put(int $userId, string $requestId, array $data): void
    {
        Cache::put($this->key($userId, $requestId), $data, now()->addMinutes(30));
    }

    /** @return array<string, mixed>|null */
    public function get(int $userId, string $requestId): ?array
    {
        $status = Cache::get($this->key($userId, $requestId));

        return is_array($status) ? $status : null;
    }

    private function key(int $userId, string $requestId): string
    {
        return "listing-photo-enhancement:$userId:$requestId";
    }
}
